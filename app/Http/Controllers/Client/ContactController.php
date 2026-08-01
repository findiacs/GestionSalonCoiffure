<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContactController extends Controller
{
    public function show(): View
    {
        return view('public.contact');
    }

    public function send(Request $request): RedirectResponse
    {
        $request->validate([
            'nom' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:180'],
            'sujet' => ['required', 'string', 'max:180'],
            'message' => ['required', 'string', 'min:20', 'max:2000'],
        ], [
            'nom.required' => 'Votre nom est obligatoire.',
            'email.required' => 'Votre email est obligatoire.',
            'sujet.required' => 'Le sujet est obligatoire.',
            'message.required' => 'Le message est obligatoire.',
            'message.min' => 'Le message doit contenir au moins 20 caractères.',
        ]);

        ContactMessage::create([
            'nom' => $request->nom,
            'email' => $request->email,
            'sujet' => $request->sujet,
            'message' => $request->message,
        ]);

        return back()->with('success',
            'Votre message a bien été envoyé. Nous vous répondrons sous 24h.'
        );
    }
}
