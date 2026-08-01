<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();              // UUID v4
            $table->foreignId('user_id')
                ->constrained('users')->cascadeOnDelete();
            $table->string('type', 100)
                ->comment('reservation_confirmee, rappel_rdv, salon_valide...');
            $table->json('donnees')->nullable()
                ->comment('Payload JSON variable selon le type');
            $table->dateTime('lu_le')->nullable();
            $table->timestamp('cree_le')->useCurrent();

            $table->index(['user_id', 'lu_le']);
            $table->index('cree_le');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
