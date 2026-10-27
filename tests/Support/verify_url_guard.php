<?php

declare(strict_types=1);

/**
 * Step 4D.10.2 — DB_URL-aware guard verification harness.
 *
 * Pure logic tests — no Laravel bootstrap, no database connections.
 * Uses synthetic fixtures only. Does not read or print any real secrets.
 */

require __DIR__ . '/../../vendor/autoload.php';

use Tests\Support\TestEnvironmentGuard;

// ---------------------------------------------------------------------------
// Helper: build a realistic config() array the way database.php returns it,
// simulating how Laravel resolves env() calls.
// 'url' represents the resolved DB_URL value.
// 'host' represents the resolved DB_HOST value.
// ---------------------------------------------------------------------------
function pgConn(string $url, string $host, string $db): array {
    return [
        'driver'   => 'pgsql',
        'url'      => $url,
        'host'     => $host,
        'database' => $db,
        'username' => 'testuser',
        'password' => '',
    ];
}

function makeConnections(array $pgsql, array $extra = []): array {
    $base = [
        'sqlite'  => ['driver' => 'sqlite', 'url' => '', 'database' => ':memory:'],
        'mysql'   => ['driver' => 'mysql',  'url' => '', 'host' => '127.0.0.1', 'database' => 'aquatara_test'],
        'mariadb' => ['driver' => 'mariadb','url' => '', 'host' => '127.0.0.1', 'database' => 'aquatara_test'],
        'sqlsrv'  => ['driver' => 'sqlsrv', 'url' => '', 'host' => '127.0.0.1', 'database' => 'aquatara_test'],
        'pgsql'   => $pgsql,
    ];
    return array_merge($base, $extra);
}

