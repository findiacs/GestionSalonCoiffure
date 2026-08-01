<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disponibilite_exceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salon_id')
                ->constrained('salons')->cascadeOnDelete();
            $table->foreignId('employe_id')
                ->nullable()->constrained('employes')->cascadeOnDelete();
            $table->date('date');
            $table->boolean('ferme')->default(true);
            $table->time('debut')->nullable();
            $table->time('fin')->nullable();
            $table->string('motif', 160)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['salon_id', 'date']);
            $table->index(['employe_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disponibilite_exceptions');
    }
};
