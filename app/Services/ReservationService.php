<?php

namespace App\Services;

use App\Mail\NouvelleReservationMail;
use App\Models\Employe;
use App\Models\Reservation;
use App\Models\Salon;
use App\Models\Service;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ReservationService
{
    public function __construct(
        private GestionnaireDisponibilite $disponibilite,
        private NotificationService $notifService
    ) {}

    /**
     * Crée une seule réservation (rétro-compat).
     */
    public function creer(User $client, Salon $salon, array $data): Reservation
    {
        Log::info('[Reservation] → Création', [
            'client_id' => $client->id,
            'salon_id' => $salon->id,
            'service_id' => $data['service_id'] ?? null,
            'date_heure' => $data['date_heure'] ?? null,
        ]);

        $service = Service::where('salon_id', $salon->id)
            ->findOrFail($data['service_id']);

        $employe = null;
        if (! empty($data['employe_id'])) {
            $employe = Employe::where('salon_id', $salon->id)
                ->findOrFail($data['employe_id']);
        }

        $dateHeure = Carbon::parse($data['date_heure']);

        if (! $this->disponibilite->estDisponible($salon, $service, $dateHeure, $employe)) {
            Log::warning('[Reservation] ✗ Créneau indisponible', [
                'client_id' => $client->id,
                'salon_id' => $salon->id,
                'service_id' => $service->id,
                'date_heure' => $dateHeure->toDateTimeString(),
                'employe_id' => $employe?->id,
            ]);
            abort(409, 'Ce créneau n\'est plus disponible. Veuillez en choisir un autre.');
        }

        $reservation = Reservation::create([
            'client_id' => $client->id,
            'salon_id' => $salon->id,
            'service_id' => $service->id,
            'employe_id' => $employe?->id,
            'date_heure' => $dateHeure,
            'duree_minutes' => $data['duree_minutes'] ?? $service->duree_minu ?? 30,
            'notes_client' => $data['notes_client'] ?? null,
            'statut' => 'en_attente',
        ]);

        Log::info('[Reservation] ✓ Créée', [
            'reservation_id' => $reservation->id,
            'client_id' => $client->id,
            'salon_id' => $salon->id,
        ]);

        $this->notifierSalon($reservation, $client, $salon, $service, $dateHeure);

        return $reservation;
    }

    /**
     * Crée un groupe de réservations en transaction. Toutes partagent un groupe_uuid.
     *
     * @param  array  $selections  Chaque item : ['service_id'=>..., 'date_heure'=>..., 'employe_id'=>null|id]
     */
    public function creerGroupe(User $client, Salon $salon, array $selections, ?string $notesClient = null): Collection
    {
        Log::info('[Reservation] → Création groupe', [
            'client_id' => $client->id,
            'salon_id' => $salon->id,
            'count' => count($selections),
        ]);

        // Validation préalable : toutes les dispos avant de créer quoi que ce soit
        // Service et employé doivent appartenir au salon en cours pour empêcher
        // la réservation cross-tenant via manipulation des IDs côté client.

        $serviceIds = collect($selections)->pluck('service_id')->filter()->unique();
        $employeIds = collect($selections)->pluck('employe_id')->filter()->unique();

        $services = Service::where('salon_id', $salon->id)
            ->whereIn('id', $serviceIds)
            ->get()
            ->keyBy('id');

        $employes = Employe::where('salon_id', $salon->id)
            ->whereIn('id', $employeIds)
            ->get()
            ->keyBy('id');

        $parsed = [];
        foreach ($selections as $sel) {
            $service = $services->get($sel['service_id']);
            if (! $service) {
                abort(404, 'Service non trouvé.');
            }

            $employe = null;
            if (! empty($sel['employe_id'])) {
                $employe = $employes->get($sel['employe_id']);
                if (! $employe) {
                    abort(404, 'Employé non trouvé.');
                }
            }

            $dateHeure = Carbon::parse($sel['date_heure']);

            if (! $this->disponibilite->estDisponible($salon, $service, $dateHeure, $employe)) {
                Log::warning('[Reservation] ✗ Créneau groupe indisponible', [
                    'client_id' => $client->id,
                    'service_id' => $service->id,
                    'date_heure' => $dateHeure->toDateTimeString(),
                    'employe_id' => $employe?->id,
                ]);
                abort(409, "Le créneau pour « {$service->nom_service} » n'est plus disponible. Veuillez en choisir un autre.");
            }

            $parsed[] = compact('service', 'employe', 'dateHeure');
        }

        // Détection de chevauchement interne (mêmes employé/salon dans le panier)
        $this->detecterChevauchementInterne($parsed);

        // Si la colonne groupe_uuid n'est pas (encore) disponible en base, on omet le champ
        $hasGroupeCol = true;
        try {
            $hasGroupeCol = Schema::hasColumn('reservations', 'groupe_uuid');
        } catch (\Throwable $e) {
            $hasGroupeCol = false;
        }

        $groupeUuid = ($hasGroupeCol && count($parsed) > 1) ? (string) Str::uuid() : null;
        $reservations = collect();

        DB::transaction(function () use ($parsed, $client, $salon, $notesClient, $groupeUuid, $hasGroupeCol, &$reservations) {
            foreach ($parsed as $p) {
                $payload = [
                    'client_id' => $client->id,
                    'salon_id' => $salon->id,
                    'service_id' => $p['service']->id,
                    'employe_id' => $p['employe']?->id,
                    'date_heure' => $p['dateHeure'],
                    'duree_minutes' => $p['service']->duree_minu ?? 30,
                    'notes_client' => $notesClient,
                    'statut' => 'en_attente',
                ];
                if ($hasGroupeCol) {
                    $payload['groupe_uuid'] = $groupeUuid;
                }
                $reservations->push(Reservation::create($payload));
            }
        });

        Log::info('[Reservation] ✓ Groupe créé', [
            'groupe_uuid' => $groupeUuid,
            'reservation_ids' => $reservations->pluck('id'),
        ]);

        // Notifier le salon (une fois par réservation, email groupé possible en amélioration)
        foreach ($reservations as $r) {
            $this->notifierSalon($r, $client, $salon, $r->service, Carbon::parse($r->date_heure));
        }

        return $reservations;
    }

    /**
     * Lève une erreur si deux items du panier se chevauchent (même employé ou sans employé).
     */
    private function detecterChevauchementInterne(array $parsed): void
    {
        for ($i = 0; $i < count($parsed); $i++) {
            for ($j = $i + 1; $j < count($parsed); $j++) {
                $a = $parsed[$i];
                $b = $parsed[$j];

                // Si un employé précis est ciblé, chevauchement seulement si c'est le même
                $memeContexte = (! $a['employe'] || ! $b['employe'])
                    || ($a['employe']->id === $b['employe']->id);
                if (! $memeContexte) {
                    continue;
                }

                $finA = $a['dateHeure']->copy()->addMinutes($a['service']->duree_minu ?? 30);
                $finB = $b['dateHeure']->copy()->addMinutes($b['service']->duree_minu ?? 30);

                if ($a['dateHeure']->lt($finB) && $finA->gt($b['dateHeure'])) {
                    abort(422, 'Deux prestations se chevauchent dans votre panier. Choisissez des créneaux distincts.');
                }
            }
        }
    }

    private function notifierSalon(Reservation $reservation, User $client, Salon $salon, Service $service, Carbon $dateHeure): void
    {
        $this->notifService->envoyer(
            $salon->user_id,
            'nouvelle_reservation',
            [
                'client' => $client->nomComplet(),
                'service' => $service->nom_service,
                'date' => $dateHeure->translatedFormat('D d M Y'),
                'heure' => $dateHeure->format('H:i'),
            ]
        );

        try {
            $reservation->load(['client', 'salon.ville', 'service', 'employe']);
            Mail::to($salon->user->email)->send(new NouvelleReservationMail($reservation));
        } catch (\Throwable $e) {
            Log::error('[Reservation] ✗ Erreur email nouvelle_reservation', [
                'reservation_id' => $reservation->id,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ]);
        }
    }
}
