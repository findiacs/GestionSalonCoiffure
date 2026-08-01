<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Avis;
use App\Models\Reservation;
use App\Models\Salon;
use App\Models\User;
use App\Models\Ville;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class StatistiqueController extends Controller
{
    public function index(Request $request): View
    {
        $debut = $request->filled('debut')
            ? Carbon::parse($request->debut)->startOfDay()
            : now()->subDays(30)->startOfDay();
        $fin = $request->filled('fin')
            ? Carbon::parse($request->fin)->endOfDay()
            : now()->endOfDay();

        Log::info('Admin: statistiques consultees', [
            'admin_id' => Auth::id(),
            'debut' => $debut->toDateString(),
            'fin' => $fin->toDateString(),
        ]);

        $totalResa = Reservation::whereBetween('date_heure', [$debut, $fin])->count();
        $annulees = Reservation::whereBetween('date_heure', [$debut, $fin])
            ->where('statut', 'annulee')->count();

        $caTotal = Reservation::whereBetween('reservations.date_heure', [$debut, $fin])
            ->whereIn('reservations.statut', ['confirmee', 'terminee'])
            ->join('services', 'services.id', '=', 'reservations.service_id')
            ->sum('services.prix');

        $inscriptions = User::where('role', 'client')
            ->whereBetween('created_at', [$debut, $fin])
            ->count();

        $tauxAnnul = $totalResa > 0 ? round($annulees / $totalResa * 100, 1) : 0;

        $noteMoyRaw = Avis::whereBetween('created_at', [$debut, $fin])->avg('note');
        $noteMoy = $noteMoyRaw ? number_format((float) $noteMoyRaw, 1) : '—';

        $kpi = [
            'total_resa' => $totalResa,
            'ca_total' => $caTotal,
            'inscriptions' => $inscriptions,
            'taux_annul' => $tauxAnnul,
            'note_moy' => $noteMoy,
            'note_moy_raw' => $noteMoyRaw ? (float) $noteMoyRaw : 0.0,
        ];

        Log::debug('Admin: KPI statistiques', $kpi);

        $resaParMois = [];
        $cursor = $debut->copy()->startOfMonth();
        while ($cursor->lte($fin)) {
            $count = Reservation::whereYear('date_heure', $cursor->year)
                ->whereMonth('date_heure', $cursor->month)
                ->count();
            $ca = Reservation::whereYear('reservations.date_heure', $cursor->year)
                ->whereMonth('reservations.date_heure', $cursor->month)
                ->whereIn('reservations.statut', ['confirmee', 'terminee'])
                ->join('services', 'services.id', '=', 'reservations.service_id')
                ->sum('services.prix');
            $resaParMois[] = [
                'label' => $cursor->translatedFormat('M Y'),
                'value' => $count,
                'ca' => $ca,
            ];
            $cursor->addMonth();
        }

        $totalAvis = Avis::count() ?: 1;
        $distNotes = [];
        for ($i = 1; $i <= 5; $i++) {
            $count = Avis::where('note', $i)->count();
            $distNotes[$i] = [
                'count' => $count,
                'pct' => round($count / $totalAvis * 100),
            ];
        }

        $topSalons = Salon::select('salons.id', 'salons.nom_salon')
            ->selectRaw('COUNT(reservations.id) as nb_resa')
            ->leftJoin('reservations', function ($j) use ($debut, $fin) {
                $j->on('reservations.salon_id', '=', 'salons.id')
                    ->whereBetween('reservations.date_heure', [$debut, $fin]);
            })
            ->groupBy('salons.id', 'salons.nom_salon')
            ->orderByDesc('nb_resa')
            ->limit(8)
            ->get();

        $parCategorie = Reservation::whereBetween('reservations.date_heure', [$debut, $fin])
            ->join('services', 'services.id', '=', 'reservations.service_id')
            ->selectRaw('services.categorie, COUNT(reservations.id) as total')
            ->groupBy('services.categorie')
            ->orderByDesc('total')
            ->get();

        $parVille = Ville::select('villes.id', 'villes.nom_ville')
            ->selectRaw('COUNT(reservations.id) as nb_resa')
            ->leftJoin('salons', 'salons.ville_id', '=', 'villes.id')
            ->leftJoin('reservations', function ($j) use ($debut, $fin) {
                $j->on('reservations.salon_id', '=', 'salons.id')
                    ->whereBetween('reservations.date_heure', [$debut, $fin]);
            })
            ->groupBy('villes.id', 'villes.nom_ville')
            ->orderByDesc('nb_resa')
            ->limit(10)
            ->get();

        return view('admin.statistiques', compact(
            'debut', 'fin', 'kpi', 'resaParMois',
            'distNotes', 'topSalons', 'parCategorie', 'parVille'
        ));
    }

    public function export(Request $request)
    {
        $debut = $request->filled('debut') ? Carbon::parse($request->debut)->startOfDay() : now()->subDays(30)->startOfDay();
        $fin = $request->filled('fin') ? Carbon::parse($request->fin)->endOfDay() : now()->endOfDay();

        Log::info('Admin: export CSV reservations', [
            'admin_id' => Auth::id(),
            'debut' => $debut->toDateString(),
            'fin' => $fin->toDateString(),
        ]);

        $reservations = Reservation::with(['client', 'salon.ville', 'service'])
            ->whereBetween('date_heure', [$debut, $fin])
            ->orderByDesc('date_heure')
            ->get();

        Log::info('Admin: export CSV - nb reservations', ['count' => $reservations->count()]);

        $bom = "\xEF\xBB\xBF";

        $rows = [];
        $rows[] = ['ID', 'Client', 'Email', 'Salon', 'Ville', 'Service', 'Catégorie', 'Date', 'Heure', 'Durée (min)', 'Prix (MAD)', 'Statut'];

        foreach ($reservations as $r) {
            $rows[] = [
                $r->id,
                $r->client?->nomComplet() ?? '',
                $r->client?->email ?? '',
                $r->salon?->nom_salon ?? '',
                $r->salon?->ville?->nom_ville ?? '',
                $r->service?->nom_service ?? '',
                $r->service?->categorie ?? '',
                $r->date_heure->format('d/m/Y'),
                $r->date_heure->format('H:i'),
                $r->service?->duree_minu ?? '',
                $r->service?->prix ?? 0,
                $r->statut,
            ];
        }

        $csv = $bom;
        foreach ($rows as $row) {
            $csv .= implode(';', array_map(
                fn ($cell) => '"'.str_replace('"', '""', (string) $cell).'"',
                $row
            ))."\r\n";
        }

        $filename = 'salonify-reservations-'.$debut->format('Y-m-d').'-au-'.$fin->format('Y-m-d').'.csv';

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ]);
    }
}
