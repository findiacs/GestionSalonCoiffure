<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Avis;
use App\Models\Reservation;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AvisController extends Controller
{
    public function __construct(private NotificationService $notifService) {}

    /*
    |------------------------------------------------------------------
    | Mes avis publiés  GET /client/avis
    |------------------------------------------------------------------
    */
    public function index(): View
    {
        $avis = Avis::whereHas('reservation', fn ($q) => $q->where('client_id', Auth::id())
        )
            ->with(['reservation.salon.ville', 'reservation.service'])
            ->latest()
            ->paginate(10);

        return view('client.mes_avis', compact('avis'));
    }

    /*
    |------------------------------------------------------------------
    | Formulaire de publication  GET /avis/publier/{reservation}
    |------------------------------------------------------------------
    */
    public function create(int $reservation): View
    {
        $reservation = Reservation::with(['salon.ville', 'service', 'employe'])
            ->where('client_id', Auth::id())
            ->where(fn ($q) => $q
                ->where('statut', 'terminee')
                ->orWhere(fn ($q2) => $q2->where('statut', 'confirmee')->where('date_heure', '<', now()))
            )
            ->doesntHave('avis')
            ->findOrFail($reservation);

        return view('client.avis', compact('reservation'));
    }

    /*
    |------------------------------------------------------------------
    | Enregistrer l'avis  POST /avis
    |------------------------------------------------------------------
    */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'reservation_id' => ['required', 'exists:reservations,id'],
            'note' => ['required', 'integer', 'between:1,5'],
            'commentaire' => ['nullable', 'string', 'max:1000'],
        ], [
            'note.required' => 'Veuillez attribuer une note.',
            'note.between' => 'La note doit être entre 1 et 5.',
        ]);

        // Double vérification sécurité
        $reservation = Reservation::with('salon')
            ->where('client_id', Auth::id())
            ->where(fn ($q) => $q
                ->where('statut', 'terminee')
                ->orWhere(fn ($q2) => $q2->where('statut', 'confirmee')->where('date_heure', '<', now()))
            )
            ->doesntHave('avis')
            ->findOrFail($request->reservation_id);

        // Si la réservation est encore "confirmee" mais la date est passée → marquer terminée
        if ($reservation->statut === 'confirmee') {
            $reservation->update(['statut' => 'terminee']);
        }

        // Créer l'avis
        $avis = Avis::create([
            'reservation_id' => $reservation->id,
            'note' => $request->note,
            'commentaire' => $request->commentaire,
        ]);

        // Recalculer note_moy et nb_avis du salon
        $reservation->salon->recalculerStatsAvis();

        // Notifier le propriétaire du salon (in-app + email)
        $this->notifService->notifierNouvelAvis($avis);

        return redirect()->route('client.dashboard')
            ->with('success', 'Votre avis a été publié. Merci pour votre retour !');
    }
}
