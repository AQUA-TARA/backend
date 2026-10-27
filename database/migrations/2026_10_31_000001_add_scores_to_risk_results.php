<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add nullable sensor_score, citizen_score, and optional node_id to risk_results.
 * Keeps existing schema untouched; new columns are nullable to avoid breaking old data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('risk_results', function (Blueprint $table) {
            $table->float('sensor_score')->nullable();
            $table->float('citizen_score')->nullable();
            $table->unsignedBigInteger('node_id')->nullable();
            $table->foreign('node_id')
                  ->references('id')
                  ->on('iot_nodes')
                  ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('risk_results', function (Blueprint $table) {
            $table->dropForeign(['node_id']);
            $table->dropColumn(['sensor_score', 'citizen_score', 'node_id']);
        });
    }
};
