<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Avis;
use App\Models\Salon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class AvisController extends Controller
{
    public function index(Request $request): View
    {
        Log::info('Admin: liste avis consultee', ['admin_id' => Auth::id(), 'filters' => $request->only('note', 'sans_reponse', 'salon_id')]);

        $query = Avis::with(['reservation.client', 'reservation.salon', 'reservation.service']);

        if ($request->filled('note')) {
            $query->where('note', (int) $request->note);
        }
        if ($request->filled('sans_reponse')) {
            $query->whereNull('reponse_salon');
        }
        if ($request->filled('salon_id')) {
            $query->whereHas('reservation', fn ($q) => $q->where('salon_id', $request->salon_id));
        }

        $avis = $query->latest()->paginate(20)->withQueryString();

        $totalAvis = Avis::count();
        $noteMoyRaw = Avis::avg('note');
        $sansReponse = Avis::whereNull('reponse_salon')->count();

        $stats = [
            'total' => $totalAvis,
            'note_moy' => $noteMoyRaw ? number_format($noteMoyRaw, 1) : '—',
            'sans_reponse' => $sansReponse,
        ];

        $salons = Salon::orderBy('nom_salon')->get();

        return view('admin.avis', compact('avis', 'stats', 'salons'));
    }

    public function destroy(int $id): RedirectResponse
    {
        $avis = Avis::with('reservation.salon')->findOrFail($id);
        $salon = $avis->reservation?->salon;

        $avis->delete();

        // La note moyenne et le compteur d'avis du salon doivent refléter
        // la suppression — sinon le salon garde une moyenne calculée sur
        // un avis qui n'existe plus en base.
        $salon?->recalculerStatsAvis();

        Log::info('Admin: avis supprime', [
            'admin_id' => Auth::id(),
            'avis_id' => $id,
            'salon_id' => $salon?->id,
        ]);

        return back()->with('success', 'Avis supprimé.');
    }
}
