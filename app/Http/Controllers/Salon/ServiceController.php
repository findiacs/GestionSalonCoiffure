<?php

namespace App\Http\Controllers\Salon;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ServiceController extends Controller
{
    private function salon()
    {
        return Auth::user()->salon()->firstOrFail();
    }

    /*
    |------------------------------------------------------------------
    | GET /salon/services
    |------------------------------------------------------------------
    */
    public function index(): View
    {
        $salon = $this->salon();
        $services = Service::where('salon_id', $salon->id)
            ->orderBy('categorie')
            ->orderBy('nom_service')
            ->get()
            ->groupBy('categorie');

        $categories = $services->keys();

        return view('salon.services', compact('salon', 'services', 'categories'));
    }

    /*
    |------------------------------------------------------------------
    | GET /salon/services/create
    |------------------------------------------------------------------
    */
    public function create(): View
    {
        $salon = $this->salon();

        return view('salon.services_form', compact('salon'));
    }

    /*
    |------------------------------------------------------------------
    | POST /salon/services
    |------------------------------------------------------------------
    */
    public function store(Request $request): RedirectResponse
    {
        $salon = $this->salon();

        $data = $request->validate([
            'nom_service' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'prix' => ['required', 'numeric', 'min:0', 'max:99999'],
            'duree_minu' => ['required', 'integer', 'min:10', 'max:480'],
            'categorie' => ['required', 'string', 'max:60'],
            'image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
        ], [
            'nom_service.required' => 'Le nom du service est obligatoire.',
            'prix.required' => 'Le prix est obligatoire.',
            'duree_minu.required' => 'La durée est obligatoire.',
            'categorie.required' => 'La catégorie est obligatoire.',
            'image.image' => 'Le fichier doit être une image.',
            'image.mimes' => 'Formats acceptés : JPG, PNG, WEBP.',
            'image.max' => "L'image ne doit pas dépasser 5 Mo.",
        ]);

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('services', 'public');
        } else {
            unset($data['image']);
        }

        $data['salon_id'] = $salon->id;
        $data['actif'] = $request->boolean('actif');

        Service::create($data);

        return redirect()->route('salon.services.index')
            ->with('success', 'Service "'.$data['nom_service'].'" créé avec succès.');
    }

    /*
    |------------------------------------------------------------------
    | GET /salon/services/{id}/edit
    |------------------------------------------------------------------
    */
    public function edit(int $id): View
    {
        $salon = $this->salon();
        $service = Service::where('salon_id', $salon->id)->findOrFail($id);

        return view('salon.services_form', compact('salon', 'service'));
    }

    /*
    |------------------------------------------------------------------
    | PUT /salon/services/{id}
    |------------------------------------------------------------------
    */
    public function update(Request $request, int $id): RedirectResponse
    {
        $salon = $this->salon();
        $service = Service::where('salon_id', $salon->id)->findOrFail($id);

        $data = $request->validate([
            'nom_service' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'prix' => ['required', 'numeric', 'min:0', 'max:99999'],
            'duree_minu' => ['required', 'integer', 'min:10', 'max:480'],
            'categorie' => ['required', 'string', 'max:60'],
            'image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
        ], [
            'image.image' => 'Le fichier doit être une image.',
            'image.mimes' => 'Formats acceptés : JPG, PNG, WEBP.',
            'image.max' => "L'image ne doit pas dépasser 5 Mo.",
        ]);

        if ($request->boolean('image_supprimer') && $service->image) {
            Storage::disk('public')->delete($service->image);
            $data['image'] = null;
        } elseif ($request->hasFile('image')) {
            if ($service->image) {
                Storage::disk('public')->delete($service->image);
            }
            $data['image'] = $request->file('image')->store('services', 'public');
        } else {
            unset($data['image']);
        }

        $data['actif'] = $request->boolean('actif');
        $service->update($data);

        return redirect()->route('salon.services.index')
            ->with('success', 'Service mis à jour avec succès.');
    }

    /*
    |------------------------------------------------------------------
    | DELETE /salon/services/{id}
    |------------------------------------------------------------------
    */
    public function destroy(int $id): RedirectResponse
    {
        $salon = $this->salon();
        $service = Service::where('salon_id', $salon->id)->findOrFail($id);

        // Vérifier qu'aucune réservation future ne dépend de ce service
        $rdvFuturs = $service->reservations()
            ->whereIn('statut', ['en_attente', 'confirmee'])
            ->where('date_heure', '>=', now())
            ->count();

        if ($rdvFuturs > 0) {
            return back()->with('error',
                "Impossible de supprimer : {$rdvFuturs} réservation(s) future(s) utilisent ce service."
            );
        }

        $nom = $service->nom_service;
        if ($service->image) {
            Storage::disk('public')->delete($service->image);
        }
        $service->delete();

        return redirect()->route('salon.services.index')
            ->with('success', "Service \"{$nom}\" supprimé.");
    }

    /*
    |------------------------------------------------------------------
    | POST rapide — activer / désactiver (appelé en AJAX)
    |------------------------------------------------------------------
    */
    public function toggleActif(int $id): JsonResponse
    {
        $salon = $this->salon();
        $service = Service::where('salon_id', $salon->id)->findOrFail($id);
        $service->update(['actif' => ! $service->actif]);

        return response()->json([
            'actif' => $service->actif,
            'message' => $service->actif ? 'Service activé.' : 'Service désactivé.',
        ]);
    }
}
