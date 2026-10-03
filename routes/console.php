<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Sandbox simulasi: buang yang kedaluwarsa, isi kolam siap-pakai.
Schedule::command('simulasi:kolam')->everyFiveMinutes()->withoutOverlapping(30)->runInBackground();
