<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisponibiliteException extends Model
{
    use HasFactory;

    protected $table = 'disponibilite_exceptions';

    public $timestamps = false;

    protected $fillable = [
        'salon_id', 'employe_id', 'date', 'ferme', 'debut', 'fin', 'motif',
    ];

    protected $casts = [
        'date' => 'date',
        'ferme' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function salon(): BelongsTo
    {
        return $this->belongsTo(Salon::class, 'salon_id');
    }

    public function employe(): BelongsTo
    {
        return $this->belongsTo(Employe::class, 'employe_id');
    }

    /** Exception qui s'applique au salon entier (employe_id null) */
    public function scopeSalonEntier($q)
    {
        return $q->whereNull('employe_id');
    }

    public function scopeEmploye($q, int $employeId)
    {
        return $q->where('employe_id', $employeId);
    }
}
