<?php

namespace App\Mail;

use App\Models\Salon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SalonSuspenduMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $prenom;

    public string $nomSalon;

    public string $motif;

    public function __construct(Salon $salon, string $motif)
    {
        $this->prenom = $salon->user->prenom;
        $this->nomSalon = $salon->nom_salon;
        $this->motif = $motif;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '⚠ Votre salon « '.$this->nomSalon.' » a été suspendu',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.salon_suspendu',
        );
    }
}
