<?php

namespace App\Http\Controllers\Salon;

use App\Http\Controllers\Controller;
use App\Models\DisponibiliteException;
use App\Models\Employe;
use App\Models\Reservation;
use App\Models\Salon;
use App\Services\GestionnaireDisponibilite;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DisponibiliteController extends Controller
{
    public function __construct(
        private GestionnaireDisponibilite $gestionnaire
    ) {}

    private function salon()
    {
        return Auth::user()->salon()->firstOrFail();
    }

    /** Affiche le calendrier + édition horaires + exceptions */
    public function index(Request $request): View
    {
        $salon = $this->salon();

        $debutSemaine = $request->filled('debut')
            ? Carbon::parse($request->debut)->startOfWeek()
            : now()->startOfWeek();

        $finSemaine = $debutSemaine->copy()->endOfWeek();

        $reservations = Reservation::where('salon_id', $salon->id)
            ->whereBetween('date_heure', [$debutSemaine, $finSemaine])
            ->whereIn('statut', ['en_attente', 'confirmee'])
            ->with(['service', 'employe', 'client'])
            ->orderBy('date_heure')
            ->get();

        $employes = Employe::where('salon_id', $salon->id)->actifs()->get();

        $employeFiltre = $request->employe_id;
        if ($employeFiltre) {
            $reservations = $reservations->where('employe_id', $employeFiltre);
        }

        $calendrier = $this->organiserParJour($reservations, $debutSemaine);
        $tauxOccupation = $this->gestionnaire->tauxOccupation($salon, $debutSemaine, $finSemaine);
        $caEstime = $reservations->sum(fn ($r) => $r->service->prix ?? 0);

        $exceptions = DisponibiliteException::where('salon_id', $salon->id)
            ->where('date', '>=', now()->toDateString())
            ->with('employe')
            ->orderBy('date')
            ->get();

        return view('salon.disponibilites', compact(
            'salon', 'employes', 'calendrier',
            'debutSemaine', 'finSemaine',
            'tauxOccupation', 'caEstime', 'employeFiltre',
            'exceptions'
        ));
    }

    /** API créneaux disponibles */
    public function creneaux(Request $request): JsonResponse
    {
        $request->validate([
            'salon_id' => ['required', 'exists:salons,id'],
            'service_id' => ['required', 'exists:services,id'],
            'date' => ['required', 'date'],
            'employe_id' => ['nullable', 'exists:employes,id'],
        ]);

        $salon = Salon::valides()->findOrFail($request->salon_id);
        $service = $salon->servicesActifs()->findOrFail($request->service_id);
        $date = Carbon::parse($request->date);

        $creneaux = $this->gestionnaire->creneauxDuJour(
            $salon,
            $service,
            $date,
            $request->employe_id ? Employe::find($request->employe_id) : null
        );

        return response()->json($creneaux);
    }

    /** Bloquer un créneau manuellement (conservé pour l'UI existante) */
    public function bloquer(Request $request): RedirectResponse
    {
        $salon = $this->salon();

        $request->validate([
            'employe_id' => ['required', 'exists:employes,id'],
            'date_heure' => ['required', 'date', 'after:now'],
            'duree' => ['required', 'integer', 'min:15', 'max:480'],
            'motif' => ['nullable', 'string', 'max:120'],
        ]);

        $employe = Employe::where('salon_id', $salon->id)
            ->findOrFail($request->employe_id);

        Reservation::create([
            'client_id' => Auth::id(),
            'salon_id' => $salon->id,
            'service_id' => $salon->servicesActifs()->first()->id,
            'employe_id' => $employe->id,
            'date_heure' => $request->date_heure,
            'duree_minutes' => $request->duree,
            'statut' => 'confirmee',
            'notes_salon' => '__bloque__',
            'notes_client' => $request->motif ?? 'Créneau bloqué',
        ]);

        return back()->with('success', 'Créneau bloqué pour '.$employe->nomComplet().'.');
    }

    public function debloquer(int $id): RedirectResponse
    {
        $salon = $this->salon();

        $bloc = Reservation::where('salon_id', $salon->id)
            ->where('notes_salon', '__bloque__')
            ->findOrFail($id);

        $bloc->delete();

        return back()->with('success', 'Créneau débloqué.');
    }

    /*
    |------------------------------------------------------------------
    | Horaires hebdomadaires du salon
    |------------------------------------------------------------------
    */
    public function updateHoraires(Request $request): RedirectResponse
    {
        $salon = $this->salon();

        $jours = ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'];
        $horaires = [];

        foreach ($jours as $jour) {
            $ferme = $request->boolean("h_{$jour}_ferme", false);
            $horaires[$jour] = [
                'debut' => $ferme ? null : $request->input("h_{$jour}_debut"),
                'fin' => $ferme ? null : $request->input("h_{$jour}_fin"),
                'ferme' => $ferme,
            ];
        }

        $salon->update(['horaires' => $horaires]);

        return back()->with('success', 'Horaires du salon mis à jour.');
    }

    /*
    |------------------------------------------------------------------
    | Horaires hebdomadaires d'un employé
    |------------------------------------------------------------------
    */
    public function updateEmployeHoraires(Request $request, int $id): RedirectResponse
    {
        $salon = $this->salon();
        $employe = Employe::where('salon_id', $salon->id)->findOrFail($id);

        $jours = ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'];
        $horaires = [];

        foreach ($jours as $jour) {
            $ferme = $request->boolean("h_{$jour}_ferme", false);
            $horaires[$jour] = [
                'debut' => $ferme ? null : $request->input("h_{$jour}_debut"),
                'fin' => $ferme ? null : $request->input("h_{$jour}_fin"),
                'ferme' => $ferme,
            ];
        }

        $employe->horaires = $horaires;
        $employe->save();

        return back()->with('success', 'Horaires de '.$employe->nomComplet().' mis à jour.');
    }

    /*
    |------------------------------------------------------------------
    | Exceptions de disponibilité (date ponctuelle)
    |------------------------------------------------------------------
    */
    public function storeException(Request $request): RedirectResponse
    {
        $salon = $this->salon();

        $data = $request->validate([
            'employe_id' => ['nullable', 'exists:employes,id'],
            'date' => ['required', 'date', 'after_or_equal:today'],
            'ferme' => ['nullable', 'boolean'],
            'debut' => ['nullable', 'date_format:H:i'],
            'fin' => ['nullable', 'date_format:H:i', 'after:debut'],
            'motif' => ['nullable', 'string', 'max:160'],
        ]);

        if ($data['employe_id'] ?? null) {
            $exists = Employe::where('salon_id', $salon->id)->where('id', $data['employe_id'])->exists();
            if (! $exists) {
                abort(403);
            }
        }

        $ferme = (bool) ($data['ferme'] ?? false);

        DisponibiliteException::create([
            'salon_id' => $salon->id,
            'employe_id' => $data['employe_id'] ?? null,
            'date' => $data['date'],
            'ferme' => $ferme,
            'debut' => $ferme ? null : ($data['debut'] ?? null),
            'fin' => $ferme ? null : ($data['fin'] ?? null),
            'motif' => $data['motif'] ?? null,
        ]);

        return back()->with('success', 'Exception enregistrée.');
    }

    public function destroyException(int $id): RedirectResponse
    {
        $salon = $this->salon();

        $exception = DisponibiliteException::where('salon_id', $salon->id)->findOrFail($id);
        $exception->delete();

        return back()->with('success', 'Exception supprimée.');
    }

    private function organiserParJour($reservations, Carbon $debutSemaine): array
    {
        $calendrier = [];

        for ($i = 0; $i < 7; $i++) {
            $date = $debutSemaine->copy()->addDays($i);
            $calendrier[$date->format('Y-m-d')] = [
                'date' => $date,
                'reservations' => $reservations->filter(fn ($r) => $r->date_heure->toDateString() === $date->toDateString()
                )->values(),
            ];
        }

        return $calendrier;
    }
}
