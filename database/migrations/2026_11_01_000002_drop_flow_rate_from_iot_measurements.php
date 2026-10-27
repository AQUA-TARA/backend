<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tidak melakukan apa pun jika tabel atau kolom tidak tersedia.
        if (
            Schema::hasTable('iot_measurements') &&
            Schema::hasColumn('iot_measurements', 'flow_rate')
        ) {
            Schema::table('iot_measurements', function (Blueprint $table) {
                $table->dropColumn('flow_rate');
            });
        }
    }

    public function down(): void
    {
        // Pulihkan kolom hanya jika tabel tersedia dan kolom belum ada.
        if (
            Schema::hasTable('iot_measurements') &&
            !Schema::hasColumn('iot_measurements', 'flow_rate')
        ) {
            Schema::table('iot_measurements', function (Blueprint $table) {
                $table->float('flow_rate')->nullable();
            });
        }
    }
};