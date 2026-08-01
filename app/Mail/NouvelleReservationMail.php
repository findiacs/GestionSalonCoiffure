<?php

namespace App\Mail;

use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NouvelleReservationMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $nomSalon;

    public string $nomClient;

    public string $telephoneClient;

    public string $nomService;

    public string $date;

    public string $heure;

    public string $duree;

    public ?string $notesClient;

    public string $urlReservation;

    public function __construct(Reservation $reservation)
    {
        $this->nomSalon = $reservation->salon->nom_salon;
        $this->nomClient = $reservation->client->nomComplet();
        $this->telephoneClient = $reservation->client->telephone ?? '';
        $this->nomService = $reservation->service->nom_service;
        $this->date = $reservation->date_heure->translatedFormat('l d F Y');
        $this->heure = $reservation->date_heure->format('H:i');
        $this->duree = $reservation->service->duree_formatee ?? ($reservation->duree_minutes.' min');
        $this->notesClient = $reservation->notes_client;
        $this->urlReservation = url('/salon/reservations/'.$reservation->id);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '📅 Nouvelle réservation – '.$this->nomClient,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.nouvelle_reservation',
        );
    }
}
