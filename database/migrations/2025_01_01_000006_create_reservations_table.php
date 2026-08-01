<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')
                ->constrained('users')->cascadeOnDelete();
            $table->foreignId('salon_id')
                ->constrained('salons')->cascadeOnDelete();
            $table->foreignId('service_id')
                ->constrained('services')->restrictOnDelete();
            $table->foreignId('employe_id')
                ->nullable()->constrained('employes')->nullOnDelete();
            $table->dateTime('date_heure');
            $table->unsignedSmallInteger('duree_minutes')->default(30);
            $table->enum('statut', ['en_attente', 'confirmee', 'annulee', 'terminee'])
                ->default('en_attente');
            $table->text('notes_client')->nullable();
            $table->text('notes_salon')->nullable();
            $table->string('motif_annul', 255)->nullable();
            $table->enum('annulee_par', ['client', 'salon', 'admin'])->nullable();
            $table->dateTime('date_annul')->nullable();
            $table->boolean('rappel_24h')->default(false)
                ->comment('SMS rappel 24h envoyé');
            $table->boolean('rappel_2h')->default(false)
                ->comment('SMS rappel 2h envoyé');
            $table->timestamp('cree_le')->useCurrent();

            // Index pour les requêtes fréquentes
            $table->index('statut');
            $table->index('date_heure');
            $table->index(['salon_id', 'statut']);
            $table->index(['client_id', 'statut']);
            $table->index(['employe_id', 'date_heure']);
            // Index pour les rappels SMS (job planifié)
            $table->index(['statut', 'rappel_24h', 'date_heure']);
            $table->index(['statut', 'rappel_2h',  'date_heure']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
