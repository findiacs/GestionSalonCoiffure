<?php

namespace App\Console\Commands;

use App\Models\Avis;
use App\Models\Salon;
use Illuminate\Console\Command;

class RecalcSalonStats extends Command
{
    protected $signature = 'salons:recalc-stats';

    protected $description = 'Recalcule nb_avis et note_moy de chaque salon depuis la vraie table avis';

    public function handle(): void
    {
        $salons = Salon::all();

        foreach ($salons as $salon) {
            $nbAvis = Avis::whereHas('reservation',
                fn ($q) => $q->where('salon_id', $salon->id)
            )->count();

            $noteMoy = $nbAvis > 0
                ? round(Avis::whereHas('reservation',
                    fn ($q) => $q->where('salon_id', $salon->id)
                )->avg('note'), 2)
                : 0;

            $salon->update([
                'nb_avis' => $nbAvis,
                'note_moy' => $noteMoy,
            ]);

            $this->line("  {$salon->nom_salon} → {$nbAvis} avis, note {$noteMoy}");
        }

        $this->info('Stats recalculées pour '.$salons->count().' salons.');
    }
}
