<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Notification extends Model
{
    use HasFactory;

    protected $table = 'notifications';

    public $timestamps = false;

    // Clé primaire UUID (CHAR 36)
    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'user_id',
        'type',
        'donnees',
        'lu_le',
    ];

    protected $casts = [
        'donnees' => 'array',   // JSON → array automatiquement
        'lu_le' => 'datetime',
        'cree_le' => 'datetime',
    ];

    // Générer un UUID automatiquement à la création
    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (self $model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    // ── Helpers ────────────────────────────────────────────────────
    public function estLue(): bool
    {
        return ! is_null($this->lu_le);
    }

    public function estNonLue(): bool
    {
        return is_null($this->lu_le);
    }

    public function marquerLue(): void
    {
        $this->update(['lu_le' => now()]);
    }

    // ── Types de notifications disponibles ─────────────────────────
    const TYPES = [
        'reservation_confirmee',
        'reservation_annulee',
        'rappel_rdv_24h',
        'rappel_rdv_2h',
        'avis_publie',
        'salon_valide',
        'salon_suspendu',
    ];

    // ── Scopes ─────────────────────────────────────────────────────
    public function scopeNonLues($q)
    {
        return $q->whereNull('lu_le');
    }

    public function scopeParType($q, string $type)
    {
        return $q->where('type', $type);
    }

    // ── Relations ──────────────────────────────────────────────────
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
