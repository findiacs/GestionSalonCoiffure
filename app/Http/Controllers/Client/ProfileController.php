<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Avis;
use App\Models\Ville;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(): View
    {
        $user = Auth::user()->load(['reservations.salon', 'reservations.service']);
        $villes = Ville::actives()->orderBy('nom_ville')->get();

        $stats = [
            'total_rdv' => $user->reservations()->count(),
            'salons_visites' => $user->reservations()->distinct('salon_id')->count('salon_id'),
            'avis_publies' => Avis::whereHas('reservation',
                fn ($q) => $q->where('client_id', $user->id)
            )->count(),
            'membre_depuis' => $user->created_at->translatedFormat('F Y'),
        ];

        return view('client.profil', compact('user', 'stats', 'villes'));
    }

    public function update(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $request->validate([
            'prenom' => ['required', 'string', 'max:80'],
            'nom' => ['required', 'string', 'max:80'],
            'telephone' => ['nullable', 'string', 'max:20'],
            'email' => ['required', 'email', 'max:180', 'unique:users,email,'.$user->id],
            'ville_id' => ['nullable', 'exists:villes,id'],
            'quartier' => ['nullable', 'string', 'max:100'],
        ], [
            'email.unique' => 'Cette adresse email est déjà utilisée.',
        ]);

        $emailChange = $user->email !== $request->email;

        $user->update([
            'prenom' => $request->prenom,
            'nom' => $request->nom,
            'telephone' => $request->telephone,
            'email' => $request->email,
            'ville_id' => $request->ville_id ?: null,
            'quartier' => $request->quartier ?: null,
            'email_verifie_le' => $emailChange ? null : $user->email_verifie_le,
        ]);

        if ($emailChange) {
            $user->sendEmailVerificationNotification();

            return back()->with('info', 'Email mis à jour. Vérifiez votre nouvelle adresse mail.');
        }

        return back()->with('success', 'Profil mis à jour avec succès.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $request->validate([
            'mot_de_passe_actuel' => ['required'],
            'nouveau_mot_de_passe' => ['required', 'confirmed',
                Password::min(8)->letters()->numbers()],
        ], [
            'mot_de_passe_actuel.required' => 'Le mot de passe actuel est obligatoire.',
            'nouveau_mot_de_passe.confirmed' => 'Les mots de passe ne correspondent pas.',
        ]);

        $user = Auth::user();

        if (! Hash::check($request->mot_de_passe_actuel, $user->mot_de_passe)) {
            return back()->withErrors(['mot_de_passe_actuel' => 'Mot de passe actuel incorrect.']);
        }

        $user->update([
            'mot_de_passe' => Hash::make($request->nouveau_mot_de_passe),
        ]);

        return back()->with('success', 'Mot de passe modifié avec succès.');
    }
}
