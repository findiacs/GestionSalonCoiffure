<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\SalonSuspenduMail;
use App\Mail\SalonValideMail;
use App\Models\Salon;
use App\Models\Ville;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class SalonController extends Controller
{
    public function index(Request $request): View
    {
        Log::info('Admin: liste salons', ['admin_id' => Auth::id(), 'filters' => $request->only('statut', 'ville_id', 'q')]);

        $query = Salon::with(['user', 'ville']);

        if ($request->filled('statut')) {
            $valide = match ($request->statut) {
                'valide' => 1,
                'attente' => 0,
                'suspendu' => -1,
                default => null,
            };
            if ($valide !== null) {
                $query->where('valide', $valide);
            }
        }
        if ($request->filled('ville_id')) {
            $query->where('ville_id', $request->ville_id);
        }
        if ($request->filled('q')) {
            $query->where('nom_salon', 'like', '%'.$request->q.'%');
        }

        $salons = $query->latest()->paginate(15)->withQueryString();

        $compteurs = [
            'valides' => Salon::where('valide', 1)->count(),
            'attente' => Salon::where('valide', 0)->count(),
            'suspendus' => Salon::where('valide', -1)->count(),
        ];

        $villes = Ville::orderBy('nom_ville')->get();

        return view('admin.salons', compact('salons', 'compteurs', 'villes'));
    }

    public function show(int $id): View
    {
        Log::info('Admin: detail salon', ['admin_id' => Auth::id(), 'salon_id' => $id]);

        $salon = Salon::with([
            'user', 'ville', 'services', 'employes',
            'reservations.client', 'reservations.service',
        ])->findOrFail($id);

        $stats = [
            'reservations' => $salon->reservations->count(),
            'terminées' => $salon->reservations->where('statut', 'terminee')->count(),
            'avis' => $salon->avis->count(),
        ];

        return view('admin.salon_detail', compact('salon', 'stats'));
    }

    public function edit(int $id): View
    {
        $salon = Salon::with(['user', 'ville'])->findOrFail($id);
        $villes = Ville::orderBy('nom_ville')->get();

        return view('admin.salon_form', compact('salon', 'villes'));
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $salon = Salon::findOrFail($id);

        $data = $request->validate([
            'nom_salon' => ['required', 'string', 'max:120'],
            'ville_id' => ['required', 'exists:villes,id'],
            'adresse' => ['required', 'string', 'max:255'],
            'quartier' => ['nullable', 'string', 'max:80'],
            'code_postal' => ['nullable', 'string', 'max:10'],
            'telephone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:180'],
            'description' => ['nullable', 'string', 'max:1000'],
            'rib' => ['nullable', 'string', 'max:60'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'valide' => ['required', 'in:-1,0,1'],
            'photo' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:5120'],
            'horaires' => ['nullable', 'array'],
        ]);

        $data['valide'] = (int) $data['valide'];
        $data['horaires'] = $this->buildHoraires($request);

        // Sans nouveau fichier, on retire la clé : aucun chemin ne doit
        // jamais venir écraser la photo existante du salon.
        if ($request->hasFile('photo')) {
            if ($salon->photo) {
                Storage::disk('public')->delete($salon->photo);
            }
            $data['photo'] = $request->file('photo')->store('salons', 'public');
        } else {
            unset($data['photo']);
        }

        if ($data['valide'] === 1 && ! $salon->date_valid) {
            $data['date_valid'] = now();
        }

        $salon->update($data);

        Log::info('Admin: salon mis a jour', ['admin_id' => Auth::id(), 'salon_id' => $id]);

        return redirect()->route('admin.salons.show', $salon->id)
            ->with('success', "Salon « {$salon->nom_salon} » mis à jour.");
    }

    private function buildHoraires(Request $request): array
    {
        $jours = ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'];
        $horaires = [];

        foreach ($jours as $jour) {
            $ferme = $request->boolean("h_{$jour}_ferme", false);
            $horaires[$jour] = [
                'debut' => $ferme ? null : $request->input("h_{$jour}_debut"),
                'fin' => $ferme ? null : $request->input("h_{$jour}_fin"),
                'ferme' => $ferme,
            ];
        }

        return $horaires;
    }

    public function valider(int $id): RedirectResponse
    {
        $salon = Salon::with(['user', 'ville'])->findOrFail($id);
        $salon->update(['valide' => 1, 'date_valid' => now()]);

        Log::info('Admin: salon valide', ['admin_id' => Auth::id(), 'salon_id' => $id, 'nom' => $salon->nom_salon]);

        app(NotificationService::class)->envoyer($salon->user_id, 'salon_valide', [
            'salon' => $salon->nom_salon,
        ]);

        try {
            Mail::to($salon->user->email)->send(new SalonValideMail($salon));
        } catch (\Throwable $e) {
            Log::error('Erreur email salon_valide', ['salon_id' => $id, 'message' => $e->getMessage()]);
        }

        return back()->with('success', "Salon « {$salon->nom_salon} » validé. Le gérant a été notifié.");
    }

    public function suspendre(Request $request, int $id): RedirectResponse
    {
        $request->validate(['motif' => ['required', 'string', 'max:500']]);

        $salon = Salon::with(['user', 'ville'])->findOrFail($id);
        $salon->update(['valide' => -1]);

        Log::info('Admin: salon suspendu', ['admin_id' => Auth::id(), 'salon_id' => $id, 'nom' => $salon->nom_salon]);

        app(NotificationService::class)->envoyer($salon->user_id, 'salon_suspendu', [
            'salon' => $salon->nom_salon,
        ]);

        try {
            Mail::to($salon->user->email)->send(new SalonSuspenduMail($salon, $request->motif));
        } catch (\Throwable $e) {
            Log::error('Erreur email salon_suspendu', ['salon_id' => $id, 'message' => $e->getMessage()]);
        }

        return back()->with('success', "Salon « {$salon->nom_salon} » suspendu. Le gérant a été notifié.");
    }

    public function destroy(int $id): RedirectResponse
    {
        $salon = Salon::findOrFail($id);
        $nom = $salon->nom_salon;
        $salon->delete();

        Log::info('Admin: salon supprime', ['admin_id' => Auth::id(), 'salon_id' => $id, 'nom' => $nom]);

        return redirect()->route('admin.salons.index')->with('success', "Salon « {$nom} » supprimé.");
    }
}
