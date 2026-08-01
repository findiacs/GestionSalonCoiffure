<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class GoogleController extends Controller
{
    public function redirect(): RedirectResponse
    {
        Log::info('Auth: redirection OAuth Google');

        return Socialite::driver('google')->redirect();
    }

    public function callback(): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Throwable $e) {
            Log::warning('Auth: callback Google echoue', ['message' => $e->getMessage()]);

            return redirect()->route('login')
                ->with('error', 'Connexion Google annulée ou expirée. Veuillez réessayer.');
        }

        $user = User::where('email', $googleUser->getEmail())->first();

        if ($user) {
            if (! $user->hasVerifiedEmail()) {
                $user->markEmailAsVerified();
            }

            Auth::login($user, remember: true);

            Log::info('Auth: connexion Google (compte existant)', ['user_id' => $user->id, 'role' => $user->role]);

            return $this->redirectParRole($user);
        }

        $user = User::create([
            'prenom' => $googleUser->user['given_name'] ?? explode(' ', $googleUser->getName())[0],
            'nom' => $googleUser->user['family_name'] ?? (explode(' ', $googleUser->getName())[1] ?? ''),
            'email' => $googleUser->getEmail(),
            'mot_de_passe' => Hash::make(Str::random(32)),
            'role' => 'client',
            'email_verifie_le' => now(),
        ]);

        Auth::login($user, remember: true);

        Log::info('Auth: nouveau compte cree via Google', ['user_id' => $user->id]);

        return redirect()->route('client.dashboard')
            ->with('success', 'Bienvenue sur Salonify, '.$user->prenom.' ! Votre compte a été créé via Google.');
    }

    private function redirectParRole(User $user): RedirectResponse
    {
        return match ($user->role) {
            'admin' => redirect()->route('admin.dashboard')
                ->with('success', 'Connecté en tant qu\'administrateur.'),
            'salon' => redirect()->route('salon.dashboard')
                ->with('success', 'Connecté en tant que gérant — '.($user->salon?->nom_salon ?? '')),
            default => redirect()->route('client.dashboard')
                ->with('success', 'Bienvenue, '.$user->prenom.' !'),
        };
    }
}
