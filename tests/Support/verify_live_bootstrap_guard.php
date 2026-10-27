<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Tests\TestCase;

$dummyTest = new class('testDummy') extends TestCase {
    public function testDummy(): void {}
};

try {
    // This will boot the actual Laravel application using the current .env / config
    // and must immediately be intercepted and thrown by createApplication()
    $dummyTest->createApplication();
    echo "FAILED: createApplication() succeeded without throwing!\n";
    exit(1);
} catch (\RuntimeException $e) {
    echo "SUCCESS: Guard threw exception as expected:\n" . $e->getMessage() . "\n";
    exit(0);
} catch (\Throwable $e) {
    echo "ERROR: Unexpected exception type: " . get_class($e) . " - " . $e->getMessage() . "\n";
    exit(2);
}
