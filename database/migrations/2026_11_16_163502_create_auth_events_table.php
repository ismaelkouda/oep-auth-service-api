<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auth_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('event', 40);
            // Pas de clé étrangère : l'historique doit survivre à la suppression d'un compte.
            $table->uuid('user_id')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestampTz('created_at');

            $table->index(['user_id', 'created_at']);
            $table->index(['event', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auth_events');
    }
};
