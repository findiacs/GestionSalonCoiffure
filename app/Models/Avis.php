<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

class Avis extends Model
{
    use HasFactory;

    protected $table = 'avis';

    protected $fillable = [
        'reservation_id',
        'note',
        'commentaire',
        'reponse_salon',
    ];

    protected $casts = [
        'note' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // ── Helpers ────────────────────────────────────────────────────

    /** Retourne "★★★★☆" selon la note */
    public function getEtoilesAttribute(): string
    {
        return str_repeat('★', $this->note).str_repeat('☆', 5 - $this->note);
    }

    public function aReponse(): bool
    {
        return ! empty($this->reponse_salon);
    }

    // ── Scopes ─────────────────────────────────────────────────────
    public function scopeParNote($q, int $note)
    {
        return $q->where('note', $note);
    }

    public function scopeSansReponse($q)
    {
        return $q->whereNull('reponse_salon');
    }

    // ── Relations ──────────────────────────────────────────────────
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class, 'reservation_id');
    }

    /** Raccourci vers le client (via réservation) */
    public function client(): HasOneThrough
    {
        return $this->hasOneThrough(
            User::class,
            Reservation::class,
            'id',          // FK sur reservations
            'id',          // FK sur users
            'reservation_id',
            'client_id'
        );
    }
}
