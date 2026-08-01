<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Recalcule nb_avis et note_moy pour chaque salon depuis la vraie table avis
        $salons = DB::table('salons')->get();

        foreach ($salons as $salon) {
            $stats = DB::table('avis')
                ->join('reservations', 'avis.reservation_id', '=', 'reservations.id')
                ->where('reservations.salon_id', $salon->id)
                ->selectRaw('COUNT(*) as nb, AVG(avis.note) as moy')
                ->first();

            DB::table('salons')->where('id', $salon->id)->update([
                'nb_avis' => $stats->nb ?? 0,
                'note_moy' => $stats->nb > 0 ? round($stats->moy, 2) : 0,
            ]);
        }
    }

    public function down(): void {}
};
