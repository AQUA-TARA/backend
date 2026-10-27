<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the `role` column to the users table.
     *
     * Allowed values : 'user' | 'admin'
     * Default        : 'user'
     *
     * NOTE - `role` is intentionally NOT in User::$fillable.
     * It must only be set by trusted internal code (e.g. CreateAdminCommand),
     * never through user-facing input or mass-assignment.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('user')->after('password');
        });
    }

    /**
     * Reverse this migration only.
     * Does NOT drop the table or touch any other column.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
