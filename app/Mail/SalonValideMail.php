<?php

namespace App\Mail;

use App\Models\Salon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SalonValideMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $prenom;

    public string $nomSalon;

    public string $adresse;

    public string $dateValidation;

    public string $urlDashboard;

    public function __construct(Salon $salon)
    {
        $this->prenom = $salon->user->prenom;
        $this->nomSalon = $salon->nom_salon;
        $this->adresse = $salon->adresse.', '.$salon->ville->nom_ville;
        $this->dateValidation = now()->translatedFormat('d F Y à H:i');
        $this->urlDashboard = url('/salon/dashboard');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '⭐ Votre salon « '.$this->nomSalon.' » est maintenant en ligne !',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.salon_valide',
        );
    }
}
