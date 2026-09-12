<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Apakah thruster sedang hidup saat baris telemetri ini dibuat.
 *
 * Dikirim program kapal, dipakai peta jejak GPS (trajectory-map.js) untuk
 * memutuskan fix mana yang boleh menjadi titik jejak: hanya saat motor
 * hidup. Sebelumnya peta menebak "kapal diam" dari kecepatan Doppler GPS -
 * tebakan tidak langsung yang butuh ambang hasil ukur (2,5 km/jam) dan
 * masih bisa salah. Kapal tahu persis kapan motornya hidup; itu yang
 * seharusnya dikirim.
 *
 * Nullable: program versi lama tidak mengirimnya, dan peta kembali ke
 * saringan Doppler untuk baris seperti itu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sensor_data', function (Blueprint $table) {
            $table->boolean('motor_on')->nullable()->after('phase');
        });
    }

    public function down(): void
    {
        Schema::table('sensor_data', function (Blueprint $table) {
            $table->dropColumn('motor_on');
        });
    }
};
