<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auth_events', function (Blueprint $table) {
            // Relie un événement d'audit aux lignes de journal de la même requête.
            $table->string('request_id', 64)->nullable();
            $table->index('request_id');
        });
    }

    public function down(): void
    {
        Schema::table('auth_events', function (Blueprint $table) {
            $table->dropIndex(['request_id']);
            $table->dropColumn('request_id');
        });
    }
};
