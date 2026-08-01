<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function showLoginForm(): View
    {
        return view('auth.connexion');
    }

    public function login(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'mot_de_passe' => ['required', 'string'],
        ], [
            'email.required' => 'L\'adresse email est obligatoire.',
            'email.email' => 'L\'adresse email n\'est pas valide.',
            'mot_de_passe.required' => 'Le mot de passe est obligatoire.',
        ]);

        $this->ensureIsNotRateLimited($request);

        Log::info('Auth: tentative de connexion', ['email' => $request->email, 'ip' => $request->ip()]);

        $attempt = Auth::attempt([
            'email' => $request->email,
            'password' => $request->mot_de_passe,
        ], $request->boolean('remember'));

        if (! $attempt) {
            RateLimiter::hit($this->throttleKey($request));

            Log::warning('Auth: echec connexion', ['email' => $request->email, 'ip' => $request->ip()]);

            throw ValidationException::withMessages([
                'email' => 'Email ou mot de passe incorrect.',
            ]);
        }

        RateLimiter::clear($this->throttleKey($request));
        $request->session()->regenerate();

        $user = Auth::user();

        Log::info('Auth: connexion reussie', ['user_id' => $user->id, 'role' => $user->role]);

        if ($user->isClient() && ! $user->hasVerifiedEmail()) {
            return redirect()->route('verification.notice')
                ->with('info', 'Veuillez vérifier votre adresse email avant de continuer.');
        }

        return match ($user->role) {
            'admin' => redirect()->intended(route('admin.dashboard')),
            'salon' => redirect()->intended(route('salon.dashboard')),
            default => redirect()->intended(route('client.dashboard')),
        };
    }

    public function logout(Request $request): RedirectResponse
    {
        $userId = Auth::id();
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        Log::info('Auth: deconnexion', ['user_id' => $userId]);

        return redirect()->route('home')
            ->with('success', 'Vous avez été déconnecté avec succès.');
    }

    protected function ensureIsNotRateLimited(Request $request): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey($request), 5)) {
            return;
        }

        $seconds = RateLimiter::availableIn($this->throttleKey($request));

        Log::warning('Auth: rate limit atteint', ['email' => $request->email, 'ip' => $request->ip()]);

        throw ValidationException::withMessages([
            'email' => "Trop de tentatives. Réessayez dans {$seconds} secondes.",
        ]);
    }

    protected function throttleKey(Request $request): string
    {
        return Str::transliterate(
            Str::lower($request->input('email')).'|'.$request->ip()
        );
    }
}
