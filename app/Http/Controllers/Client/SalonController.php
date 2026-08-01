<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Avis;
use App\Models\Reservation;
use App\Models\Salon;
use App\Models\Ville;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SalonController extends Controller
{
    /*
    |------------------------------------------------------------------
    | Liste des salons d'une ville  GET /salons/{ville}
    |------------------------------------------------------------------
    */
    public function index(Request $request, string $ville): View
    {
        $villeModel = Ville::actives()
            ->where('nom_ville', 'like', $ville)
            ->firstOrFail();

        $realNbAvis = Avis::selectRaw('COUNT(*)')
            ->join('reservations', 'avis.reservation_id', '=', 'reservations.id')
            ->whereColumn('reservations.salon_id', 'salons.id');

        $realNoteMoy = Avis::selectRaw('COALESCE(ROUND(AVG(avis.note), 1), 0)')
            ->join('reservations', 'avis.reservation_id', '=', 'reservations.id')
            ->whereColumn('reservations.salon_id', 'salons.id');

        $query = Salon::valides()
            ->addSelect(['*',
                'real_nb_avis' => $realNbAvis,
                'real_note_moy' => $realNoteMoy,
            ])
            ->with(['ville', 'servicesActifs'])
            ->parVille($villeModel->id);

        // ── Auto-filtre quartier ──────────────────────────────────────
        // 1. Quartier passé en URL (priorité absolue)
        // 2. Quartier GPS passé en URL (?gps=1&quartier=...)
        // 3. Quartier du profil client connecté (même ville)
        $autoQuartier = false;   // true = filtre appliqué automatiquement
        $quartierActif = null;    // valeur du quartier filtré

        if ($request->filled('quartier')) {
            // Filtre manuel ou GPS via URL
            $quartierActif = $request->quartier;
            $query->parQuartier($quartierActif);
            $autoQuartier = (bool) $request->boolean('auto');

        } elseif (Auth::check()) {
            $user = Auth::user();
            if ($user->ville_id === $villeModel->id && $user->quartier) {
                // Client connecté avec même ville → auto-filtre
                $quartierActif = $user->quartier;
                $query->parQuartier($quartierActif);
                $autoQuartier = true;
            }
        }

        // Filtre catégorie de service
        if ($request->filled('categorie')) {
            $query->whereHas('services', fn ($q) => $q->where('categorie', $request->categorie)->where('actif', 1)
            );
        }

        // Filtre note minimale
        if ($request->filled('note_min')) {
            $query->where('note_moy', '>=', (float) $request->note_min);
        }

        // Tri
        $tri = $request->get('tri', 'note');
        $query = match ($tri) {
            'alpha' => $query->orderBy('nom_salon'),
            'avis' => $query->orderByDesc('nb_avis'),
            default => $query->mieuxNotes(),
        };

        $salons = $query->paginate(15)->withQueryString();

        // Quartiers disponibles pour les filtres
        $quartiers = Salon::valides()
            ->parVille($villeModel->id)
            ->whereNotNull('quartier')
            ->distinct()
            ->pluck('quartier')
            ->sort()
            ->values();

        return view('salons.index', compact(
            'villeModel', 'salons', 'quartiers', 'tri',
            'autoQuartier', 'quartierActif'
        ));
    }

    /*
    |------------------------------------------------------------------
    | Profil d'un salon  GET /salons/{ville}/{slug}
    |------------------------------------------------------------------
    */
    public function show(string $ville, string $slug): View
    {
        $villeModel = Ville::where('nom_ville', 'like', $ville)->firstOrFail();

        $salon = Salon::valides()
            ->parVille($villeModel->id)
            ->with(['ville', 'servicesActifs', 'employesActifs'])
            ->get()
            ->first(fn ($s) => Str::slug($s->nom_salon) === $slug);

        if (! $salon) {
            abort(404);
        }

        $avis = Avis::whereHas('reservation', fn ($q) => $q->where('salon_id', $salon->id)
        )
            ->with('reservation.client')
            ->latest()
            ->limit(10)
            ->get();

        // Calcul dynamique depuis la vraie table avis
        $totalAvis = Avis::whereHas('reservation', fn ($q) => $q->where('salon_id', $salon->id)
        )->count();

        $noteMoy = $totalAvis > 0
            ? round(Avis::whereHas('reservation', fn ($q) => $q->where('salon_id', $salon->id)
            )->avg('note'), 1)
            : 0;

        $noteMoyInt = (int) round($noteMoy);

        $distribution = [];
        for ($i = 5; $i >= 1; $i--) {
            $nb = Avis::whereHas('reservation', fn ($q) => $q->where('salon_id', $salon->id)
            )->where('note', $i)->count();

            $distribution[$i] = [
                'count' => $nb,
                'pct' => $totalAvis > 0 ? round($nb / $totalAvis * 100) : 0,
            ];
        }

        $servicesByCategorie = $salon->servicesActifs
            ->groupBy('categorie')
            ->sortKeys();

        // Réservation terminée du client connecté, sans avis → CTA "Laisser un avis"
        $reservationAEvaluer = null;
        if (Auth::check() && Auth::user()->isClient()) {
            $reservationAEvaluer = Reservation::where('client_id', Auth::id())
                ->where('salon_id', $salon->id)
                ->where(fn ($q) => $q
                    ->where('statut', 'terminee')
                    ->orWhere(fn ($q2) => $q2->where('statut', 'confirmee')->where('date_heure', '<', now()))
                )
                ->doesntHave('avis')
                ->latest('date_heure')
                ->first();
        }

        return view('salons.show', compact(
            'salon', 'villeModel', 'avis',
            'distribution', 'servicesByCategorie', 'reservationAEvaluer',
            'totalAvis', 'noteMoy', 'noteMoyInt'
        ));
    }
}
