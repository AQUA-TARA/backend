<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Physical Design: users table = Admin entity.
     * Admins are pre-provisioned staff, so email_verified_at is not required.
     * remember_token is retained for Laravel session guard compatibility.
     *
     * Source: PHYSICAL_DATABASE_DESIGN.md §5 users (Admin)
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();                         // BIGINT UNSIGNED AUTO_INCREMENT PK
            $table->string('name');               // Nama lengkap admin/dispatcher - NOT NULL
            $table->string('email')->unique();    // Email login unik - NOT NULL UNIQUE
            $table->string('password');           // Hash bcrypt/argon2id - NOT NULL
            $table->rememberToken();              // Diperlukan Laravel session guard
            $table->timestamps();                 // created_at, updated_at - NOT NULL (Laravel default)
        });

        // Dibutuhkan Laravel Auth untuk password reset flow
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        // Dibutuhkan Laravel untuk database session driver
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
