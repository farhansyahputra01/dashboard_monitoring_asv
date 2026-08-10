<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sensor_data', function (Blueprint $table) {
            $table->id();
            $table->float('temperature')->nullable();
            $table->float('humidity')->nullable();
            $table->double('latitude')->nullable();
            $table->double('longitude')->nullable();
            $table->float('speed')->nullable();
            $table->float('altitude')->nullable();
            $table->integer('satellites')->nullable();
            $table->float('heading')->nullable();
            $table->float('current')->nullable();
            $table->float('voltage')->nullable();
            $table->float('battery_percent')->nullable();
            $table->timestamps();

            // Tabel ini tumbuh terus (satu baris per paket serial),
            // index dipakai untuk mengambil data terbaru / rentang waktu.
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sensor_data');
    }
};
