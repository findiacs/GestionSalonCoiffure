<?php

namespace App\Mail;

use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReservationAnnuleeMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $prenom;

    public string $nomSalon;

    public string $nomService;

    public string $date;

    public string $heure;

    public string $annuleePar;

    public ?string $motif;

    public string $urlSalons;

    public function __construct(Reservation $reservation)
    {
        $this->prenom = $reservation->client->prenom;
        $this->nomSalon = $reservation->salon->nom_salon;
        $this->nomService = $reservation->service->nom_service;
        $this->date = $reservation->date_heure->translatedFormat('l d F Y');
        $this->heure = $reservation->date_heure->format('H:i');
        $this->annuleePar = $reservation->annulee_par ?? 'client';
        $this->motif = $reservation->motif_annul;
        $this->urlSalons = url('/villes');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Votre réservation chez '.$this->nomSalon.' a été annulée',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.reservation_annulee',
        );
    }
}
