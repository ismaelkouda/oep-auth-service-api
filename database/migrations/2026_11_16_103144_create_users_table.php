<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type', 20);
            $table->string('name');
            $table->string('phone', 20)->unique();
            $table->timestampTz('phone_verified_at')->nullable();
            $table->string('email')->nullable()->unique();
            $table->string('password')->nullable();
            $table->foreignUuid('profile_id')->constrained()->restrictOnDelete();
            $table->string('role', 20)->nullable();
            $table->uuid('organization_id')->nullable();
            $table->uuid('territory_id')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestampTz('locked_until')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        DB::statement("ALTER TABLE users ADD CONSTRAINT users_type_check CHECK (type IN ('citizen', 'staff'))");
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IS NULL OR role IN ('supervisor', 'team_lead', 'manager'))");
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_status_check CHECK (status IN ('pending', 'active', 'suspended', 'deactivated'))");
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_staff_check CHECK (type <> 'staff' OR (email IS NOT NULL AND password IS NOT NULL AND role IS NOT NULL AND organization_id IS NOT NULL))");
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_citizen_check CHECK (type <> 'citizen' OR (role IS NULL AND password IS NULL))");
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
