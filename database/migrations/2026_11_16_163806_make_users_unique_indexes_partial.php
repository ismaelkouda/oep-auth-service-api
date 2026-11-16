<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_phone_unique');
            $table->dropUnique('users_email_unique');
        });

        // Unicité parmi les comptes non supprimés seulement : un numéro ou un e-mail
        // d'un compte supprimé redevient disponible.
        DB::statement('CREATE UNIQUE INDEX users_phone_unique ON users (phone) WHERE deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX users_email_unique ON users (email) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX users_phone_unique');
        DB::statement('DROP INDEX users_email_unique');

        Schema::table('users', function (Blueprint $table) {
            $table->unique('phone');
            $table->unique('email');
        });
    }
};