// ---------------------------------------------------------------------------
// Test cases
// ---------------------------------------------------------------------------
$testCases = [

    // ---- ORIGINALLY VERIFIED (regression) ----------------------------------
    [
        'id' => 'T01',
        'name' => 'Supabase direct host in host field → REJECT',
        'default' => 'pgsql',
        'connections' => makeConnections(
            pgConn('', 'db.xxxx.supabase.co', 'postgres')
        ),
        'expectException' => true,
        'expectedFragment' => 'Supabase cloud infrastructure',
    ],
    [
        'id' => 'T02',
        'name' => 'Supabase pooler in host field → REJECT',
        'default' => 'pgsql',
        'connections' => makeConnections(
            pgConn('', 'aws-0-ap-southeast-2.pooler.supabase.com', 'postgres')
        ),
        'expectException' => true,
        'expectedFragment' => 'Supabase cloud infrastructure',
    ],
    [
        'id' => 'T03',
        'name' => 'Non-Supabase remote host (192.168.x.x) → REJECT',
        'default' => 'pgsql',
        'connections' => makeConnections(
            pgConn('', '192.168.1.100', 'aquatara_test')
        ),
        'expectException' => true,
        'expectedFragment' => 'not a recognized local test host',
    ],
    [
        'id' => 'T04',
        'name' => 'Local 127.0.0.1, empty URL, aquatara_test → ACCEPT',
        'default' => 'pgsql',
        'connections' => makeConnections(
            pgConn('', '127.0.0.1', 'aquatara_test')
        ),
        'expectException' => false,
    ],
    [
        'id' => 'T05',
        'name' => 'Local localhost, empty URL, dedicated test db → ACCEPT',
        'default' => 'pgsql',
        'connections' => makeConnections(
            pgConn('', 'localhost', 'aquatara_testing_suite')
        ),
        'expectException' => false,
    ],
    [
        'id' => 'T06',
        'name' => 'Local host but system database "postgres" → REJECT',
        'default' => 'pgsql',
        'connections' => makeConnections(
            pgConn('', '127.0.0.1', 'postgres')
        ),
        'expectException' => true,
        'expectedFragment' => 'targets system/shared database',
    ],
    [
        'id' => 'T07',
        'name' => 'Named secondary connection pointing to remote (default is local) → REJECT',
        'default' => 'pgsql',
        'connections' => makeConnections(
            pgConn('', '127.0.0.1', 'aquatara_test'),
            ['secondary_remote' => ['driver' => 'pgsql', 'url' => '', 'host' => 'rds.us-east-1.amazonaws.com', 'database' => 'remotedb']]
        ),
        'expectException' => true,
        'expectedFragment' => 'not a recognized local test host',
    ],
    [
        'id' => 'T08',
        'name' => 'Empty host + empty URL (incomplete config) → REJECT',
        'default' => 'pgsql',
        'connections' => makeConnections(
            pgConn('', '', '')
        ),
        'expectException' => true,
        'expectedFragment' => 'has no host',
    ],

    // ---- NEW: DB_URL SCENARIOS (the focus of Step 4D.10.2) ----------------
    [
        'id' => 'T09',
        'name' => 'DB_URL contains Supabase direct hostname, host=127.0.0.1 → REJECT',
        'note' => 'Critical: URL takes precedence over host in Laravel. Guard must inspect URL.',
        'default' => 'pgsql',
        'connections' => makeConnections(
            pgConn('postgresql://user:pass@db.xxxx.supabase.co/postgres', '127.0.0.1', 'aquatara_test')
        ),
        'expectException' => true,
        'expectedFragment' => 'Supabase cloud infrastructure',
    ],
    [
        'id' => 'T10',
        'name' => 'DB_URL contains Supabase pooler, host=127.0.0.1 → REJECT',
        'note' => 'Pooler URL with seemingly safe host field.',
        'default' => 'pgsql',
        'connections' => makeConnections(
            pgConn('postgresql://user:pass@aws-0-ap-southeast-2.pooler.supabase.com/postgres', '127.0.0.1', 'aquatara_test')
        ),
        'expectException' => true,
        'expectedFragment' => 'Supabase cloud infrastructure',
    ],
    [
        'id' => 'T11',
        'name' => 'DB_URL points to non-Supabase remote (AWS RDS), host=127.0.0.1 → REJECT',
        'note' => 'GAP TEST: host field is local but URL overrides it. Guard must parse URL host.',
        'default' => 'pgsql',
        'connections' => makeConnections(
            pgConn('postgresql://user:pass@mydb.rds.amazonaws.com/aquatara_test', '127.0.0.1', 'aquatara_test')
        ),
        'expectException' => true,
        'expectedFragment' => 'not a recognized local test host',
    ],
    [
        'id' => 'T12',
        'name' => 'DB_URL with non-Supabase remote IP, host=127.0.0.1 → REJECT',
        'note' => 'GAP TEST: remote IP in URL while host field is local.',
        'default' => 'pgsql',
        'connections' => makeConnections(
            pgConn('postgresql://user:pass@10.0.0.50:5432/aquatara_test', '127.0.0.1', 'aquatara_test')
        ),
        'expectException' => true,
        'expectedFragment' => 'not a recognized local test host',
    ],
    [
        'id' => 'T13',
        'name' => 'DB_URL malformed / not a valid URL → REJECT',
        'note' => 'Malformed URL should not silently pass validation.',
        'default' => 'pgsql',
        'connections' => makeConnections(
            pgConn('http:///malformed::url:5432', '127.0.0.1', 'aquatara_test')
        ),
        'expectException' => true,
        'expectedFragment' => 'database URL is malformed',
    ],
    [
        'id' => 'T14',
        'name' => 'DB_URL uses postgres:// scheme with Supabase host → REJECT',
        'note' => 'postgres:// scheme variant (vs postgresql://).',
        'default' => 'pgsql',
        'connections' => makeConnections(
            pgConn('postgres://user:pass@db.xxxx.supabase.co/postgres', '127.0.0.1', 'aquatara_test')
        ),
        'expectException' => true,
        'expectedFragment' => 'Supabase cloud infrastructure',
    ],
    [
        'id' => 'T15',
        'name' => 'DB_URL is empty string, host=127.0.0.1, db=aquatara_test → ACCEPT',
        'note' => 'Empty URL must not cause false rejection. Host field is authoritative when URL is empty.',
        'default' => 'pgsql',
        'connections' => makeConnections(
            pgConn('', '127.0.0.1', 'aquatara_test')
        ),
        'expectException' => false,
    ],
    [
        'id' => 'T16',
        'name' => 'DB_URL points to localhost (valid local URL) → ACCEPT',
        'note' => 'URL explicitly targeting localhost with dedicated test db should be accepted.',
        'default' => 'pgsql',
        'connections' => makeConnections(
            pgConn('postgresql://aquatara_test_user:localpass@127.0.0.1:5432/aquatara_test', '127.0.0.1', 'aquatara_test')
        ),
        'expectException' => false,
    ],
    [
        'id' => 'T17',
        'name' => 'DB_URL targets system database via URL path → REJECT',
        'note' => 'URL pointing to local host but database name is postgres (system db).',
        'default' => 'pgsql',
        'connections' => makeConnections(
            pgConn('postgresql://postgres:pass@127.0.0.1:5432/postgres', '127.0.0.1', 'aquatara_test')
        ),
        'expectException' => true,
        'expectedFragment' => 'system/shared database',
    ],
    [
        'id' => 'T18',
        'name' => 'Connection config entry is not an array (scalar) → REJECT',
        'note' => 'Guard must reject non-array connection entries.',
        'default' => 'pgsql',
        'connections' => [
            'pgsql' => pgConn('', '127.0.0.1', 'aquatara_test'),
            'bad_entry' => 'this-is-a-string-not-an-array',
        ],
        'expectException' => true,
        'expectedFragment' => 'configuration is invalid',
    ],
];

