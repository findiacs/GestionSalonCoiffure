<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\BienvenueClientMail;
use App\Mail\BienvenueSalonMail;
use App\Models\Salon;
use App\Models\User;
use App\Models\Ville;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function showRegistrationForm(): View
    {
        $villes = Ville::actives()->orderBy('nom_ville')->get();

        return view('auth.inscription', compact('villes'));
    }

    public function register(Request $request): RedirectResponse
    {
        Log::info('Auth: tentative inscription', ['email' => $request->email, 'role' => $request->role]);

        $request->validate([
            'prenom' => ['required', 'string', 'max:80'],
            'nom' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:180', 'unique:users,email'],
            'telephone' => ['nullable', 'string', 'max:20'],
            'mot_de_passe' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'role' => ['required', 'in:client,salon'],
            'cgv' => ['accepted'],
            'nom_salon' => ['nullable', 'required_if:role,salon', 'string', 'max:120'],
            'ville_id' => ['nullable', 'required_if:role,salon', 'exists:villes,id'],
            'adresse' => ['nullable', 'required_if:role,salon', 'string', 'max:255'],
        ], [
            'prenom.required' => 'Le prénom est obligatoire.',
            'prenom.max' => 'Le prénom ne doit pas dépasser 80 caractères.',
            'nom.required' => 'Le nom est obligatoire.',
            'nom.max' => 'Le nom ne doit pas dépasser 80 caractères.',
            'email.required' => 'L\'adresse email est obligatoire.',
            'email.email' => 'L\'adresse email n\'est pas valide.',
            'email.unique' => 'Cette adresse email est déjà utilisée.',
            'mot_de_passe.required' => 'Le mot de passe est obligatoire.',
            'mot_de_passe.confirmed' => 'Les mots de passe ne correspondent pas.',
            'mot_de_passe.min' => 'Le mot de passe doit contenir au moins 8 caractères.',
            'mot_de_passe.letters' => 'Le mot de passe doit contenir au moins une lettre.',
            'mot_de_passe.numbers' => 'Le mot de passe doit contenir au moins un chiffre.',
            'role.required' => 'Veuillez choisir un rôle.',
            'role.in' => 'Le rôle sélectionné est invalide.',
            'cgv.accepted' => 'Vous devez accepter les conditions générales.',
            'nom_salon.required_if' => 'Le nom du salon est obligatoire.',
            'nom_salon.max' => 'Le nom du salon ne doit pas dépasser 120 caractères.',
            'ville_id.required_if' => 'Veuillez sélectionner une ville.',
            'ville_id.exists' => 'La ville sélectionnée est invalide.',
            'adresse.required_if' => 'L\'adresse du salon est obligatoire.',
            'adresse.max' => 'L\'adresse ne doit pas dépasser 255 caractères.',
        ]);

        $user = User::create([
            'prenom' => $request->prenom,
            'nom' => $request->nom,
            'email' => $request->email,
            'telephone' => $request->telephone,
            'mot_de_passe' => Hash::make($request->mot_de_passe),
            'role' => $request->role,
        ]);

        Log::info('Auth: utilisateur cree', ['user_id' => $user->id, 'role' => $user->role]);

        $salon = null;
        if ($request->role === 'salon') {
            $salon = Salon::create([
                'user_id' => $user->id,
                'ville_id' => $request->ville_id,
                'nom_salon' => $request->nom_salon,
                'adresse' => $request->adresse,
                'quartier' => $request->quartier,
                'telephone' => $request->telephone,
                'email' => $request->email,
                'valide' => 0,
            ]);
            Log::info('Auth: salon cree en attente validation', ['salon_id' => $salon->id, 'user_id' => $user->id]);
        }

        try {
            event(new Registered($user));
        } catch (\Throwable $e) {
            Log::error('Erreur envoi email verification', ['user_id' => $user->id, 'message' => $e->getMessage()]);
        }

        $urlVerification = URL::temporarySignedRoute(
            'verification.verify',
            now()->addHours(24),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        try {
            if ($request->role === 'salon' && $salon) {
                Mail::to($user->email)->send(new BienvenueSalonMail(
                    prenom: $user->prenom,
                    nomSalon: $salon->nom_salon,
                    adresse: $salon->adresse,
                    urlVerification: $urlVerification,
                ));
            } else {
                Mail::to($user->email)->send(new BienvenueClientMail(
                    prenom: $user->prenom,
                    email: $user->email,
                    urlVerification: $urlVerification,
                ));
            }
            Log::info('Auth: email bienvenue envoye', ['user_id' => $user->id]);
        } catch (\Throwable $e) {
            Log::error('Erreur email bienvenue', [
                'user_id' => $user->id,
                'message' => $e->getMessage(),
            ]);
        }

        Auth::login($user);

        return redirect()->route('verification.notice')
            ->with('success', 'Compte créé ! Vérifiez votre email pour activer votre compte.');
    }
}
