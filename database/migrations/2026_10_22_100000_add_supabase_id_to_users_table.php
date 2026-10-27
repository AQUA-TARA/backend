<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the `supabase_id` column to the users table.
     *
     * Holds the unique Supabase Auth User UID (the `sub` claim in Supabase JWTs).
     * Unique and nullable to allow local-only users / seeds while enforcing 1:1 binding for admin auth.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('supabase_id')->nullable()->unique()->after('email');
        });
    }

    /**
     * Reverse this migration only.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('supabase_id');
        });
    }
};
