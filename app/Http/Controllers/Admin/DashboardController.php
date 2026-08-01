<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Avis;
use App\Models\Reservation;
use App\Models\Salon;
use App\Models\User;
use App\Models\Ville;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        Log::info('Admin: dashboard consulte', ['admin_id' => Auth::id()]);

        $now = Carbon::now();

        $salonsTotal = Salon::count();
        $salonsValides = Salon::where('valide', 1)->count();
        $salonsAttente = Salon::where('valide', 0)->count();
        $usersTotal = User::count();
        $usersClients = User::where('role', 'client')->count();
        $resaTotal = Reservation::count();
        $resaCeMois = Reservation::whereYear('date_heure', $now->year)
            ->whereMonth('date_heure', $now->month)->count();
        $avisSansReponse = Avis::whereNull('reponse_salon')->count();
        $villesActives = Ville::has('salons')->count();

        $kpi = [
            'salons_total' => $salonsTotal,
            'salons_valides' => $salonsValides,
            'salons_attente' => $salonsAttente,
            'users_total' => $usersTotal,
            'users_clients' => $usersClients,
            'reservations' => $resaTotal,
            'resa_ce_mois' => $resaCeMois,
            'avis_sans_reponse' => $avisSansReponse,
            'villes_actives' => $villesActives,
        ];

        Log::debug('Admin: KPI charges', $kpi);

        $alertes = [];
        if ($salonsAttente > 0) {
            $alertes[] = [
                'type' => 'warning',
                'message' => $salonsAttente.' salon(s) en attente de validation.',
                'route' => route('admin.salons.index').'?statut=attente',
            ];
        }
        if ($avisSansReponse > 0) {
            $alertes[] = [
                'type' => 'info',
                'message' => $avisSansReponse.' avis client(s) sans réponse du salon.',
                'route' => route('admin.avis.index').'?sans_reponse=1',
            ];
        }

        $salonsEnAttente = Salon::with(['ville', 'user'])
            ->where('valide', 0)
            ->latest()
            ->limit(5)
            ->get();

        $derniersUsers = User::latest()->limit(6)->get();

        $topVilles = Ville::select('villes.id', 'villes.nom_ville')
            ->selectRaw('COUNT(reservations.id) as nb_resa')
            ->leftJoin('salons', 'salons.ville_id', '=', 'villes.id')
            ->leftJoin('reservations', 'reservations.salon_id', '=', 'salons.id')
            ->groupBy('villes.id', 'villes.nom_ville')
            ->orderByDesc('nb_resa')
            ->limit(6)
            ->get();

        $chartResa = [];
        for ($i = 5; $i >= 0; $i--) {
            $mois = $now->copy()->subMonths($i);
            $chartResa[] = [
                'label' => $mois->translatedFormat('M'),
                'value' => Reservation::whereYear('date_heure', $mois->year)
                    ->whereMonth('date_heure', $mois->month)
                    ->count(),
            ];
        }

        return view('admin.dashboard', compact(
            'kpi',
            'alertes',
            'salonsEnAttente',
            'derniersUsers',
            'topVilles',
            'chartResa'
        ));
    }
}
