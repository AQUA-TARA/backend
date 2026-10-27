<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Tests\Support\TestEnvironmentGuard;

/**
 * Pure, database-free verification harness for TestEnvironmentGuard.
 * Tests all 8 required cases + edge cases without touching Supabase or booting Laravel.
 */

$testCases = [
    [
        'name' => '1. Host Supabase langsung (must REJECT)',
        'default' => 'pgsql',
        'connections' => [
            'pgsql' => [
                'driver' => 'pgsql',
                'host' => 'db.xxxx.supabase.co',
                'database' => 'postgres',
            ],
        ],
        'expectException' => true,
        'expectedMessage' => 'Supabase cloud infrastructure',
    ],
    [
        'name' => '2. Host Supabase pooler (must REJECT)',
        'default' => 'pgsql',
        'connections' => [
            'pgsql' => [
                'driver' => 'pgsql',
                'host' => 'aws-0-ap-southeast-2.pooler.supabase.com',
                'database' => 'postgres',
            ],
        ],
        'expectException' => true,
        'expectedMessage' => 'Supabase cloud infrastructure',
    ],
    [
        'name' => '3. DB_URL Supabase direct host (must REJECT)',
        'default' => 'pgsql',
        'connections' => [
            'pgsql' => [
                'driver' => 'pgsql',
                'host' => '127.0.0.1',
                'database' => 'aquatara_test',
                'url' => 'postgresql://postgres.projectref:secret@db.projectref.supabase.co:5432/postgres',
            ],
        ],
        'expectException' => true,
        'expectedMessage' => 'Supabase cloud infrastructure',
    ],
    [
        'name' => '4. DB_URL Supabase pooler (must REJECT)',
        'default' => 'pgsql',
        'connections' => [
            'pgsql' => [
                'driver' => 'pgsql',
                'host' => '127.0.0.1',
                'database' => 'aquatara_test',
                'url' => 'postgresql://postgres.projectref:secret@aws-0-ap-southeast-2.pooler.supabase.com:6543/postgres',
            ],
        ],
        'expectException' => true,
        'expectedMessage' => 'Supabase cloud infrastructure',
    ],
    [
        'name' => '5. DB_HOST localhost + DB_URL Supabase (must REJECT)',
        'default' => 'pgsql',
        'connections' => [
            'pgsql' => [
                'driver' => 'pgsql',
                'host' => 'localhost',
                'database' => 'aquatara_test',
                'url' => 'postgresql://user:secret@db.projectref.supabase.co:5432/postgres',
            ],
        ],
        'expectException' => true,
        'expectedMessage' => 'Supabase cloud infrastructure',
    ],
    [
        'name' => '6. DB_URL PostgreSQL remote non-Supabase (must REJECT)',
        'default' => 'pgsql',
        'connections' => [
            'pgsql' => [
                'driver' => 'pgsql',
                'host' => '127.0.0.1',
                'database' => 'aquatara_test',
                'url' => 'postgresql://user:secret@192.168.1.100:5432/aquatara_test',
            ],
        ],
        'expectException' => true,
        'expectedMessage' => 'not a recognized local test host',
    ],
    [
        'name' => '7. DB_URL PostgreSQL remote hostname non-Supabase (must REJECT)',
        'default' => 'pgsql',
        'connections' => [
            'pgsql' => [
                'driver' => 'pgsql',
                'host' => '127.0.0.1',
                'database' => 'aquatara_test',
                'url' => 'postgresql://user:secret@remote-postgres.rds.amazonaws.com:5432/aquatara_test',
            ],
        ],
        'expectException' => true,
        'expectedMessage' => 'not a recognized local test host',
    ],
    [
        'name' => '8. DB_URL malformed (must REJECT)',
        'default' => 'pgsql',
        'connections' => [
            'pgsql' => [
                'driver' => 'pgsql',
                'host' => '127.0.0.1',
                'database' => 'aquatara_test',
                'url' => 'http:///malformed::url:5432',
            ],
        ],
        'expectException' => true,
        'expectedMessage' => 'database URL is malformed',
    ],
    [
        'name' => '9. DB_URL without host for network driver (must REJECT)',
        'default' => 'pgsql',
        'connections' => [
            'pgsql' => [
                'driver' => 'pgsql',
                'host' => '127.0.0.1',
                'database' => 'aquatara_test',
                'url' => 'postgresql:/aquatara_test',
            ],
        ],
        'expectException' => true,
        'expectedMessage' => 'URL has no host component defined',
    ],
    [
        'name' => '9b. DB_URL with invalid scheme/slashes structure (must REJECT)',
        'default' => 'pgsql',
        'connections' => [
            'pgsql' => [
                'driver' => 'pgsql',
                'host' => '127.0.0.1',
                'database' => 'aquatara_test',
                'url' => 'postgresql:///aquatara_test',
            ],
        ],
        'expectException' => true,
        'expectedMessage' => 'database URL is malformed',
    ],
    [
        'name' => '10. DB_URL kosong + localhost + database aquatara_test (must ACCEPT)',
        'default' => 'pgsql',
        'connections' => [
            'pgsql' => [
                'driver' => 'pgsql',
                'host' => 'localhost',
                'database' => 'aquatara_test',
                'url' => '',
            ],
        ],
        'expectException' => false,
    ],
    [
        'name' => '11. DB_URL valid localhost + database aquatara_test (must ACCEPT)',
        'default' => 'pgsql',
        'connections' => [
            'pgsql' => [
                'driver' => 'pgsql',
                'host' => '127.0.0.1',
                'database' => 'aquatara_test',
                'url' => 'postgresql://postgres:secret@127.0.0.1:5432/aquatara_test',
            ],
        ],
        'expectException' => false,
    ],
    [
        'name' => '12. Host 127.0.0.1 dengan database postgres (must REJECT)',
        'default' => 'pgsql',
        'connections' => [
            'pgsql' => [
                'driver' => 'pgsql',
                'host' => '127.0.0.1',
                'database' => 'postgres',
            ],
        ],
        'expectException' => true,
        'expectedMessage' => 'targets system/shared database',
    ],
    [
        'name' => '13. Host 127.0.0.1 + DB_URL menunjuk database sistem postgres (must REJECT)',
        'default' => 'pgsql',
        'connections' => [
            'pgsql' => [
                'driver' => 'pgsql',
                'host' => '127.0.0.1',
                'database' => 'aquatara_test',
                'url' => 'postgresql://postgres:secret@127.0.0.1:5432/postgres',
            ],
        ],
        'expectException' => true,
        'expectedMessage' => 'targets system/shared database',
    ],
    [
        'name' => '14. Koneksi bernama tambahan ke remote meskipun default lokal (must REJECT)',
        'default' => 'pgsql',
        'connections' => [
            'pgsql' => [
                'driver' => 'pgsql',
                'host' => '127.0.0.1',
                'database' => 'aquatara_test',
            ],
            'secondary_remote' => [
                'driver' => 'pgsql',
                'host' => 'rds.us-east-1.amazonaws.com',
                'database' => 'remote_db',
            ],
        ],
        'expectException' => true,
        'expectedMessage' => 'not a recognized local test host',
    ],
    [
        'name' => '15. Konfigurasi koneksi tidak lengkap / kosong (must REJECT)',
        'default' => 'pgsql',
        'connections' => [
            'pgsql' => [
                'driver' => 'pgsql',
                'host' => '',
                'database' => '',
                'url' => '',
            ],
        ],
        'expectException' => true,
        'expectedMessage' => 'has no host defined',
    ],
    [
        'name' => '16. Host database remote lainnya non-URL (must REJECT)',
        'default' => 'pgsql',
        'connections' => [
            'pgsql' => [
                'driver' => 'pgsql',
                'host' => '192.168.1.100',
                'database' => 'aquatara_test',
            ],
        ],
        'expectException' => true,
        'expectedMessage' => 'not a recognized local test host',
    ],
];