// ---------------------------------------------------------------------------
// Runner
// ---------------------------------------------------------------------------
$results = [];
$allPassed = true;

foreach ($testCases as $tc) {
    $passed = false;
    $detail = '';

    try {
        TestEnvironmentGuard::assertSafeEnvironment($tc['default'], $tc['connections']);
        if (!$tc['expectException']) {
            $passed = true;
            $detail = 'Accepted as expected';
        } else {
            $passed = false;
            $detail = 'FAIL: Expected REJECT but guard ACCEPTED';
        }
    } catch (RuntimeException $e) {
        $msg = $e->getMessage();
        if ($tc['expectException']) {
            $frag = $tc['expectedFragment'] ?? '';
            if ($frag === '' || str_contains($msg, $frag)) {
                $passed = true;
                // Truncate message; do not print secrets
                $detail = 'Rejected: ' . substr(preg_replace('/password=[^@]*@/', 'password=***@', $msg), 0, 120);
            } else {
                $passed = false;
                $detail = 'FAIL: Wrong message. Expected fragment [' . $frag . ']. Got start: ' . substr($msg, 0, 100);
            }
        } else {
            $passed = false;
            $detail = 'FAIL: Expected ACCEPT but guard REJECTED: ' . substr($msg, 0, 120);
        }
    } catch (Throwable $e) {
        $passed = false;
        $detail = 'FAIL: Unexpected exception ' . get_class($e) . ': ' . substr($e->getMessage(), 0, 120);
    }

    if (!$passed) $allPassed = false;

    $results[] = [
        'id'       => $tc['id'],
        'name'     => $tc['name'],
        'note'     => $tc['note'] ?? '',
        'expected' => $tc['expectException'] ? 'REJECT' : 'ACCEPT',
        'result'   => $passed ? 'PASS' : 'FAIL',
        'detail'   => $detail,
    ];
}

echo json_encode(['all_passed' => $allPassed, 'cases' => $results], JSON_PRETTY_PRINT) . PHP_EOL;
exit($allPassed ? 0 : 1);
