<?php

use App\Services\SendOutboxProcessor;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('memos:retry-outbox', function (SendOutboxProcessor $processor) {
    $processed = $processor->retryPending();
    $this->info("Processed {$processed} pending send outbox record(s).");
})->purpose('Retry pending or failed memo recipient fan-out outbox records');

Schedule::command('memos:retry-outbox')->everyMinute()->withoutOverlapping();
