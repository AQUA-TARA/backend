<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule the ML risk analysis to run on a set interval.
// Hourly is chosen as a reasonable default for MVP since IoT nodes and citizen reports
// update continuously, and running it periodically captures updates efficiently without
// overloading the ML service.
// withoutOverlapping() ensures that if an execution takes longer than the interval,
// a duplicate execution will not start, providing concurrency protection.
Schedule::command('ml:run-analysis')->hourly()->withoutOverlapping();
