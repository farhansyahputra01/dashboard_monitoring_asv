<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MonitoringSetting;
use App\Models\SensorData;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Berhenti darurat dan lanjut jalan.
 *
 * Laravel tidak pernah menulis ke port serial ESP32 - port itu dipegang penuh
 * program Python di kapal (deteksi buoy + kemudi PID), karena satu perangkat
 * hanya boleh dibuka satu proses. Jadi perintah berhenti diteruskan ke server
 * Flask milik program tersebut, yang menaikkan flag manual_stop lalu mengirim
 * "L:0,R:0" ke ESP32 pada iterasi berikutnya (~20 ms).
 *
 * Lapis pengaman kedua ada di firmware: bila tidak ada perintah motor selama
 * 1 detik, ESC kembali ke netral dengan sendirinya. Jadi kalau program Python
 * atau Raspberry Pi mati, kapal tetap berhenti.
 */
class ControlController extends Controller
{
    public function stop(): JsonResponse
    {
        return $this->forward('/control/stop', 'Kapal diperintahkan berhenti.');
    }

    public function resume(): JsonResponse
    {
        return $this->forward('/control/resume', 'Kemudi otomatis dijalankan kembali.');
    }

<<<<<<< HEAD
    public function rth(): JsonResponse
    {
        return $this->forward('/control/rth', 'Perintah Return to Home (RTH) telah dikirim ke kapal.');
=======
    /**
     * Kapal kembali ke titik start lewat jejak yang sudah dilewatinya
     * (peta_jalur.JejakRoti di kapal). Tidak melepas berhenti darurat:
     * kapal yang sedang dihentikan tetap berhenti sampai MULAI ditekan.
     */
    public function pulang(): JsonResponse
    {
        return $this->forward('/control/pulang', 'Kapal diperintahkan PULANG ke titik start.');
>>>>>>> fc27ed5b06fe9959b2cb750ee245fd9ff196f6e8
    }

    public function status(): JsonResponse
    {
        $response = $this->call('get', '/control/status');

        if ($response === null) {
            return response()->json([
                'reachable' => false,
                'stopped' => null,
                'message' => 'Kendali kapal tidak terjangkau.',
            ], 503);
        }

        return response()->json(array_merge([
            'reachable' => true,
            'stopped' => (bool)$response->json('stopped'),
            'pulang' => (bool)$response->json('pulang'),
        ], $this->arena()));
    }

    /**
     * Arena yang DIPILIH operator vs arena yang SEDANG DIPAKAI kapal.
     *
     * Kapal membaca pilihan Lintasan sekali saja, waktu programnya dinyalakan.
     * Kalau operator menggantinya sesudah itu, kapal tidak ikut pindah - ia
     * terus menghitung posisi di arena lama sementara dashboard menggambar
     * arena baru. Tidak ada galat yang muncul; petanya saja yang diam-diam
     * salah sepanjang lomba.
     *
     * Karena itu keduanya dibandingkan di sini, dan tombol MULAI menolak
     * bekerja selama tidak cocok.
     */
    private function arena(): array
    {
        $dipilih = optional(MonitoringSetting::first())->active_track;

        $terakhir = SensorData::whereNotNull('lintasan')->latest('id')->first();

        // Telemetri basi tidak boleh dipakai menilai: kapal yang dimatikan
        // sejak kemarin akan terus "mengaku" memakai arena lamanya.
        $kapal = ($terakhir && $terakhir->created_at->gt(now()->subSeconds(20)))
            ? $terakhir->lintasan
            : null;

        return [
            'lintasan_dipilih' => $dipilih,
            'lintasan_kapal' => $kapal,
            // null = kapal belum melaporkan arena apa pun (belum jalan, atau
            // dijalankan dengan --tanpa-posisi). Itu bukan ketidakcocokan.
            'lintasan_cocok' => $kapal === null || $kapal === $dipilih,
        ];
    }

    private function forward(string $path, string $successMessage): JsonResponse
    {
        $response = $this->call('post', $path);

        // Operator HARUS tahu kalau perintahnya tidak sampai. Diam-diam gagal
        // di sini berarti dia mengira kapal sudah berhenti padahal masih jalan.
        if ($response === null) {
            return response()->json([
                'reachable' => false,
                'stopped' => null,
                'message' => 'PERINTAH TIDAK SAMPAI. Kendali kapal tidak merespons — '
                    . 'kapal kemungkinan masih berjalan. Putuskan daya kapal secara manual.',
            ], 503);
        }

        return response()->json([
            'reachable' => true,
            'stopped' => (bool)$response->json('stopped'),
            'pulang' => (bool)$response->json('pulang'),
            'message' => $successMessage,
        ]);
    }

    /**
     * Kembalikan null bila kendali kapal tidak terjangkau atau menolak.
     */
    private function call(string $method, string $path)
    {
        $url = rtrim((string)config('asv.control_url'), '/') . $path;

        try {
            $response = Http::timeout(config('asv.control_timeout'))
                ->acceptJson()
                ->{$method}($url);

            if ($response->failed()) {
                Log::warning('Kendali kapal menolak permintaan', [
                    'url' => $url,
                    'status' => $response->status(),
                ]);

                return null;
            }

            return $response;
        } catch (\Throwable $e) {
            Log::warning('Kendali kapal tidak terjangkau', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
