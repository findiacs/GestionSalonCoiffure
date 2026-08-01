<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salon_id')->constrained('salons')->cascadeOnDelete();
            $table->string('nom_service', 120);
            $table->text('description')->nullable();
            $table->decimal('prix', 10, 2)->default(0.00);
            $table->unsignedSmallInteger('duree_minu')->default(30)
                ->comment('Durée en minutes');
            $table->string('categorie', 60)->default('Coiffure');
            $table->boolean('actif')->default(true);
            $table->timestamp('cree_le')->useCurrent();
            $table->timestamp('modifie_le')->useCurrent()->useCurrentOnUpdate();

            $table->index('categorie');
            $table->index('actif');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
