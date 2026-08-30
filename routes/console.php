<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// DB-IP publish a new country database on the first of each month. Fetched on
// the second so a run cannot beat the publication, and off peak because it
// restarts the queue workers to pick the new file up.
Schedule::command('rotator:geoip-update')->monthlyOn(2, '03:00')->onOneServer();
