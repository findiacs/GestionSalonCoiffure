<?php

namespace App\Http\Controllers\Salon;

use App\Http\Controllers\Controller;
use App\Models\Avis;
use App\Models\Reservation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        Log::info('Salon: dashboard consulte', ['user_id' => Auth::id()]);

        $salon = Auth::user()->salon()->with(['ville', 'employesActifs'])->firstOrFail();

        Log::debug('Salon: dashboard pour salon', ['salon_id' => $salon->id, 'nom' => $salon->nom_salon]);

        $rdvAujourdhui = Reservation::where('salon_id', $salon->id)
            ->whereDate('date_heure', today())
            ->whereIn('statut', ['confirmee', 'en_attente'])
            ->with(['service', 'employe', 'client'])
            ->orderBy('date_heure')
            ->get();

        $rdvSemaine = Reservation::where('salon_id', $salon->id)
            ->whereBetween('date_heure', [now()->startOfWeek(), now()->endOfWeek()])
            ->whereIn('statut', ['confirmee', 'en_attente', 'terminee'])
            ->count();

        $caSemaine = Reservation::where('reservations.salon_id', $salon->id)
            ->whereBetween('reservations.date_heure', [now()->startOfWeek(), now()->endOfWeek()])
            ->where('reservations.statut', 'terminee')
            ->join('services', 'reservations.service_id', '=', 'services.id')
            ->sum('services.prix');

        $enAttente = Reservation::where('salon_id', $salon->id)
            ->where('statut', 'en_attente')
            ->where('date_heure', '>=', now())
            ->with(['service', 'client'])
            ->orderBy('date_heure')
            ->get();

        $prochains = Reservation::where('salon_id', $salon->id)
            ->whereIn('statut', ['confirmee', 'en_attente'])
            ->whereBetween('date_heure', [now(), now()->addDays(7)])
            ->with(['service', 'employe', 'client'])
            ->orderBy('date_heure')
            ->get();

        $chartData = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $chartData[] = [
                'label' => $date->translatedFormat('D'),
                'value' => Reservation::where('salon_id', $salon->id)
                    ->whereDate('date_heure', $date->toDateString())
                    ->whereIn('statut', ['confirmee', 'terminee'])
                    ->count(),
                'today' => $i === 0,
            ];
        }

        $totalTerminees = Reservation::where('salon_id', $salon->id)
            ->where('statut', 'terminee')->count();

        $topServices = Reservation::where('salon_id', $salon->id)
            ->where('statut', 'terminee')
            ->selectRaw('service_id, count(*) as total')
            ->groupBy('service_id')
            ->with('service')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        $derniersAvis = Avis::whereHas('reservation',
            fn ($q) => $q->where('salon_id', $salon->id)
        )
            ->with('reservation.client')
            ->latest()
            ->limit(3)
            ->get();

        Log::debug('Salon: dashboard chiffres', [
            'salon_id' => $salon->id,
            'rdv_auj' => $rdvAujourdhui->count(),
            'en_attente' => $enAttente->count(),
            'rdv_semaine' => $rdvSemaine,
        ]);

        return view('salon.dashboard', compact(
            'salon', 'rdvAujourdhui', 'rdvSemaine', 'caSemaine',
            'enAttente', 'prochains', 'chartData',
            'topServices', 'totalTerminees', 'derniersAvis'
        ));
    }
}
