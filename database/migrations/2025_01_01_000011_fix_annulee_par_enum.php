<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Élargit l'enum annulee_par pour inclure 'systeme'
        // (utilisé par NotificationService::annulerReservationsExpirees)
        if (config('database.default') !== 'sqlite') {
            DB::statement("ALTER TABLE reservations MODIFY COLUMN annulee_par ENUM('client','salon','admin','systeme') NULL");
        }
    }

    public function down(): void
    {
        // Remet l'enum original (les lignes 'systeme' seront mises à NULL)
        if (config('database.default') !== 'sqlite') {
            DB::statement("UPDATE reservations SET annulee_par = NULL WHERE annulee_par = 'systeme'");
            DB::statement("ALTER TABLE reservations MODIFY COLUMN annulee_par ENUM('client','salon','admin') NULL");
        }
    }
};
