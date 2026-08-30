<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Posisi kapal pada PETA LINTASAN, bukan pada peta satelit.
 *
 * Sampai sekarang posisi hanya disimpan sebagai lat/lon. Di arena lomba
 * 30 x 30 m itu tidak cukup: modul GPS kelas hobi berdesir 2-5 m walau kapal
 * terikat diam, dan pada peta bergrid 1 m desiran sebesar itu berarti 2-5
 * kotak. Karena itu kapal sekarang menghitung sendiri posisinya dalam meter
 * (dead reckoning + penambat gerbang + pembatas GPS) dan mengirimkannya lewat
 * kolom-kolom di bawah.
 *
 * Semuanya NULLABLE dengan sengaja: program kapal versi lama - dan versi baru
 * yang dijalankan dengan --tanpa-posisi - tetap boleh mengirim telemetri tanpa
 * kolom ini, dan dashboard tinggal tidak menggambar penanda posisinya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sensor_data', function (Blueprint $table) {
            // Arena yang dipakai saat baris ini direkam: 'A' atau 'B'.
            // Disimpan per baris, bukan diambil dari monitoring_settings,
            // supaya rekaman lama tetap terbaca benar setelah panitia
            // memindahkan tim ke arena sebelah.
            $table->char('lintasan', 1)->nullable()->after('battery_percent');

            // Posisi dalam meter dari sudut kiri-bawah arena.
            $table->float('x_m')->nullable()->after('lintasan');
            $table->float('y_m')->nullable()->after('x_m');

            // Dari mana angka x/y itu berasal: DR, GERBANG_3, GPS_KOREKSI,
            // BELUM_KALIBRASI. Tanpa ini operator tidak bisa membedakan
            // posisi yang baru saja ditambatkan gerbang (hampir pasti benar)
            // dari hasil dead reckoning yang sudah lama berjalan sendiri.
            $table->string('pos_sumber', 24)->nullable()->after('y_m');

            // Jarak tempuh kumulatif menurut dead reckoning (meter).
            $table->float('jarak_m')->nullable()->after('pos_sumber');

            // Selisih perkiraan posisi terhadap GPS saat pemeriksaan terakhir.
            // Ini ukuran kejujuran sistem: kalau angkanya terus membesar,
            // dead reckoning sedang melenceng dan kompas perlu dikalibrasi.
            $table->float('selisih_gps_m')->nullable()->after('jarak_m');

            // Kemajuan misi, ikut disimpan supaya jejak di peta bisa diberi
            // penanda "di sini gerbang ke-6 terlewati".
            $table->unsignedSmallInteger('pair_count')->nullable()->after('selisih_gps_m');
            $table->string('phase', 24)->nullable()->after('pair_count');
        });
    }

    public function down(): void
    {
        Schema::table('sensor_data', function (Blueprint $table) {
            $table->dropColumn([
                'lintasan',
                'x_m',
                'y_m',
                'pos_sumber',
                'jarak_m',
                'selisih_gps_m',
                'pair_count',
                'phase',
            ]);
        });
    }
};
