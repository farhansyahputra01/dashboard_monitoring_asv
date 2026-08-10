<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Kolom `username` tidak dipakai kode manapun (login memakai email),
     * tetapi pada database hasil import lama kolom ini NOT NULL sehingga
     * pembuatan user baru selalu gagal.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'username')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Sengaja dibiarkan kosong: mengembalikan NOT NULL akan gagal
        // bila sudah ada baris dengan username kosong.
    }
};
