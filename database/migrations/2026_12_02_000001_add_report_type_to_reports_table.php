<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds report_type column to reports table if it doesn't already exist.
     * Allowed types: 'kebocoran', 'air_keruh', 'lainnya'.
     */
    public function up(): void
    {
        if (Schema::hasTable('reports') && !Schema::hasColumn('reports', 'report_type')) {
            Schema::table('reports', function (Blueprint $table) {
                $table->string('report_type')->default('air_keruh');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('reports') && Schema::hasColumn('reports', 'report_type')) {
            Schema::table('reports', function (Blueprint $table) {
                $table->dropColumn('report_type');
            });
        }
    }
};
