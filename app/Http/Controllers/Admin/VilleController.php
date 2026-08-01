<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ville;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class VilleController extends Controller
{
    public function index(): View
    {
        Log::info('Admin: liste villes', ['admin_id' => Auth::id()]);

        $villes = Ville::withCount([
            'salons as salons_count',
            'salons as salons_valides_count' => fn ($q) => $q->where('valide', 1),
        ])->orderBy('nom_ville')->paginate(20);

        return view('admin.villes', compact('villes'));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'nom_ville' => ['required', 'string', 'max:100', 'unique:villes,nom_ville'],
            'code_postal' => ['required', 'string', 'max:10'],
            'region' => ['required', 'string', 'max:100'],
        ]);

        $ville = Ville::create([
            'nom_ville' => $request->nom_ville,
            'code_postal' => $request->code_postal,
            'region' => $request->region,
            'pays' => 'Maroc',
            'actif' => $request->boolean('actif'),
        ]);

        Log::info('Admin: ville creee', ['admin_id' => Auth::id(), 'ville_id' => $ville->id, 'nom' => $ville->nom_ville]);

        return back()->with('success', 'Ville ajoutée.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $ville = Ville::findOrFail($id);

        $request->validate([
            'nom_ville' => ['required', 'string', 'max:100', 'unique:villes,nom_ville,'.$id],
            'code_postal' => ['required', 'string', 'max:10'],
            'region' => ['required', 'string', 'max:100'],
        ]);

        $ville->update([
            'nom_ville' => $request->nom_ville,
            'code_postal' => $request->code_postal,
            'region' => $request->region,
            'actif' => $request->boolean('actif'),
        ]);

        Log::info('Admin: ville mise a jour', ['admin_id' => Auth::id(), 'ville_id' => $id]);

        return back()->with('success', "Ville « {$ville->nom_ville} » mise à jour.");
    }

    public function destroy(int $id): RedirectResponse
    {
        $ville = Ville::findOrFail($id);

        if ($ville->salons()->count() > 0) {
            Log::warning('Admin: tentative suppression ville avec salons', ['admin_id' => Auth::id(), 'ville_id' => $id]);

            return back()->with('error', 'Impossible de supprimer : des salons sont rattachés à cette ville.');
        }

        $ville->delete();

        Log::info('Admin: ville supprimee', ['admin_id' => Auth::id(), 'ville_id' => $id]);

        return back()->with('success', 'Ville supprimée.');
    }
}
