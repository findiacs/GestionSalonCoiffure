<?php

namespace App\Http\Controllers\Salon;

use App\Http\Controllers\Controller;
use App\Models\Ville;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ProfilSalonController extends Controller
{
    private function salon()
    {
        return Auth::user()->salon()->firstOrFail();
    }

    /*
    |------------------------------------------------------------------
    | GET /salon/profil
    |------------------------------------------------------------------
    */
    public function edit(): View
    {
        $salon = $this->salon()->load('ville');
        $villes = Ville::actives()->orderBy('nom_ville')->get();

        return view('salon.profil', compact('salon', 'villes'));
    }

    /*
    |------------------------------------------------------------------
    | PUT /salon/profil
    |------------------------------------------------------------------
    */
    public function update(Request $request): RedirectResponse
    {
        $salon = $this->salon();

        $data = $request->validate([
            'nom_salon' => ['required', 'string', 'max:120'],
            'ville_id' => ['required', 'exists:villes,id'],
            'adresse' => ['required', 'string', 'max:255'],
            'quartier' => ['nullable', 'string', 'max:80'],
            'code_postal' => ['nullable', 'string', 'max:10'],
            'telephone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:180'],
            'description' => ['nullable', 'string', 'max:1000'],
            'rib' => ['nullable', 'string', 'max:60'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'photo' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:5120'],
            'horaires' => ['nullable', 'array'],
        ], [
            'nom_salon.required' => 'Le nom du salon est obligatoire.',
            'adresse.required' => 'L\'adresse est obligatoire.',
            'ville_id.exists' => 'Veuillez choisir une ville valide.',
            'photo.max' => 'La photo principale ne doit pas dépasser 5 Mo.',
        ]);

        // Upload nouvelle photo principale.
        // Sans nouveau fichier on retire explicitement la clé pour ne JAMAIS
        // écraser la photo existante, même si la validation la renvoyait.
        if ($request->hasFile('photo')) {
            if ($salon->photo) {
                Storage::disk('public')->delete($salon->photo);
            }
            $data['photo'] = $request->file('photo')->store('salons', 'public');
        } else {
            unset($data['photo']);
        }

        // Construire le JSON horaires
        $data['horaires'] = $this->buildHoraires($request);

        // Désactiver les champs non fournis
        unset($data['horaires_source']); // clé fantôme éventuelle

        $salon->update($data);

        return back()->with('success', 'Profil du salon mis à jour avec succès.');
    }

    /*
    |------------------------------------------------------------------
    | Helper — construire le JSON horaires depuis les champs du form
    |------------------------------------------------------------------
    */
    private function buildHoraires(Request $request): array
    {
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

        return $horaires;
    }
}
