<?php
/**
 * Transaction safety test for AQUA-TARA Step 2A.1
 * Tests that if Ticket creation fails inside a DB::transaction,
 * the Report is also rolled back (no partial record left).
 *
 * Run with: php artisan tinker < tests/transaction_safety.php
 * Or: php tests/transaction_safety.php (with Laravel bootstrap)
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\Report;
use App\Models\Ticket;

$reportCountBefore = Report::count();
$ticketCountBefore = Ticket::count();

echo "=== TRANSACTION SAFETY TEST ===" . PHP_EOL;
echo "Reports before: $reportCountBefore" . PHP_EOL;
echo "Tickets before: $ticketCountBefore" . PHP_EOL;
echo PHP_EOL;

try {
    DB::transaction(function () {
        // Step 1: Create a real report (would succeed normally)
        $report = Report::create([
            'citizen_id'  => null,
            'description' => 'TX safety test - should rollback',
            'latitude'    => -6.9,
            'longitude'   => 107.6,
            'photo_path'  => 'test/phantom.png',
        ]);

        echo "Report created inside TX (id={$report->id}) — will be rolled back if next step fails" . PHP_EOL;

        // Step 2: Force a FK violation — reference a non-existent report_id
        // This simulates a DB constraint failure mid-transaction
        DB::statement("INSERT INTO tickets (report_id, status, created_at, updated_at) VALUES (999999, 'SUBMITTED', NOW(), NOW())");
    });

    echo "UNEXPECTED: Transaction did not throw — test inconclusive" . PHP_EOL;

} catch (Throwable $e) {
    echo "EXCEPTION CAUGHT: " . $e->getMessage() . PHP_EOL;
    echo PHP_EOL;

    $reportCountAfter = Report::count();
    $ticketCountAfter = Ticket::count();

    echo "Reports after rollback: $reportCountAfter" . PHP_EOL;
    echo "Tickets after rollback: $ticketCountAfter" . PHP_EOL;
    echo PHP_EOL;

    $reportRolledBack = ($reportCountAfter === $reportCountBefore);
    $ticketClean      = ($ticketCountAfter === $ticketCountBefore);

    echo "Report rolled back: " . ($reportRolledBack ? "YES (PASS)" : "NO (FAIL)") . PHP_EOL;
    echo "Ticket not created: " . ($ticketClean      ? "YES (PASS)" : "NO (FAIL)") . PHP_EOL;
    echo PHP_EOL;
    echo "TRANSACTION SAFETY: " . ($reportRolledBack && $ticketClean ? "PASS" : "FAIL") . PHP_EOL;
}
