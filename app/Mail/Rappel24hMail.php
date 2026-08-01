<?php

namespace App\Mail;

use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class Rappel24hMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $prenom;

    public string $nomSalon;

    public string $adresseSalon;

    public string $nomService;

    public string $date;

    public string $heure;

    public ?string $nomEmploye;

    public string $urlReservation;

    public function __construct(Reservation $reservation)
    {
        $this->prenom = $reservation->client->prenom;
        $this->nomSalon = $reservation->salon->nom_salon;
        $this->adresseSalon = $reservation->salon->adresse.', '.$reservation->salon->ville->nom_ville;
        $this->nomService = $reservation->service->nom_service;
        $this->date = $reservation->date_heure->translatedFormat('l d F Y');
        $this->heure = $reservation->date_heure->format('H:i');
        $this->nomEmploye = $reservation->employe?->nomComplet();
        $this->urlReservation = url('/client/reservations/'.$reservation->id);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '⏰ Rappel – Votre RDV chez '.$this->nomSalon.' est demain',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.rappel_24h',
        );
    }
}
