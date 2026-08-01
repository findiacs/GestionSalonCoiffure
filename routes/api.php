<?php

use App\Http\Controllers\Salon\DisponibiliteController;
use App\Models\Employe;
use App\Models\Salon;
use App\Services\GestionnaireDisponibilite;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Salonify
|--------------------------------------------------------------------------
| Préfixe automatique : /api/...
| Pas de session, pas de CSRF — utilisé par le wizard JS (fetch)
*/

/*
|--------------------------------------------------------------------------
| CRÉNEAUX DISPONIBLES
| GET /api/disponibilites?salon_id=1&service_id=2&date=2026-03-19
| GET /api/disponibilites?salon_id=1&service_id=2&date=2026-03-19&employe_id=3
|--------------------------------------------------------------------------
| Retourne un tableau JSON des créneaux du jour :
| [
|   { "heure": "09:00", "datetime": "2026-03-19 09:00:00", "disponible": true },
|   { "heure": "09:30", "disponible": false },
|   ...
| ]
*/
Route::get('/disponibilites', [DisponibiliteController::class, 'creneaux'])
    ->name('api.disponibilites');

/*
|--------------------------------------------------------------------------
| VÉRIFICATION CRÉNEAU (avant soumission du formulaire step 3)
| POST /api/disponibilites/verifier
|--------------------------------------------------------------------------
*/
Route::post('/disponibilites/verifier', function (Request $request) {
    $request->validate([
        'salon_id' => ['required', 'exists:salons,id'],
        'service_id' => ['required', 'exists:services,id'],
        'date_heure' => ['required', 'date', 'after:now'],
        'employe_id' => ['nullable', 'exists:employes,id'],
    ]);

    $salon = Salon::valides()->findOrFail($request->salon_id);
    $service = $salon->servicesActifs()->findOrFail($request->service_id);
    $employe = $request->employe_id
        ? Employe::find($request->employe_id)
        : null;

    $dateHeure = Carbon::parse($request->date_heure);

    $gestionnaire = app(GestionnaireDisponibilite::class);
    $disponible = $gestionnaire->estDisponible($salon, $service, $dateHeure, $employe);

    return response()->json([
        'disponible' => $disponible,
        'message' => $disponible
            ? 'Créneau disponible.'
            : 'Ce créneau n\'est plus disponible.',
    ]);
})->name('api.disponibilites.verifier');
