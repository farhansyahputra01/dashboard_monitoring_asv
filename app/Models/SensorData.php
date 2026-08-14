<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SensorData extends Model
{
    protected $table = 'sensor_data';

    protected $fillable = [
        'temperature',
        'humidity',
        'latitude',
        'longitude',
        'speed',
        'altitude',
        'satellites',
        'heading',
        'current',
        'voltage',
        'battery_percent',
    ];

    /**
     * Titik-titik lintasan untuk peta jejak, urut dari yang paling lama.
     *
     * Hanya baris yang GPS-nya sudah fix yang diambil - baris tanpa fix
     * disimpan dengan latitude/longitude NULL, dan menariknya ke peta akan
     * menghasilkan lompatan ke koordinat 0,0 di lepas pantai Afrika.
     */
    public static function recentTrack(int $limit = 600, int $jedaDetik = 60): array
    {
        $baris = static::query()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->latest('id')
            ->limit($limit)
            ->get(['latitude', 'longitude', 'heading', 'satellites', 'speed', 'created_at'])
            ->reverse()
            ->values();

        // Ambil hanya sesi TERAKHIR, yaitu titik-titik setelah jeda waktu
        // terakhir yang lebih panjang dari $jedaDetik. Tanpa ini, peta yang
        // baru dibuka langsung menampilkan lintasan uji coba kemarin seolah
        // kapal sudah bergerak, padahal belum berangkat.
        $mulai = 0;
        for ($i = 1; $i < $baris->count(); $i++) {
            $selisih = $baris[$i]->created_at->getTimestamp()
                - $baris[$i - 1]->created_at->getTimestamp();

            if ($selisih > $jedaDetik) {
                $mulai = $i;
            }
        }

        return $baris->slice($mulai)
            ->values()
            ->map(fn ($r) => [
                'lat' => (float) $r->latitude,
                'lng' => (float) $r->longitude,
                'hdg' => $r->heading !== null ? (float) $r->heading : null,
                // Ikut dikirim supaya riwayat melewati saringan jumlah satelit
                // yang sama dengan titik realtime. Tanpa ini, titik berkualitas
                // rendah yang sudah tersaring saat siaran akan muncul kembali
                // setiap kali halaman di-refresh.
                'sat' => $r->satellites !== null ? (int) $r->satellites : null,
                // km/jam. Dipakai peta untuk membedakan kapal yang benar-benar
                // bergerak dari desiran GPS saat kapal diam.
                'spd' => $r->speed !== null ? (float) $r->speed : null,
                // milidetik epoch. Peta memakainya sebagai pembagi waktu saat
                // memeriksa apakah sebuah perpindahan masuk akal terhadap
                // kecepatan yang dilaporkan.
                'ts' => $r->created_at->getTimestamp() * 1000,
            ])
            ->all();
    }
}
