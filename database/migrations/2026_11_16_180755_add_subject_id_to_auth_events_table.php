<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auth_events', function (Blueprint $table) {
            // Cible d'une action d'administration (user_id reste l'auteur de l'action).
            $table->uuid('subject_id')->nullable();
            $table->index(['subject_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('auth_events', function (Blueprint $table) {
            $table->dropIndex(['subject_id', 'created_at']);
            $table->dropColumn('subject_id');
        });
    }
};