$results = [];
$allPassed = true;

foreach ($testCases as $tc) {
    $passed = false;
    $actualMessage = '';

    try {
        TestEnvironmentGuard::assertSafeEnvironment($tc['default'], $tc['connections']);
        if (!$tc['expectException']) {
            $passed = true;
        } else {
            $actualMessage = 'UNEXPECTED_PASS: Exception expected but not thrown';
        }
    } catch (\RuntimeException $e) {
        $actualMessage = $e->getMessage();
        if ($tc['expectException']) {
            if (empty($tc['expectedMessage']) || str_contains($actualMessage, $tc['expectedMessage'])) {
                $passed = true;
            } else {
                $passed = false;
                $actualMessage = "MISMATCH: Expected [{$tc['expectedMessage']}], got [{$actualMessage}]";
            }
        } else {
            $passed = false;
        }
    } catch (\Throwable $e) {
        $passed = false;
        $actualMessage = 'EXCEPTION: ' . $e->getMessage();
    }

    if (!$passed) {
        $allPassed = false;
    }

    $results[] = [
        'case' => $tc['name'],
        'expected' => $tc['expectException'] ? 'REJECT' : 'ACCEPT',
        'result' => $passed ? 'PASS' : 'FAIL',
        'detail' => $actualMessage,
    ];
}

echo json_encode(['all_passed' => $allPassed, 'cases' => $results], JSON_PRETTY_PRINT);
