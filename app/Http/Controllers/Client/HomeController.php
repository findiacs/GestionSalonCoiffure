<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Models\Salon;
use App\Models\Ville;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(Request $request): View
    {
        Log::info('[Home] Page accueil', [
            'user_id' => Auth::id(),
            'q' => $request->input('q'),
            'ville' => $request->input('ville'),
        ]);

        // Villes actives avec au moins un salon validé + compteur
        $villes = Ville::actives()
            ->withCount(['salonsValides'])
            ->having('salons_valides_count', '>', 0)
            ->orderByDesc('salons_valides_count')
            ->get();

        // Salons en vedette : les 6 mieux notés toutes villes confondues
        $salonsFeatured = Salon::valides()
            ->with('ville')
            ->mieuxNotes()
            ->limit(6)
            ->get();

        // Statistiques globales affichées sur la hero section
        $stats = [
            'salons' => Salon::valides()->count(),
            'villes' => $villes->count(),
            'reservations' => Reservation::count(),
        ];

        // Recherche rapide depuis la barre hero
        $recherche = null;
        if ($request->filled('q') || $request->filled('ville')) {
            $recherche = Salon::valides()
                ->with('ville')
                ->when($request->q, fn ($q, $term) => $q->where('nom_salon', 'like', "%$term%")
                    ->orWhere('quartier', 'like', "%$term%")
                )
                ->when($request->ville, fn ($q, $villeId) => $q->where('ville_id', $villeId)
                )
                ->mieuxNotes()
                ->limit(12)
                ->get();
        }

        return view('public.accueil', compact(
            'villes', 'salonsFeatured', 'stats', 'recherche'
        ));
    }

    public function about(): View
    {
        $stats = [
            'salons' => Salon::valides()->count(),
            'villes' => Ville::actives()->count(),
            'reservations' => Reservation::count(),
        ];

        return view('public.about', compact('stats'));
    }
}
