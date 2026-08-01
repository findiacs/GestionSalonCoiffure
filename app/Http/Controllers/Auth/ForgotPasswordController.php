<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class ForgotPasswordController extends Controller
{
    public function showLinkRequestForm(): View
    {
        return view('auth.forgot-password');
    }

    public function sendResetLinkEmail(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ], [
            'email.required' => 'L\'adresse email est obligatoire.',
            'email.email' => 'L\'adresse email n\'est pas valide.',
        ]);

        Log::info('Auth: demande reinitialisation mot de passe', ['email' => $request->email]);

        $status = Password::sendResetLink(
            $request->only('email')
        );

        if ($status === Password::RESET_LINK_SENT) {
            Log::info('Auth: lien reinitialisation envoye', ['email' => $request->email]);

            return back()->with('success',
                'Un email de réinitialisation a été envoyé à '.$request->email.'. '.
                'Le lien est valable 60 minutes.'
            );
        }

        Log::info('Auth: reinitialisation demandee (email non trouve ou limite atteinte)', ['status' => $status]);

        return back()->with('success',
            'Si cet email existe dans notre système, vous recevrez un lien de réinitialisation.'
        );
    }
}
