<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('ville_id')->constrained('villes')->restrictOnDelete();
            $table->string('nom_salon', 120);
            $table->string('adresse', 255);
            $table->string('quartier', 80)->nullable();
            $table->string('code_postal', 10)->nullable();
            $table->string('telephone', 20)->nullable();
            $table->string('email', 180)->nullable();
            $table->json('horaires')->nullable()
                ->comment('{"lundi":{"debut":"09:00","fin":"18:00","ferme":false},...}');
            $table->string('photo', 255)->nullable()
                ->comment('Chemin storage/app/public/salons/');
            $table->text('description')->nullable();
            $table->string('rib', 60)->nullable();
            $table->decimal('note_moy', 3, 2)->default(0.00);
            $table->unsignedInteger('nb_avis')->default(0);
            $table->tinyInteger('valide')->default(0)
                ->comment('0=en_attente, 1=valide, -1=suspendu');
            $table->unsignedTinyInteger('nb_employes')->default(0);
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->dateTime('date_valid')->nullable()
                ->comment('Date de validation admin');
            $table->timestamps();

            $table->index('valide');
            $table->index('quartier');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salons');
    }
};
