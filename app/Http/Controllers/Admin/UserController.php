<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Ville;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        Log::info('Admin: liste utilisateurs', ['admin_id' => Auth::id(), 'filters' => $request->only('role', 'verifie', 'q')]);

        $query = User::query();

        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }
        if ($request->filled('verifie')) {
            if ($request->verifie === '1') {
                $query->whereNotNull('email_verifie_le');
            } elseif ($request->verifie === '0') {
                $query->whereNull('email_verifie_le');
            }
        }
        if ($request->filled('q')) {
            $query->where(function ($q) use ($request) {
                $q->where('nom', 'like', '%'.$request->q.'%')
                    ->orWhere('prenom', 'like', '%'.$request->q.'%')
                    ->orWhere('email', 'like', '%'.$request->q.'%');
            });
        }

        $users = $query->latest()->paginate(20)->withQueryString();

        $compteurs = [
            'total' => User::count(),
            'clients' => User::where('role', 'client')->count(),
            'salons' => User::where('role', 'salon')->count(),
            'admins' => User::where('role', 'admin')->count(),
        ];

        return view('admin.users', compact('users', 'compteurs'));
    }

    public function create(): View
    {
        $villes = Ville::orderBy('nom_ville')->get();

        return view('admin.user_form', ['user' => null, 'villes' => $villes]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nom' => ['required', 'string', 'max:80'],
            'prenom' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email', 'unique:users,email'],
            'role' => ['required', 'in:admin,salon,client'],
            'mot_de_passe' => ['required', Password::min(8)],
            'telephone' => ['nullable', 'string', 'max:20'],
            'ville_id' => ['nullable', 'exists:villes,id'],
            'quartier' => ['nullable', 'string', 'max:80'],
        ]);

        $user = User::create([
            'nom' => $data['nom'],
            'prenom' => $data['prenom'],
            'email' => $data['email'],
            'role' => $data['role'],
            'mot_de_passe' => Hash::make($data['mot_de_passe']),
            'telephone' => $data['telephone'] ?? null,
            'ville_id' => $data['ville_id'] ?? null,
            'quartier' => $data['quartier'] ?? null,
            'email_verifie_le' => $request->boolean('email_verifie') ? now() : null,
        ]);

        Log::info('Admin: utilisateur cree', ['admin_id' => Auth::id(), 'new_user_id' => $user->id, 'role' => $user->role]);

        return redirect()->route('admin.users.index')->with('success', 'Utilisateur créé.');
    }

    public function show(int $id): View
    {
        Log::info('Admin: detail utilisateur', ['admin_id' => Auth::id(), 'user_id' => $id]);

        $user = User::with([
            'salon.ville',
            'salon.services',
            'salon.employes',
            'reservations.salon.ville',
            'reservations.service',
            'notifications',
        ])->findOrFail($id);

        $stats = [];

        if ($user->isClient()) {
            $stats = [
                'reservations' => $user->reservations->count(),
                'confirmees' => $user->reservations->where('statut', 'confirmee')->count(),
                'terminees' => $user->reservations->where('statut', 'terminee')->count(),
                'annulees' => $user->reservations->where('statut', 'annulee')->count(),
                'notifications' => $user->notifications->count(),
            ];
        } elseif ($user->isSalon() && $user->salon) {
            $stats = [
                'reservations' => $user->salon->reservations()->count(),
                'services' => $user->salon->services->count(),
                'employes' => $user->salon->employes->count(),
                'avis' => $user->salon->nb_avis,
            ];
        }

        return view('admin.user_detail', compact('user', 'stats'));
    }

    public function edit(int $id): View
    {
        $user = User::findOrFail($id);
        $villes = Ville::orderBy('nom_ville')->get();

        return view('admin.user_form', compact('user', 'villes'));
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $user = User::findOrFail($id);

        $data = $request->validate([
            'nom' => ['required', 'string', 'max:80'],
            'prenom' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email', 'unique:users,email,'.$user->id],
            'role' => ['required', 'in:admin,salon,client'],
            'telephone' => ['nullable', 'string', 'max:20'],
            'ville_id' => ['nullable', 'exists:villes,id'],
            'quartier' => ['nullable', 'string', 'max:80'],
            'mot_de_passe' => ['nullable', Password::min(8)],
        ]);

        $updatable = [
            'nom' => $data['nom'],
            'prenom' => $data['prenom'],
            'email' => $data['email'],
            'role' => $data['role'],
            'telephone' => $data['telephone'] ?? null,
            'ville_id' => $data['ville_id'] ?? null,
            'quartier' => $data['quartier'] ?? null,
        ];

        if (! empty($data['mot_de_passe'])) {
            $updatable['mot_de_passe'] = Hash::make($data['mot_de_passe']);
        }

        $verifie = $request->boolean('email_verifie');
        if ($verifie && ! $user->email_verifie_le) {
            $updatable['email_verifie_le'] = now();
        } elseif (! $verifie && $user->email_verifie_le) {
            $updatable['email_verifie_le'] = null;
        }

        $user->update($updatable);

        Log::info('Admin: utilisateur mis a jour', ['admin_id' => Auth::id(), 'user_id' => $id]);

        return redirect()->route('admin.users.index')->with('success', 'Utilisateur mis à jour.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $user = User::findOrFail($id);
        $user->delete();

        Log::info('Admin: utilisateur supprime', ['admin_id' => Auth::id(), 'user_id' => $id]);

        return redirect()->route('admin.users.index')->with('success', 'Utilisateur supprimé.');
    }
}
