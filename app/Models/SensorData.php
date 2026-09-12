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

        // Posisi di peta lintasan (meter), dikirim kapal. Semuanya boleh
        // kosong: program yang dijalankan dengan --tanpa-posisi tetap sah.
        'lintasan',
        'x_m',
        'y_m',
        'pos_sumber',
        'jarak_m',
        'selisih_gps_m',
        'pair_count',
        'phase',

        // Thruster hidup saat baris ini dibuat. Gerbang validitas titik jejak
        // GPS di peta - lihat migrasi add_motor_on_to_sensor_data_table.
        'motor_on',
    ];

    protected $casts = [
        'x_m' => 'float',
        'y_m' => 'float',
        'jarak_m' => 'float',
        'selisih_gps_m' => 'float',
        'pair_count' => 'integer',
        'motor_on' => 'boolean',
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
            ->get(['latitude', 'longitude', 'heading', 'satellites', 'speed', 'motor_on', 'created_at'])
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
                // Thruster hidup? Gerbang validitas yang PASTI: titik jejak
                // hanya ditambah saat motor hidup. null = program kapal versi
                // lama, peta kembali ke saringan Doppler.
                'mtr' => $r->motor_on !== null ? (bool) $r->motor_on : null,
                // milidetik epoch. Peta memakainya sebagai pembagi waktu saat
                // memeriksa apakah sebuah perpindahan masuk akal terhadap
                // kecepatan yang dilaporkan.
                'ts' => $r->created_at->getTimestamp() * 1000,
            ])
            ->all();
    }

    /**
     * Jejak kapal di PETA LINTASAN, urut dari yang paling lama.
     *
     * Terpisah dari recentTrack() dan memang harus terpisah: yang itu
     * menyaring baris ber-GPS, yang ini menyaring baris yang punya x/y. Dua
     * himpunan yang berbeda - kapal bisa punya posisi peta yang sah (dari
     * dead reckoning) justru saat GPS-nya sedang tidak fix, dan itulah
     * keadaan yang paling ingin dilihat operator.
     */
    public static function recentLintasan(
        ?string $lintasan = null,
        int $limit = 900,
        int $jedaDetik = 60
    ): array {
        $baris = static::query()
            ->whereNotNull('x_m')
            ->whereNotNull('y_m')
            // Disaring per ARENA, dan ini bukan kerapian belaka: koordinat x/y
            // hanya bermakna di dalam arenanya sendiri. Menggambar jejak arena
            // B di atas gambar arena A menampilkan lintasan yang tidak pernah
            // terjadi - dan itu persis yang sempat terlihat, penanda kapal
            // muncul di titik start arena sebelah.
            //
            // Baris lama yang kolom lintasannya kosong ikut terbuang, dan
            // memang seharusnya: tidak ada cara tahu ia milik arena mana.
            ->when($lintasan, fn ($q) => $q->where('lintasan', $lintasan))
            ->latest('id')
            ->limit($limit)
            ->get(['x_m', 'y_m', 'heading', 'pos_sumber', 'pair_count', 'phase', 'lintasan', 'created_at'])
            ->reverse()
            ->values();

        // Sama seperti recentTrack: ambil hanya sesi terakhir, supaya peta
        // yang baru dibuka tidak menampilkan lintasan uji coba kemarin.
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
                'x' => (float) $r->x_m,
                'y' => (float) $r->y_m,
                'hdg' => $r->heading !== null ? (float) $r->heading : null,
                'src' => $r->pos_sumber,
                'gate' => $r->pair_count !== null ? (int) $r->pair_count : null,
                'fase' => $r->phase,
                'ts' => $r->created_at->getTimestamp() * 1000,
            ])
            ->all();
    }
}
