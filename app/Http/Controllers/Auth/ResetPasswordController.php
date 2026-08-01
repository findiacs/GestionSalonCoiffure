<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

class ResetPasswordController extends Controller
{
    public function showResetForm(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->email,
        ]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'mot_de_passe' => ['required', 'confirmed',
                PasswordRule::min(8)->letters()->numbers()],
            'mot_de_passe_confirmation' => ['required'],
        ], [
            'email.required' => 'L\'adresse email est obligatoire.',
            'mot_de_passe.required' => 'Le nouveau mot de passe est obligatoire.',
            'mot_de_passe.confirmed' => 'Les mots de passe ne correspondent pas.',
            'mot_de_passe.min' => 'Le mot de passe doit contenir au moins 8 caractères.',
        ]);

        Log::info('Auth: tentative reinitialisation mot de passe', ['email' => $request->email]);

        $status = Password::reset(
            [
                'email' => $request->email,
                'password' => $request->mot_de_passe,
                'password_confirmation' => $request->mot_de_passe,
                'token' => $request->token,
            ],
            function ($user, $password) {
                $user->forceFill([
                    'mot_de_passe' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                Log::info('Auth: mot de passe reinitialise', ['user_id' => $user->id]);

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login')
                ->with('success', 'Mot de passe modifié avec succès. Vous pouvez vous connecter.');
        }

        Log::warning('Auth: echec reinitialisation mot de passe', ['email' => $request->email, 'status' => $status]);

        return back()
            ->withInput($request->only('email'))
            ->withErrors(['email' => __($status)]);
    }
}
