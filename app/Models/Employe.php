<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employe extends Model
{
    use HasFactory;

    protected $table = 'employes';

    public $timestamps = false;

    protected $fillable = [
        'salon_id', 'nom', 'prenom', 'tel', 'email',
        'specialites', 'photo', 'horaires', 'actif',
    ];

    protected $casts = [
        'horaires' => 'array',
        'actif' => 'boolean',
        'cree_le' => 'datetime',
    ];

    /** Normalise specialites qui peut être doublement encodé en JSON depuis le seeder */
    public function getSpecialitesAttribute(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        $decoded = json_decode($value, true);
        if (is_array($decoded)) {
            return $decoded;
        }
        // Double encodage : decode une deuxième fois
        $decoded2 = json_decode($decoded, true);
        if (is_array($decoded2)) {
            return $decoded2;
        }

        return $value ? [$value] : [];
    }

    public function setSpecialitesAttribute(array $value): void
    {
        $this->attributes['specialites'] = json_encode($value);
    }

    // ── Helpers ────────────────────────────────────────────────────
    public function nomComplet(): string
    {
        return trim($this->prenom.' '.$this->nom);
    }

    public function getPhotoUrlAttribute(): string
    {
        if ($this->photo) {
            $storagePath = storage_path('app/public/'.$this->photo);
            if (file_exists($storagePath)) {
                return asset('storage/'.$this->photo);
            }
            $publicPath = public_path('images/'.$this->photo);
            if (file_exists($publicPath)) {
                return asset('images/'.$this->photo);
            }
        }

        // Avatar unique par employé — photos variées de personnes
        // Les IDs pairs → hommes, impairs → femmes (cohérent par employé)
        $id = (int) ($this->id ?? 1);
        $genre = ($id % 2 === 0) ? 'men' : 'women';
        $imgNum = (($id - 1) % 50) + 1; // 1–50 par genre

        return "https://randomuser.me/api/portraits/{$genre}/{$imgNum}.jpg";
    }

    /** Vérifie si l'employé est disponible un jour donné */
    public function estDisponible(string $jour): bool
    {
        $h = $this->horaires;
        if (! $h || ! isset($h[$jour])) {
            return false;
        }

        return ! ($h[$jour]['ferme'] ?? true);
    }

    /**
     * Vérifie si un créneau (Carbon) est dans les horaires de l'employé
     */
    public function accepteCreneau(Carbon $dateHeure): bool
    {
        $jours = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
        $jour = $jours[$dateHeure->dayOfWeek];

        if (! $this->estDisponible($jour)) {
            return false;
        }

        $h = $this->horaires[$jour];
        $heure = $dateHeure->format('H:i');

        return $heure >= ($h['debut'] ?? '00:00') && $heure <= ($h['fin'] ?? '23:59');
    }

    // ── Scopes ─────────────────────────────────────────────────────
    public function scopeActifs($q)
    {
        return $q->where('actif', true);
    }

    // ── Relations ──────────────────────────────────────────────────
    public function salon(): BelongsTo
    {
        return $this->belongsTo(Salon::class, 'salon_id');
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class, 'employe_id');
    }

    /** Réservations futures de cet employé */
    public function reservationsAVenir(): HasMany
    {
        return $this->hasMany(Reservation::class, 'employe_id')
            ->whereIn('statut', ['en_attente', 'confirmee'])
            ->where('date_heure', '>=', now())
            ->orderBy('date_heure');
    }
}
