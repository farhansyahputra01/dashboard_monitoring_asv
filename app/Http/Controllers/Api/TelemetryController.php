<?php

namespace App\Http\Controllers\Api;

use App\Events\SensorDataUpdated;
use App\Http\Controllers\Controller;
use App\Models\SensorData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Penerima telemetri dari program Python di kapal.
 *
 * Di Raspberry Pi, port serial ESP32 dipegang penuh oleh program Python
 * (deteksi buoy + kemudi PID), karena hanya satu proses yang boleh membuka
 * /dev/ttyUSB0. Laravel tidak pernah menyentuh serial di sana - telemetri
 * masuk lewat endpoint ini. Perintah asv:read-serial hanya untuk pengembangan
 * di laptop.
 */
class TelemetryController extends Controller
{
    /**
     * Nama field pada JSON Python berbeda dengan nama kolom di sini.
     * Keduanya diterima supaya tidak rapuh terhadap perubahan di sisi kapal.
     */
    private const ALIASES = [
        'speed' => ['speed', 'speed_kmph'],
        'voltage' => ['voltage', 'battery_voltage'],
    ];

    public function store(Request $request): JsonResponse
    {
        $token = config('asv.ingest_token');

        // Fail closed: endpoint ini terekspos lewat ngrok.
        if (empty($token) || !hash_equals($token, (string)$request->header('X-ASV-Token'))) {
            return response()->json(['message' => 'Token tidak valid.'], 401);
        }

        // Penanda "tidak valid" dari firmware (-999, GPS 0,0) diterjemahkan
        // menjadi null SEBELUM validasi. Kalau dibalik, -999 dari kompas yang
        // mati akan ditabrak aturan between:0,360 dan seluruh baris telemetri
        // ditolak - padahal sensor lainnya baik-baik saja.
        $payload = $this->applySensorRules($this->normalise($request));

        $validated = validator($payload, [
            'temperature' => ['nullable', 'numeric'],
            'humidity' => ['nullable', 'numeric'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'speed' => ['nullable', 'numeric'],
            'altitude' => ['nullable', 'numeric'],
            'satellites' => ['nullable', 'integer', 'min:0'],
            'heading' => ['nullable', 'numeric', 'between:0,360'],
            'current' => ['nullable', 'numeric'],
            'voltage' => ['nullable', 'numeric'],
            'battery_percent' => ['nullable', 'numeric', 'between:0,100'],
        ])->validate();

        $sensorData = SensorData::create($validated);

        // SensorDataUpdated sekarang ShouldBroadcastNow, jadi pengiriman ke
        // Reverb terjadi DI DALAM request ini (bukan lewat antrean) demi
        // realtime. Konsekuensinya Reverb yang mati bisa melempar exception
        // di sini - dan itu tidak boleh menggagalkan penyimpanan telemetri
        // yang sudah terlanjur tersimpan di baris atas. Data kapal jauh
        // lebih berharga daripada satu kali push ke browser.
        try {
            broadcast(new SensorDataUpdated($sensorData));
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->json([
            'id' => $sensorData->id,
            'gps_fix' => $sensorData->latitude !== null,
        ], 201);
    }

    /**
     * Samakan nama field Python dengan nama kolom, dan buang field lain
     * (misal `timestamp`) yang bukan milik tabel ini.
     */
    private function normalise(Request $request): array
    {
        $payload = [];

        foreach (SensorData::make()->getFillable() as $column) {
            $sources = self::ALIASES[$column] ?? [$column];

            foreach ($sources as $source) {
                if ($request->has($source)) {
                    $payload[$column] = $request->input($source);
                    break;
                }
            }
        }

        return $payload;
    }

    /**
     * Firmware ESP32 memakai dua penanda "tidak valid" yang berbeda, dan
     * program Python meneruskannya apa adanya:
     *
     *   DHT11 gagal      -> -999
     *   GPS belum fix    -> 0 untuk lat/lng/speed/altitude
     *
     * Tanpa penanganan ini, posisi belum-fix tersimpan sebagai koordinat 0,0
     * yang terlihat seolah-olah valid. Aturannya ditegakkan di sini, di sisi
     * server, supaya tetap berlaku apa pun yang dikirim kapal.
     */
    private function applySensorRules(array $data): array
    {
        // Kompas ikut memakai penanda -999 sejak firmware bisa membedakan
        // "tidak menjawab" dari "menunjuk 0 derajat". Tanpa dipetakan ke null,
        // -999 akan ditolak aturan between:0,360 dan SELURUH baris telemetri
        // hilang - padahal sensor lainnya baik-baik saja.
        foreach (['temperature', 'humidity', 'heading'] as $field) {
            if (isset($data[$field]) && (float)$data[$field] === -999.0) {
                $data[$field] = null;
            }
        }

        $hasGpsFix = (float)($data['latitude'] ?? 0) !== 0.0
            || (float)($data['longitude'] ?? 0) !== 0.0;

        if (!$hasGpsFix) {
            foreach (['latitude', 'longitude', 'speed', 'altitude'] as $field) {
                $data[$field] = null;
            }
        }

        return $data;
    }
}
