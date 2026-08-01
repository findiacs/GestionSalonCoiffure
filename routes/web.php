<?php

use App\Http\Controllers\Admin\AvisController as AdminAvisController;
use App\Http\Controllers\Admin\ContactController as AdminContactController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\SalonController as AdminSalonController;
use App\Http\Controllers\Admin\StatistiqueController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Admin\VilleController as AdminVilleController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\Client\AvisController;
use App\Http\Controllers\Client\ContactController;
use App\Http\Controllers\Client\DashboardController;
use App\Http\Controllers\Client\HomeController;
use App\Http\Controllers\Client\NotificationController;
use App\Http\Controllers\Client\ProfileController;
use App\Http\Controllers\Client\ReservationController;
use App\Http\Controllers\Client\SalonController;
use App\Http\Controllers\Client\VilleController;
use App\Http\Controllers\Salon\AvisController as SalonAvisController;
use App\Http\Controllers\Salon\DashboardController as SalonDashboardController;
use App\Http\Controllers\Salon\DisponibiliteController;
use App\Http\Controllers\Salon\EmployeController;
use App\Http\Controllers\Salon\ProfilSalonController;
use App\Http\Controllers\Salon\ReservationController as SalonReservationController;
use App\Http\Controllers\Salon\ServiceController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/salons/{ville}', [SalonController::class, 'index'])->name('salons.index');
Route::get('/salons/{ville}/{slug}', [SalonController::class, 'show'])->name('salons.show');
Route::get('/villes', [VilleController::class, 'index'])->name('villes.index');
Route::get('/about', [HomeController::class, 'about'])->name('about');
Route::get('/contact', [ContactController::class, 'show'])->name('contact.show');
Route::post('/contact', [ContactController::class, 'send'])->name('contact.send');

// Auth
Route::middleware('guest')->group(function () {
    Route::get('/connexion', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/connexion', [LoginController::class, 'login']);
    Route::get('/inscription', [RegisterController::class, 'showRegistrationForm'])->name('register');
    Route::post('/inscription', [RegisterController::class, 'register']);
    Route::get('/mot-de-passe-oublie', [ForgotPasswordController::class, 'showLinkRequestForm'])->name('password.request');
    Route::post('/mot-de-passe-oublie', [ForgotPasswordController::class, 'sendResetLinkEmail'])->name('password.email');
    Route::get('/reinitialisation/{token}', [ResetPasswordController::class, 'showResetForm'])->name('password.reset');
    Route::post('/reinitialisation', [ResetPasswordController::class, 'reset'])->name('password.update');
});
Route::post('/deconnexion', [LoginController::class, 'logout'])->name('logout')->middleware('auth');

// Google OAuth
Route::middleware('guest')->group(function () {
    Route::get('/auth/google', [GoogleController::class, 'redirect'])->name('auth.google');
    Route::get('/auth/google/callback', [GoogleController::class, 'callback'])->name('auth.google.callback');
});

// Email verification
Route::get('/verification-email', [VerifyEmailController::class, 'notice'])->name('verification.notice')->middleware('auth');
Route::get('/verification-email/{id}/{hash}', [VerifyEmailController::class, 'verify'])->name('verification.verify');
Route::post('/verification-email/renvoyer', [VerifyEmailController::class, 'resend'])->name('verification.send')->middleware(['auth', 'throttle:6,1']);

// Client routes
Route::middleware(['auth', 'verified'])->prefix('client')->name('client.')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/profil', [ProfileController::class, 'edit'])->name('profil.edit');
    Route::put('/profil', [ProfileController::class, 'update'])->name('profil.update');
    Route::put('/profil/mot-de-passe', [ProfileController::class, 'updatePassword'])->name('profil.password');
    Route::get('/reservations', [ReservationController::class, 'index'])->name('reservations.index');
    Route::get('/avis', [AvisController::class, 'index'])->name('avis.index');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{id}/lu', [NotificationController::class, 'marquerLu'])->name('notifications.lu');
    Route::get('/notifications/count', [NotificationController::class, 'count'])->name('notifications.count');
});

// Reservations wizard (auth + verified)
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/reservations/{salon}/step1', [ReservationController::class, 'step1'])->name('reservations.step1');
    Route::get('/reservations/{salon}/step2', [ReservationController::class, 'step2'])->name('reservations.step2');
    Route::get('/reservations/{salon}/step3', [ReservationController::class, 'step3'])->name('reservations.step3');
    Route::post('/reservations/{salon}', [ReservationController::class, 'store'])->name('reservations.store');
    Route::post('/reservations/{salon}/save-step', [ReservationController::class, 'saveStep'])->name('reservations.save-step');
    Route::get('/reservations/{salon}/creneaux/{serviceId}', [ReservationController::class, 'getCreneaux'])->name('reservations.creneaux');
    Route::get('/reservations/{id}/confirmation', [ReservationController::class, 'confirmation'])->name('reservations.confirmation');
    Route::get('/reservations/{id}', [ReservationController::class, 'show'])->name('reservations.show');
    Route::post('/reservations/{id}/annuler', [ReservationController::class, 'annuler'])->name('reservations.annuler');
    Route::get('/avis/create/{reservation}', [AvisController::class, 'create'])->name('avis.create');
    Route::post('/avis', [AvisController::class, 'store'])->name('avis.store');

});

