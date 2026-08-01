<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Ville extends Model
{
    use HasFactory;

    protected $table = 'villes';

    public $timestamps = false;

    protected $fillable = ['nom_ville', 'code_postal', 'region', 'pays', 'actif'];

    protected $casts = [
        'actif' => 'boolean',
        'cree_le' => 'datetime',
    ];

    // ── Scopes ─────────────────────────────────────────────────────
    public function scopeActives($q)
    {
        return $q->where('actif', true);
    }

    public function scopeAvecSalons($q)
    {
        return $q->whereHas('salons', fn ($s) => $s->where('valide', 1));
    }

    // ── Relations ──────────────────────────────────────────────────
    public function salons(): HasMany
    {
        return $this->hasMany(Salon::class, 'ville_id');
    }

    public function salonsValides(): HasMany
    {
        return $this->hasMany(Salon::class, 'ville_id')->where('valide', 1);
    }

    public function getPhotoUrlAttribute(): string
    {
        $filename = $this->findPhotoFilename();
        if ($filename) {
            return asset('images/'.$filename);
        }

        return asset('images/salon-placeholder.jpg');
    }

    private function findPhotoFilename(): ?string
    {
        $possibleNames = [
            $this->nom_ville,
            Str::title($this->nom_ville),
            Str::slug($this->nom_ville),
            Str::slug($this->nom_ville, '_'),
            Str::replace(' ', '_', $this->nom_ville),
        ];

        $extensions = ['jpg', 'jpeg', 'png', 'webp'];

        foreach ($possibleNames as $name) {
            $name = trim($name);
            if ($name === '') {
                continue;
            }
            foreach ($extensions as $ext) {
                $publicPath = public_path("images/{$name}.{$ext}");
                if (file_exists($publicPath)) {
                    return "{$name}.{$ext}";
                }

                $rootPath = base_path("images/{$name}.{$ext}");
                if (file_exists($rootPath)) {
                    if (! file_exists($publicPath)) {
                        @copy($rootPath, $publicPath);
                    }

                    return "{$name}.{$ext}";
                }
            }
        }

        $rootMatch = $this->findMatchingImageInDirectory(base_path('images'), $this->nom_ville);
        if ($rootMatch) {
            $filename = basename($rootMatch);
            $publicPath = public_path('images/'.$filename);
            if (! file_exists($publicPath)) {
                @copy($rootMatch, $publicPath);
            }

            return $filename;
        }

        return null;
    }

    private function findMatchingImageInDirectory(string $directory, string $nomVille): ?string
    {
        if (! is_dir($directory)) {
            return null;
        }

        $normalizedTarget = $this->normalizeName($nomVille);
        $extensions = ['jpg', 'jpeg', 'png', 'webp'];

        foreach (scandir($directory) as $filename) {
            $path = $directory.DIRECTORY_SEPARATOR.$filename;
            if (! is_file($path)) {
                continue;
            }
            foreach ($extensions as $ext) {
                if (! str_ends_with(strtolower($filename), ".{$ext}")) {
                    continue;
                }
                if ($this->normalizeName(pathinfo($filename, PATHINFO_FILENAME)) === $normalizedTarget) {
                    return $path;
                }
            }
        }

        return null;
    }

    private function normalizeName(string $value): string
    {
        return preg_replace('/[^a-z0-9]/', '', Str::lower(Str::ascii($value)));
    }
}
