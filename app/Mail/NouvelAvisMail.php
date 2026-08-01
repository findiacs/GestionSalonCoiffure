<?php

namespace App\Mail;

use App\Models\Avis;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NouvelAvisMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $nomSalon;

    public string $nomClient;

    public int $note;

    public string $etoiles;

    public ?string $commentaire;

    public string $nomService;

    public string $dateRdv;

    public string $urlRepondre;

    public function __construct(Avis $avis)
    {
        $reservation = $avis->reservation;
        $client = $reservation->client;
        $this->nomSalon = $reservation->salon->nom_salon;
        $this->nomClient = trim(($client->prenom ?? 'Client').' '.substr($client->nom ?? '', 0, 1).'.');
        $this->note = (int) $avis->note;
        $this->etoiles = str_repeat('★', $this->note).str_repeat('☆', 5 - $this->note);
        $this->commentaire = $avis->commentaire;
        $this->nomService = $reservation->service->nom_service ?? '—';
        $this->dateRdv = $reservation->date_heure
            ? $reservation->date_heure->translatedFormat('d F Y')
            : '';
        $this->urlRepondre = url('/salon/avis');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '⭐ Nouvel avis ('.$this->note.'/5) sur '.$this->nomSalon,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.nouvel_avis',
        );
    }
}
