<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Str;

class Salon extends Model
{
    use HasFactory;

    protected $table = 'salons';

    protected $fillable = [
        'user_id',
        'ville_id',
        'nom_salon',
        'adresse',
        'quartier',
        'code_postal',
        'telephone',
        'email',
        'horaires',
        'photo',
        'description',
        'rib',
        'note_moy',
        'nb_avis',
        'valide',
        'nb_employes',
        'latitude',
        'longitude',
        'date_valid',
    ];

    protected $casts = [
        'note_moy' => 'decimal:2',
        'valide' => 'integer',
        'date_valid' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
    ];

    /*
    |------------------------------------------------------------------
    | SLUG (URL propre : /salons/rabat/elegance-coiffure)
    |------------------------------------------------------------------
    */

    public function getSlugAttribute(): string
    {
        return Str::slug($this->nom_salon);
    }

    /** Trouver un salon par ville + slug */
    public static function findBySlug(string $villeNom, string $slug): ?self
    {
        return self::whereHas('ville', fn ($q) => $q->where('nom_ville', 'like', $villeNom))
            ->get()
            ->first(fn ($s) => Str::slug($s->nom_salon) === $slug);
    }

    /*
    |------------------------------------------------------------------
    | HELPERS STATUT
    |------------------------------------------------------------------
    */

    public function estValide(): bool
    {
        return $this->valide === 1;
    }

    public function estEnAttente(): bool
    {
        return $this->valide === 0;
    }

    public function estSuspendu(): bool
    {
        return $this->valide === -1;
    }

    public function libelleStatut(): string
    {
        return match ($this->valide) {
            1 => 'Validé',
            0 => 'En attente',
            -1 => 'Suspendu',
            default => 'Inconnu',
        };
    }

    /*
    |------------------------------------------------------------------
    | HELPERS HORAIRES
    |------------------------------------------------------------------
    */

    /** Accesseur : toujours retourner un tableau PHP (gère double-encodage JSON) */
    public function getHorairesAttribute(mixed $value): mixed
    {
        if (is_array($value)) {
            return $value;
        }
        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            // Double-encodé : premier decode donne une string, second donne l'array
            if (is_string($decoded)) {
                $decoded = json_decode($decoded, true);
            }

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    /**
     * Vérifie si le salon est ouvert un jour donné
     * $jour : 'lundi' | 'mardi' | ... | 'dimanche'
     */
    public function estOuvert(string $jour): bool
    {
        $horaires = $this->horaires;
        if (! $horaires || ! isset($horaires[$jour])) {
            return false;
        }

        return ! ($horaires[$jour]['ferme'] ?? true);
    }

    /**
     * Retourne l'heure d'ouverture d'un jour
     * Ex: $salon->heureOuverture('lundi') → '09:00'
     */
    public function heureOuverture(string $jour): ?string
    {
        return $this->horaires[$jour]['debut'] ?? null;
    }

    public function heureFermeture(string $jour): ?string
    {
        return $this->horaires[$jour]['fin'] ?? null;
    }

    /** Vérifie si le salon est actuellement ouvert (jour + heure) */
    public function estOuvertMaintenant(): bool
    {
        $jours = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
        $jourActuel = $jours[now()->dayOfWeek];
        if (! $this->estOuvert($jourActuel)) {
            return false;
        }

        $maintenant = now()->format('H:i');
        $debut = $this->heureOuverture($jourActuel);
        $fin = $this->heureFermeture($jourActuel);

        if (! $debut || ! $fin) {
            return false;
        }

        return $maintenant >= $debut && $maintenant <= $fin;
    }

    /*
    |------------------------------------------------------------------
    | PHOTO
    |------------------------------------------------------------------
    */

    public function getPhotoUrlAttribute(): string
    {
        return $this->resolvePhotoUrl($this->photo, 'images/salon-placeholder.jpg');
    }

    private function resolvePhotoUrl(?string $photo, string $defaultAsset): string
    {
        if ($photo) {
            $storagePath = storage_path('app/public/'.$photo);
            if (file_exists($storagePath)) {
                return asset('storage/'.$photo);
            }

            $publicPath = public_path('images/'.$photo);
            if (file_exists($publicPath)) {
                return asset('images/'.$photo);
            }

            $rootPath = base_path('images/'.$photo);
            if (file_exists($rootPath)) {
                if (! file_exists($publicPath)) {
                    @copy($rootPath, $publicPath);
                }

                return asset('images/'.$photo);
            }
        }

        return asset($defaultAsset);
    }

    /*
    |------------------------------------------------------------------
    | SCOPES
    |------------------------------------------------------------------
    */

    public function scopeValides($query)
    {
        return $query->where('valide', 1);
    }

    public function scopeEnAttente($query)
    {
        return $query->where('valide', 0);
    }

    public function scopeParVille($query, int $villeId)
    {
        return $query->where('ville_id', $villeId);
    }

    public function scopeParQuartier($query, string $quartier)
    {
        return $query->where('quartier', 'like', "%$quartier%");
    }

    public function scopeMieuxNotes($query)
    {
        return $query->orderByDesc('note_moy');
    }

    /*
    |------------------------------------------------------------------
    | RELATIONS
    |------------------------------------------------------------------
    */

    /** Gérant du salon */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Ville du salon */
    public function ville(): BelongsTo
    {
        return $this->belongsTo(Ville::class, 'ville_id');
    }

    /** Services proposés */
    public function services(): HasMany
    {
        return $this->hasMany(Service::class, 'salon_id');
    }

    /** Services actifs uniquement */
    public function servicesActifs(): HasMany
    {
        return $this->hasMany(Service::class, 'salon_id')
            ->where('actif', 1)
            ->orderBy('categorie')
            ->orderBy('prix');
    }

    /** Employés */
    public function employes(): HasMany
    {
        return $this->hasMany(Employe::class, 'salon_id');
    }

    /** Employés actifs */
    public function employesActifs(): HasMany
    {
        return $this->hasMany(Employe::class, 'salon_id')->where('actif', 1);
    }

    /** Toutes les réservations */
    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class, 'salon_id');
    }

    /** Réservations à venir (confirmées ou en attente) */
    public function reservationsAVenir(): HasMany
    {
        return $this->hasMany(Reservation::class, 'salon_id')
            ->whereIn('statut', ['en_attente', 'confirmee'])
            ->where('date_heure', '>=', now())
            ->orderBy('date_heure');
    }

    /** Avis du salon — passent par la table reservations (pas de salon_id direct sur avis) */
    public function avis(): HasManyThrough
    {
        return $this->hasManyThrough(
            Avis::class,
            Reservation::class,
            'salon_id',        // FK sur reservations → salons.id
            'reservation_id',  // FK sur avis → reservations.id
            'id',              // PK salons
            'id'               // PK reservations
        );
    }

    /**
     * Recalcule note_moy et nb_avis depuis la table avis.
     *
     * À appeler après toute opération qui ajoute, supprime ou modifie un avis
     * lié à ce salon (publication client, suppression admin, suppression de
     * réservation cascadée…).
     */
    public function recalculerStatsAvis(): void
    {
        $stats = Avis::whereHas('reservation', fn ($q) => $q->where('salon_id', $this->id))
            ->selectRaw('COUNT(*) AS nb, AVG(note) AS moy')
            ->first();

        $this->update([
            'nb_avis' => (int) ($stats->nb ?? 0),
            'note_moy' => $stats && $stats->nb > 0 ? round((float) $stats->moy, 2) : 0,
        ]);
    }
}
