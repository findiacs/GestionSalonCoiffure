<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Doublons exacts : même salon_id + même nom_service.
        // On garde la ligne la plus ancienne (MIN(id)), on réattribue les réservations
        // qui pointent vers les doublons, puis on supprime les doublons.
        $duplicates = DB::table('services')
            ->select('salon_id', 'nom_service', DB::raw('MIN(id) AS keep_id'), DB::raw('GROUP_CONCAT(id) AS ids'))
            ->groupBy('salon_id', 'nom_service')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $row) {
            $allIds = array_map('intval', explode(',', $row->ids));
            $keepId = (int) $row->keep_id;
            $deleteIds = array_values(array_diff($allIds, [$keepId]));

            if (empty($deleteIds)) {
                continue;
            }

            DB::table('reservations')
                ->whereIn('service_id', $deleteIds)
                ->update(['service_id' => $keepId]);

            DB::table('services')->whereIn('id', $deleteIds)->delete();
        }

        // Variante typographique connue : "Manucure chique" est une redite de
        // "Manucure classique" (même description, même durée). On fusionne.
        $chique = DB::table('services')
            ->where('nom_service', 'Manucure chique')
            ->get();

        foreach ($chique as $svc) {
            $canonical = DB::table('services')
                ->where('salon_id', $svc->salon_id)
                ->where('nom_service', 'Manucure classique')
                ->value('id');

            if ($canonical) {
                DB::table('reservations')
                    ->where('service_id', $svc->id)
                    ->update(['service_id' => $canonical]);
                DB::table('services')->where('id', $svc->id)->delete();
            } else {
                DB::table('services')->where('id', $svc->id)->update([
                    'nom_service' => 'Manucure classique',
                ]);
            }
        }
    }

    public function down(): void {}
};
