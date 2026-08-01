<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Employe;
use App\Models\Reservation;
use App\Models\Salon;
use App\Services\GestionnaireDisponibilite;
use App\Services\ReservationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ReservationController extends Controller
{
    public function __construct(
        private ReservationService $reservationService,
        private GestionnaireDisponibilite $disponibiliteService,
    ) {}

    /** STEP 1 — Choix du service (Modernisé pour autoriser le multi-choix) */
    public function step1(string $salon)
    {
        $salonModel = $this->getSalonOr404($salon);
        $categories = $salonModel->servicesActifs->pluck('categorie')->unique()->values();
        $services = $salonModel->servicesActifs;

        return view('reservations.step1', compact('salonModel', 'services', 'categories'));
    }

    /** STEP 2 — Choix du créneau (Optimisé AJAX & Multi-services) */
    public function step2(Request $request, string $salon)
    {
        $salonModel = $this->getSalonOr404($salon);
        $sessionData = $request->session()->get("wizard_{$salonModel->id}", []);

        // On récupère soit le tableau service_ids, soit l'unique service_id (flexibilité)
        $serviceIds = $sessionData['service_ids'] ?? (isset($sessionData['service_id']) ? [$sessionData['service_id']] : null);

        if (! $serviceIds) {
            return redirect()->route('reservations.step1', $salon)
                ->with('error', 'Veuillez d\'abord choisir au moins un service.');
        }

        $services = $salonModel->servicesActifs()->whereIn('id', $serviceIds)->get();
        if ($services->isEmpty()) {
            return redirect()->route('reservations.step1', $salon)
                ->with('error', 'Veuillez sélectionner des services valides.');
        }

        $employes = $salonModel->employesActifs;
        // Chargement lazy en AJAX dans la vue pour éviter les timeouts/500 en prod.
        $creneauxParService = collect();
        $servicesMeta = $services->map(function ($service) {
            return [
                'id' => (string) $service->id,
                'name' => $service->nom_service,
                'duration' => $service->duree_formatee,
                'price' => $service->prix_format,
                'prix' => (float) $service->prix,
            ];
        })->values();

        return view('reservations.step2', compact('salonModel', 'services', 'employes', 'creneauxParService', 'servicesMeta'));
    }

    /** AJAX — Nouvelle fonction pour le chargement dynamique (indispensable pour l'optimisation) */
    public function getCreneaux(Request $request, string $salon, int $serviceId)
    {
        $salonModel = $this->getSalonOr404($salon);
        $service = $salonModel->servicesActifs()->findOrFail($serviceId);

        $annee = (int) $request->input('annee', now()->year);
        $mois = (int) $request->input('mois', now()->month);

        $debut = Carbon::createFromDate($annee, $mois, 1)->startOfDay();
        $fin = $debut->copy()->endOfMonth()->endOfDay();

        $creneaux = $this->disponibiliteService->creneauxDisponibles($salonModel, $service, $debut, $fin);

        return response()->json($creneaux);
    }

    /** STEP 3 — Informations client (Adapté au panier) */
    public function step3(Request $request, string $salon)
    {
        $salonModel = $this->getSalonOr404($salon);
        $user = Auth::user();
        $sessionData = $request->session()->get("wizard_{$salonModel->id}", []);

        // On normalise les sélections pour gérer le panier
        $selections = $sessionData['selections'] ?? null;
        if (! $selections && ! empty($sessionData['service_id']) && ! empty($sessionData['date_heure'])) {
            $selections = [[
                'service_id' => (int) $sessionData['service_id'],
                'date_heure' => $sessionData['date_heure'],
                'employe_id' => $sessionData['employe_id'] ?? null,
            ]];
        }

        if (empty($selections)) {
            return redirect()->route('reservations.step1', $salon);
        }

        $ids = collect($selections)->pluck('service_id')->all();
        $services = $salonModel->servicesActifs()->whereIn('id', $ids)->get()->keyBy('id');
        $employes = Employe::whereIn('id', collect($selections)->pluck('employe_id')->filter()->all())->get()->keyBy('id');

        $items = collect($selections)->map(function ($s) use ($services, $employes) {
            return [
                'service' => $services[$s['service_id']] ?? null,
                'employe' => ! empty($s['employe_id']) ? ($employes[$s['employe_id']] ?? null) : null,
                'date_heure' => $s['date_heure'],
            ];
        })->filter(fn ($i) => $i['service'] !== null)->values();

        $total = $items->sum(fn ($i) => (float) $i['service']->prix);

        return view('reservations.create', compact('salonModel', 'items', 'user', 'total'));
    }

    /** STEP 4 — Enregistrement (Utilise creerGroupe pour la flexibilité) */
    public function store(Request $request, string $salon)
    {
        $salonModel = $this->getSalonOr404($salon);

        $data = $request->validate([
            'selections' => ['required', 'array', 'min:1'],
            'selections.*.service_id' => ['required', 'exists:services,id'],
            'selections.*.employe_id' => ['nullable', 'exists:employes,id'],
            'selections.*.date_heure' => ['required', 'date', 'after:now'],
            'notes_client' => ['nullable', 'string', 'max:500'],
        ]);

        // Appel à creerGroupe pour supporter le panier
        $reservations = $this->reservationService->creerGroupe(
            Auth::user(),
            $salonModel,
            $data['selections'],
            $data['notes_client'] ?? null
        );

        Log::info('Client: reservations creees', [
            'client_id' => Auth::id(),
            'salon_id' => $salonModel->id,
            'reservation_ids' => $reservations->pluck('id')->all(),
            'nb_services' => count($data['selections']),
        ]);

        $request->session()->forget("wizard_{$salonModel->id}");

        return redirect()->route('reservations.confirmation', $reservations->first()->id);
    }

    public function saveStep(Request $request, string $salon)
    {
        $salonModel = $this->getSalonOr404($salon);
        $wizardKey = "wizard_{$salonModel->id}";
        $payload = $request->session()->get($wizardKey, []);
        $step = $request->input('step');

        if ($step === 'services') {
            $validated = $request->validate([
                'service_ids' => ['required', 'array', 'min:1'],
                'service_ids.*' => ['required', 'integer', 'exists:services,id'],
                'redirect_to' => ['nullable', 'url'],
            ]);

            $validIds = $salonModel->servicesActifs()
                ->whereIn('id', $validated['service_ids'])
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();

            if (empty($validIds)) {
                return back()->with('error', 'Aucun service valide sélectionné pour ce salon.');
            }

            $payload['service_ids'] = $validIds;
            $payload['service_id'] = $validIds[0];
            $payload['selections'] = [];
        }

        if ($step === 'creneaux') {
            $validated = $request->validate([
                'selections' => ['required', 'array', 'min:1'],
                'selections.*.service_id' => ['required', 'integer', 'exists:services,id'],
                'selections.*.employe_id' => ['nullable', 'integer', 'exists:employes,id'],
                'selections.*.date_heure' => ['required', 'date', 'after:now'],
            ]);

            $serviceIdsActifs = $salonModel->servicesActifs()->pluck('id')->all();
            $selections = collect($validated['selections'])
                ->filter(fn ($sel) => in_array((int) $sel['service_id'], $serviceIdsActifs, true))
                ->map(fn ($sel) => [
                    'service_id' => (int) $sel['service_id'],
                    'employe_id' => ! empty($sel['employe_id']) ? (int) $sel['employe_id'] : null,
                    'date_heure' => $sel['date_heure'],
                ])
                ->values()
                ->all();

            if (empty($selections)) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Aucun créneau valide transmis.',
                ], 422);
            }

            $payload['selections'] = $selections;
        }

        $request->session()->put($wizardKey, $payload);

        $redirectTo = $request->input('redirect_to');
        if ($redirectTo) {
            return redirect()->to($redirectTo);
        }

        return response()->json(['ok' => true]);
    }

    public function confirmation(int $id)
    {
        $reservation = Reservation::with(['salon.ville', 'service', 'employe', 'client'])
            ->where('client_id', Auth::id())
            ->findOrFail($id);

        $groupe = null;
        if (! empty($reservation->groupe_uuid)) {
            $groupe = Reservation::with(['service', 'employe'])
                ->where('client_id', Auth::id())
                ->where('groupe_uuid', $reservation->groupe_uuid)
                ->orderBy('date_heure')
                ->get();
        }

        return view('reservations.confirmation', compact('reservation', 'groupe'));
    }

    public function index(Request $request)
    {
        $statut = $request->query('statut');

        Log::info('Client: liste reservations consultee', [
            'client_id' => Auth::id(),
            'statut' => $statut,
        ]);

        $reservations = Reservation::with(['salon.ville', 'service', 'employe', 'avis'])
            ->where('client_id', Auth::id())
            ->when($statut === 'confirmee', fn ($q) => $q
                ->where('statut', 'confirmee')
                ->where('date_heure', '>=', now()))
            ->when($statut === 'terminee', fn ($q) => $q->where(fn ($sub) => $sub
                ->where('statut', 'terminee')
                ->orWhere(fn ($s) => $s
                    ->where('statut', 'confirmee')
                    ->where('date_heure', '<', now()))))
            ->when($statut && ! in_array($statut, ['confirmee', 'terminee'], true),
                fn ($q) => $q->where('statut', $statut))
            ->orderByDesc('date_heure')
            ->paginate(10)
            ->withQueryString();

        return view('reservations.index', compact('reservations'));
    }

    public function show(int $id)
    {
        $reservation = Reservation::with(['salon.ville', 'service', 'employe', 'avis'])
            ->where('client_id', Auth::id())
            ->findOrFail($id);

        return view('reservations.show', compact('reservation'));
    }

    public function annuler(Request $request, int $id)
    {
        $reservation = Reservation::where('client_id', Auth::id())->findOrFail($id);

        if (! $reservation->peutEtreAnnulee()) {
            Log::warning('Client: tentative annulation refusee', [
                'client_id' => Auth::id(),
                'reservation_id' => $id,
                'statut_actuel' => $reservation->statut,
            ]);

            return back()->with('error', 'Cette réservation ne peut plus être annulée.');
        }

        $motif = $request->input('motif_annul', 'Annulation par le client');

        $reservation->update([
            'statut' => 'annulee',
            'annulee_par' => 'client',
            'date_annul' => now(),
            'motif_annul' => $motif,
        ]);

        Log::info('Client: reservation annulee', [
            'client_id' => Auth::id(),
            'reservation_id' => $id,
            'motif' => $motif,
        ]);

        return back()->with('success', 'La réservation a été annulée avec succès.');
    }

    private function getSalonOr404(string $slugOrId): Salon
    {
        if (is_numeric($slugOrId)) {
            return Salon::valides()
                ->with(['ville', 'servicesActifs', 'employesActifs'])
                ->findOrFail((int) $slugOrId);
        }

        // Recherche par slug sans charger tous les salons en mémoire.
        $normalizedSlug = strtolower(trim($slugOrId));
        $sqlSlug = str_replace(["'", ' '], ['', '-'], $normalizedSlug);

        $salon = Salon::valides()
            ->whereRaw('LOWER(REPLACE(REPLACE(nom_salon, " ", "-"), "\'", "")) = ?', [$sqlSlug])
            ->first();

        // Fallback: tentative via nom exact reconstruit depuis le slug.
        if (! $salon) {
            $nomSinceSlug = str_replace('-', ' ', $normalizedSlug);
            $salon = Salon::valides()
                ->whereRaw('LOWER(nom_salon) = ?', [$nomSinceSlug])
                ->first();
        }

        // Dernier fallback robuste si format de slug atypique.
        if (! $salon) {
            $minimal = Salon::valides()->get(['id', 'nom_salon']);
            $match = $minimal->first(fn (Salon $s) => $s->slug === $slugOrId);
            if ($match) {
                $salon = Salon::valides()->find($match->id);
            }
        }

        if (! $salon) {
            abort(404);
        }

        $salon->loadMissing(['ville', 'servicesActifs', 'employesActifs']);

        return $salon;
    }
}
