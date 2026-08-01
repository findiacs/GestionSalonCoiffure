<?php

namespace App\Mail;

use App\Models\Avis;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReponseAvisMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $prenomClient;

    public string $nomSalon;

    public int $note;

    public string $etoiles;

    public ?string $commentaireClient;

    public string $reponseSalon;

    public string $urlReservations;

    public function __construct(Avis $avis)
    {
        $reservation = $avis->reservation;
        $this->prenomClient = $reservation->client->prenom;
        $this->nomSalon = $reservation->salon->nom_salon;
        $this->note = $avis->note;
        $this->etoiles = str_repeat('★', $avis->note).str_repeat('☆', 5 - $avis->note);
        $this->commentaireClient = $avis->commentaire;
        $this->reponseSalon = $avis->reponse_salon;
        $this->urlReservations = url('/client/reservations');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '💬 '.$this->nomSalon.' a répondu à votre avis',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.reponse_avis',
        );
    }
}
