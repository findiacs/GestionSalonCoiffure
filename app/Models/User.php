<?php

namespace App\Models;

use App\Notifications\VerifyEmailNotification;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable;

    protected $table = 'users';

    protected $fillable = [
        'nom',
        'prenom',
        'email',
        'mot_de_passe',
        'telephone',
        'role',
        'email_verifie_le',
        'ville_id',
        'quartier',
    ];

    protected $hidden = [
        'mot_de_passe',
        'remember_token',
    ];

    protected $casts = [
        'email_verifie_le' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Laravel attend 'password' par convention — on mappe
    public function getAuthPassword(): string
    {
        return $this->mot_de_passe;
    }

    // Champ email_verified_at attendu par MustVerifyEmail
    public function getEmailVerifiedAtAttribute(): mixed
    {
        return $this->email_verifie_le;
    }

    public function setEmailVerifiedAtAttribute(mixed $value): void
    {
        $this->attributes['email_verifie_le'] = $value;
    }

    /*
    |------------------------------------------------------------------
    | HELPERS RÔLE
    |------------------------------------------------------------------
    */

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isSalon(): bool
    {
        return $this->role === 'salon';
    }

    public function isClient(): bool
    {
        return $this->role === 'client';
    }

    public function nomComplet(): string
    {
        return trim($this->prenom.' '.$this->nom);
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification);
    }

    // Vérification email — on écrit directement sur la vraie colonne
    // pour éviter tout aléa du mutateur email_verified_at.
    public function hasVerifiedEmail(): bool
    {
        return ! is_null($this->email_verifie_le);
    }

    public function markEmailAsVerified(): bool
    {
        return $this->forceFill([
            'email_verifie_le' => $this->freshTimestamp(),
        ])->save();
    }

    public function getEmailForVerification(): string
    {
        return $this->email;
    }

    /*
    |------------------------------------------------------------------
    | RELATIONS
    |------------------------------------------------------------------
    */

    /** Ville du client */
    public function ville(): BelongsTo
    {
        return $this->belongsTo(Ville::class, 'ville_id');
    }

    /** Salons gérés par ce user (role=salon) */
    public function salons(): HasMany
    {
        return $this->hasMany(Salon::class, 'user_id');
    }

    /** Premier salon (raccourci pratique pour les gérants) */
    public function salon(): HasOne
    {
        return $this->hasOne(Salon::class, 'user_id');
    }

    /** Réservations passées en tant que client */
    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class, 'client_id');
    }

    /** Notifications de l'utilisateur */
    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'user_id');
    }

    /** Notifications non lues */
    public function notificationsNonLues(): HasMany
    {
        return $this->hasMany(Notification::class, 'user_id')
            ->whereNull('lu_le')
            ->latest('cree_le');
    }
}
