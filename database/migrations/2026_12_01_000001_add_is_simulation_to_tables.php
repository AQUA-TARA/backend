<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = [
            'tickets',
            'reports',
            'risk_results',
            'iot_nodes',
            'iot_measurements',
            'clusters',
            'ml_analyses',
        ];

        foreach ($tables as $tableName) {
            if (!Schema::hasColumn($tableName, 'is_simulation')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->boolean('is_simulation')->default(false);
                });
            }
        }
    }

    public function down(): void
    {
        $tables = [
            'tickets',
            'reports',
            'risk_results',
            'iot_nodes',
            'iot_measurements',
            'clusters',
            'ml_analyses',
        ];

        foreach ($tables as $tableName) {
            if (Schema::hasColumn($tableName, 'is_simulation')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropColumn('is_simulation');
                });
            }
        }
    }
};