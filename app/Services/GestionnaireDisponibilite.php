<?php

namespace App\Services;

use App\Models\DisponibiliteException;
use App\Models\Employe;
use App\Models\Reservation;
use App\Models\Salon;
use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class GestionnaireDisponibilite
{
    private int $pas = 30; // minutes entre créneaux

    private ?bool $hasExceptionsTable = null;

    /**
     * Retourne les créneaux disponibles pour un jour donné.
     */
    public function creneauxDuJour(
        Salon $salon,
        Service $service,
        Carbon $date,
        ?Employe $employe = null
    ): array {
        $plage = $this->plageSalonPourDate($salon, $date);
        if (! $plage) {
            return [];
        }

        [$debut, $fin] = $plage;

        if ($employe) {
            $plageEmp = $this->plageEmployePourDate($employe, $date, $plage);
            if (! $plageEmp) {
                return [];
            }
            [$dE, $fE] = $plageEmp;
            if ($dE->gt($debut)) {
                $debut = $dE;
            }
            if ($fE->lt($fin)) {
                $fin = $fE;
            }
            if ($debut->gte($fin)) {
                return [];
            }
        }

        $reservations = $this->reservationsDuJour($salon, $date, $employe);

        $creneaux = [];
        $cursor = $debut->copy();
        $dureeSvc = $service->duree_minu ?? 30;

        while ($cursor->copy()->addMinutes($dureeSvc)->lte($fin)) {
            $dateheure = $cursor->copy();
            $disponible = $this->creneauLibre($dateheure, $dureeSvc, $reservations, $employe);

            if ($disponible && ! $employe) {
                $disponible = $this->auMoinsUnEmployeDispo($salon, $dateheure, $dureeSvc, $plage);
            }

            if ($dateheure->isPast()) {
                $disponible = false;
            }

            $creneaux[] = [
                'heure' => $dateheure->format('H:i'),
                'datetime' => $dateheure->toDateTimeString(),
                'disponible' => $disponible,
            ];

            $cursor->addMinutes($this->pas);
        }

        return $creneaux;
    }

    public function creneauxDisponibles(
        Salon $salon,
        Service $service,
        Carbon $debut,
        Carbon $fin
    ): Collection {
        $result = collect();
        $cursor = $debut->copy()->startOfDay();
        while ($cursor->lte($fin)) {
            $result->put($cursor->toDateString(), $this->creneauxDuJour($salon, $service, $cursor->copy()));
            $cursor->addDay();
        }

        return $result;
    }

    public function estDisponible(
        Salon $salon,
        Service $service,
        Carbon $dateHeure,
        ?Employe $employe = null
    ): bool {
        if ($dateHeure->isPast()) {
            return false;
        }

        $plage = $this->plageSalonPourDate($salon, $dateHeure);
        if (! $plage) {
            return false;
        }
        [$debut, $fin] = $plage;

        $duree = $service->duree_minu ?? 30;
        $finCren = $dateHeure->copy()->addMinutes($duree);

        if ($dateHeure->lt($debut) || $finCren->gt($fin)) {
            return false;
        }

        if ($employe) {
            $plageEmp = $this->plageEmployePourDate($employe, $dateHeure, $plage);
            if (! $plageEmp) {
                return false;
            }
            [$dE, $fE] = $plageEmp;
            if ($dateHeure->lt($dE) || $finCren->gt($fE)) {
                return false;
            }
        }

        $reservations = $this->reservationsDuJour($salon, $dateHeure, $employe);

        if (! $this->creneauLibre($dateHeure, $duree, $reservations, $employe)) {
            return false;
        }

        if (! $employe) {
            return $this->auMoinsUnEmployeDispo($salon, $dateHeure, $duree, $plage);
        }

        return true;
    }

    public function tauxOccupation(Salon $salon, Carbon $debut, Carbon $fin): float
    {
        $total = Reservation::where('salon_id', $salon->id)
            ->whereBetween('date_heure', [$debut, $fin])
            ->whereIn('statut', ['en_attente', 'confirmee', 'terminee'])
            ->count();

        $capacite = max(1, ($salon->nb_employes ?? 1)) * 7 * 16;

        return min(100, round(($total / $capacite) * 100, 1));
    }

    /*
    |------------------------------------------------------------------
    | Plages horaires effectives (horaires hebdo + exceptions)
    |------------------------------------------------------------------
    */

    private function plageSalonPourDate(Salon $salon, Carbon $date): ?array
    {
        $ex = $this->exceptionPourSalon($salon->id, $date);

        if ($ex) {
            if ($ex->ferme) {
                return null;
            }

            return [
                Carbon::parse($date->toDateString().' '.$ex->debut),
                Carbon::parse($date->toDateString().' '.$ex->fin),
            ];
        }

        $jours = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
        $jour = $jours[$date->dayOfWeek];

        $horaires = $salon->horaires;

        // Fallback : si aucun horaire n'est configuré, on suppose le salon ouvert
        // 9h-19h du lundi au samedi (dimanche fermé). Évite que le calendrier
        // de réservation soit entièrement bloqué tant que le gérant n'a pas
        // renseigné ses horaires.
        if (empty($horaires) || ! isset($horaires[$jour])) {
            if ($jour === 'dimanche') {
                return null;
            }

            return [
                Carbon::parse($date->toDateString().' 09:00'),
                Carbon::parse($date->toDateString().' 19:00'),
            ];
        }

        if (! $salon->estOuvert($jour)) {
            return null;
        }

        $debut = $salon->heureOuverture($jour) ?? '09:00';
        $fin = $salon->heureFermeture($jour) ?? '18:00';

        return [
            Carbon::parse($date->toDateString().' '.$debut),
            Carbon::parse($date->toDateString().' '.$fin),
        ];
    }

    /**
     * Plage effective pour un employé. Si l'employé n'a pas d'horaires configurés,
     * on tombe sur la plage du salon (rétrocompat : avant ce feature, les employés
     * héritaient implicitement des horaires salon).
     *
     * @param  array  $plageSalon  [Carbon $debut, Carbon $fin]
     */
    private function plageEmployePourDate(Employe $employe, Carbon $date, array $plageSalon): ?array
    {
        $ex = $this->exceptionPourEmploye($employe->id, $date);

        if ($ex) {
            if ($ex->ferme) {
                return null;
            }

            return [
                Carbon::parse($date->toDateString().' '.$ex->debut),
                Carbon::parse($date->toDateString().' '.$ex->fin),
            ];
        }

        $h = $employe->horaires ?? [];
        if (empty($h)) {
            // Pas d'horaires configurés → on s'aligne sur le salon (comportement historique)
            return $plageSalon;
        }

        $jours = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
        $jour = $jours[$date->dayOfWeek];

        if (! isset($h[$jour]) || ($h[$jour]['ferme'] ?? false)) {
            return null;
        }

        $debut = $h[$jour]['debut'] ?? null;
        $fin = $h[$jour]['fin'] ?? null;
        if (! $debut || ! $fin) {
            return $plageSalon;
        }

        return [
            Carbon::parse($date->toDateString().' '.$debut),
            Carbon::parse($date->toDateString().' '.$fin),
        ];
    }

    private function reservationsDuJour(Salon $salon, Carbon $date, ?Employe $employe): Collection
    {
        $q = Reservation::where('salon_id', $salon->id)
            ->whereDate('date_heure', $date->toDateString())
            ->whereIn('statut', ['en_attente', 'confirmee']);

        if ($employe) {
            $q->where('employe_id', $employe->id);
        }

        return $q->get();
    }

    private function auMoinsUnEmployeDispo(Salon $salon, Carbon $dateHeure, int $duree, array $plageSalon): bool
    {
        $employes = $salon->employesActifs;

        // Salon sans équipe → on considère le salon lui-même comme "ressource"
        if ($employes->isEmpty()) {
            return true;
        }

        foreach ($employes as $emp) {
            $plage = $this->plageEmployePourDate($emp, $dateHeure, $plageSalon);
            if (! $plage) {
                continue;
            }
            [$dE, $fE] = $plage;
            $finCren = $dateHeure->copy()->addMinutes($duree);
            if ($dateHeure->lt($dE) || $finCren->gt($fE)) {
                continue;
            }

            $resas = $this->reservationsDuJour($salon, $dateHeure, $emp);
            if ($this->creneauLibre($dateHeure, $duree, $resas, $emp)) {
                return true;
            }
        }

        return false;
    }

    private function creneauLibre(
        Carbon $dateHeure,
        int $dureeMinutes,
        Collection $reservations,
        ?Employe $employe = null
    ): bool {
        $finCreneau = $dateHeure->copy()->addMinutes($dureeMinutes);

        foreach ($reservations as $r) {
            if ($employe && $r->employe_id && $r->employe_id !== $employe->id) {
                continue;
            }

            $debutRes = Carbon::parse($r->date_heure);
            $finRes = $debutRes->copy()->addMinutes($r->duree_minutes ?? 30);

            if ($dateHeure->lt($finRes) && $finCreneau->gt($debutRes)) {
                return false;
            }
        }

        return true;
    }

    /*
    |------------------------------------------------------------------
    | Exceptions — requêtes protégées (ne plante pas si table absente)
    |------------------------------------------------------------------
    */

    private function exceptionsTableExists(): bool
    {
        if ($this->hasExceptionsTable === null) {
            try {
                $this->hasExceptionsTable = Schema::hasTable('disponibilite_exceptions');
            } catch (\Throwable $e) {
                Log::warning('[Dispo] Schema::hasTable a échoué', ['err' => $e->getMessage()]);
                $this->hasExceptionsTable = false;
            }
        }

        return $this->hasExceptionsTable;
    }

    private function exceptionPourSalon(int $salonId, Carbon $date): ?DisponibiliteException
    {
        if (! $this->exceptionsTableExists()) {
            return null;
        }
        try {
            return DisponibiliteException::where('salon_id', $salonId)
                ->whereNull('employe_id')
                ->whereDate('date', $date->toDateString())
                ->first();
        } catch (\Throwable $e) {
            Log::warning('[Dispo] Lecture exception salon a échoué', ['err' => $e->getMessage()]);

            return null;
        }
    }

    private function exceptionPourEmploye(int $employeId, Carbon $date): ?DisponibiliteException
    {
        if (! $this->exceptionsTableExists()) {
            return null;
        }
        try {
            return DisponibiliteException::where('employe_id', $employeId)
                ->whereDate('date', $date->toDateString())
                ->first();
        } catch (\Throwable $e) {
            Log::warning('[Dispo] Lecture exception employé a échoué', ['err' => $e->getMessage()]);

            return null;
        }
    }
}
