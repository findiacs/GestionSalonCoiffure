<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── avis ────────────────────────────────────────────────
        Schema::create('avis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')
                ->unique()                          // 1 avis max par réservation
                ->constrained('reservations')->cascadeOnDelete();
            $table->tinyInteger('note')
                ->comment('1 à 5 étoiles');
            $table->text('commentaire')->nullable();
            $table->text('reponse_salon')->nullable();
            $table->timestamps();

            // Contrainte CHECK sur la note (MySQL 8+)
            // $table->check('note BETWEEN 1 AND 5'); // activer si MySQL 8
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('avis');
    }
};