// Salon routes
Route::middleware(['auth', 'role:salon'])->prefix('salon')->name('salon.')->group(function () {
    Route::get('/dashboard', [SalonDashboardController::class, 'index'])->name('dashboard');
    Route::get('/profil', [ProfilSalonController::class, 'edit'])->name('profil.edit');
    Route::put('/profil', [ProfilSalonController::class, 'update'])->name('profil.update');
    Route::get('/services', [ServiceController::class, 'index'])->name('services.index');
    Route::post('/services', [ServiceController::class, 'store'])->name('services.store');
    Route::get('/services/{id}/edit', [ServiceController::class, 'edit'])->name('services.edit');
    Route::put('/services/{id}', [ServiceController::class, 'update'])->name('services.update');
    Route::delete('/services/{id}', [ServiceController::class, 'destroy'])->name('services.destroy');
    Route::patch('/services/{id}/toggle', [ServiceController::class, 'toggleActif'])->name('services.toggle');
    Route::get('/employes', [EmployeController::class, 'index'])->name('employes.index');
    Route::post('/employes', [EmployeController::class, 'store'])->name('employes.store');
    Route::put('/employes/{id}', [EmployeController::class, 'update'])->name('employes.update');
    Route::delete('/employes/{id}', [EmployeController::class, 'destroy'])->name('employes.destroy');
    Route::patch('/employes/{id}/toggle', [EmployeController::class, 'toggleActif'])->name('employes.toggle');
    Route::get('/reservations', [SalonReservationController::class, 'index'])->name('reservations.index');
    Route::get('/reservations/{id}', [SalonReservationController::class, 'show'])->name('reservations.show');
    Route::post('/reservations/{id}/confirmer', [SalonReservationController::class, 'confirmer'])->name('reservations.confirmer');
    Route::post('/reservations/{id}/terminer', [SalonReservationController::class, 'terminer'])->name('reservations.terminer');
    Route::post('/reservations/{id}/annuler', [SalonReservationController::class, 'annuler'])->name('reservations.annuler');
    Route::get('/disponibilites', [DisponibiliteController::class, 'index'])->name('disponibilites.index');
    Route::post('/disponibilites/bloquer', [DisponibiliteController::class, 'bloquer'])->name('disponibilites.bloquer');
    Route::delete('/disponibilites/bloc/{id}', [DisponibiliteController::class, 'debloquer'])->name('disponibilites.debloquer');
    Route::put('/disponibilites/horaires', [DisponibiliteController::class, 'updateHoraires'])->name('disponibilites.horaires');
    Route::put('/disponibilites/employes/{id}/horaires', [DisponibiliteController::class, 'updateEmployeHoraires'])->name('disponibilites.employe.horaires');
    Route::post('/disponibilites/exceptions', [DisponibiliteController::class, 'storeException'])->name('disponibilites.exceptions.store');
    Route::delete('/disponibilites/exceptions/{id}', [DisponibiliteController::class, 'destroyException'])->name('disponibilites.exceptions.destroy');
    Route::get('/avis', [SalonAvisController::class, 'index'])->name('avis.index');
    Route::post('/avis/{id}/repondre', [SalonAvisController::class, 'repondre'])->name('avis.repondre');
    Route::post('/avis/{id}/signaler', [SalonAvisController::class, 'signaler'])->name('avis.signaler');
});

// Admin routes
Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
    Route::get('/salons', [AdminSalonController::class, 'index'])->name('salons.index');
    Route::get('/salons/{id}/edit', [AdminSalonController::class, 'edit'])->name('salons.edit');
    Route::put('/salons/{id}', [AdminSalonController::class, 'update'])->name('salons.update');
    Route::get('/salons/{id}', [AdminSalonController::class, 'show'])->name('salons.show');
    Route::post('/salons/{id}/valider', [AdminSalonController::class, 'valider'])->name('salons.valider');
    Route::post('/salons/{id}/suspendre', [AdminSalonController::class, 'suspendre'])->name('salons.suspendre');
    Route::delete('/salons/{id}', [AdminSalonController::class, 'destroy'])->name('salons.destroy');
    Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
    Route::get('/users/create', [AdminUserController::class, 'create'])->name('users.create');
    Route::get('/users/{id}', [AdminUserController::class, 'show'])->name('users.show');
    Route::post('/users', [AdminUserController::class, 'store'])->name('users.store');
    Route::get('/users/{id}/edit', [AdminUserController::class, 'edit'])->name('users.edit');
    Route::put('/users/{id}', [AdminUserController::class, 'update'])->name('users.update');
    Route::delete('/users/{id}', [AdminUserController::class, 'destroy'])->name('users.destroy');
    Route::get('/villes', [AdminVilleController::class, 'index'])->name('villes.index');
    Route::post('/villes', [AdminVilleController::class, 'store'])->name('villes.store');
    Route::put('/villes/{id}', [AdminVilleController::class, 'update'])->name('villes.update');
    Route::delete('/villes/{id}', [AdminVilleController::class, 'destroy'])->name('villes.destroy');
    Route::get('/avis', [AdminAvisController::class, 'index'])->name('avis.index');
    Route::delete('/avis/{id}', [AdminAvisController::class, 'destroy'])->name('avis.destroy');
    Route::get('/statistiques', [StatistiqueController::class, 'index'])->name('statistiques.index');
    Route::get('/statistiques/export', [StatistiqueController::class, 'export'])->name('statistiques.export');
    Route::get('/contact', [AdminContactController::class, 'index'])->name('contact.index');
    Route::delete('/contact/{id}', [AdminContactController::class, 'destroy'])->name('contact.destroy');
});
