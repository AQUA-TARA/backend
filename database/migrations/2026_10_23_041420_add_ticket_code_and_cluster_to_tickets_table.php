<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            // Individual Ticket Code (AQT-YYMMDD-NNNN) - unique and indexed for fast lookup
            $table->string('ticket_code', 32)->nullable()->unique()->after('id');
            // Cluster Reference (e.g. AQT-CLUSTER-001) - nullable, indexed
            $table->string('cluster_code', 32)->nullable()->index()->after('ticket_code');
        });

        // Safe backfill for existing rows so legacy tickets obtain a valid unique ticket_code
        // Uses the created_at timestamp of the ticket/report
        $existingTickets = \DB::table('tickets')->orderBy('id', 'asc')->get();
        $dateCounters = [];

        foreach ($existingTickets as $t) {
            $date = $t->created_at ? \Carbon\Carbon::parse($t->created_at) : now();
            $dateKey = $date->format('ymd');

            if (!isset($dateCounters[$dateKey])) {
                $dateCounters[$dateKey] = 1;
            } else {
                $dateCounters[$dateKey]++;
            }

            $seq = str_pad((string) $dateCounters[$dateKey], 4, '0', STR_PAD_LEFT);
            $code = "AQT-{$dateKey}-{$seq}";

            \DB::table('tickets')->where('id', $t->id)->update([
                'ticket_code' => $code,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn(['ticket_code', 'cluster_code']);
        });
    }
};
