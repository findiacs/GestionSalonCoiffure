<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salon_id')->constrained('salons')->cascadeOnDelete();
            $table->string('nom', 80);
            $table->string('prenom', 80);
            $table->string('tel', 20)->nullable();
            $table->string('email', 180)->nullable();
            $table->json('specialites')->nullable()
                ->comment('["Coupe femme","Coloration","Lissage"]');
            $table->string('photo', 255)->nullable()
                ->comment('Chemin storage/app/public/employes/');
            $table->json('horaires')->nullable()
                ->comment('{"lundi":{"debut":"09:00","fin":"18:00","ferme":false},...}');
            $table->boolean('actif')->default(true);
            $table->timestamp('cree_le')->useCurrent();

            $table->index('actif');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employes');
    }
};
