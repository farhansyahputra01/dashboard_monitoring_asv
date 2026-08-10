<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Kapal menulis satu baris per detik. Tanpa pemangkasan, kartu SD Raspberry Pi
// menerima 86.400 baris per hari terus-menerus.
Schedule::command('asv:prune-sensor-data --days=7')
    ->dailyAt('03:00')
    ->onOneServer();
