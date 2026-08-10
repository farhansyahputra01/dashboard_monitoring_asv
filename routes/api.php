<?php

use App\Http\Controllers\Api\TelemetryController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Endpoint untuk kapal
|--------------------------------------------------------------------------
|
| Dipanggil program Python di Raspberry Pi, bukan oleh browser. Autentikasi
| memakai header X-ASV-Token (lihat config/asv.php).
|
*/

Route::post('/telemetry', [TelemetryController::class, 'store'])
    ->name('api.telemetry');
