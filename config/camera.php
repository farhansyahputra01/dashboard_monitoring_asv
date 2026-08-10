<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Stream Kamera Kapal
    |--------------------------------------------------------------------------
    |
    | URL stream MJPEG yang disiarkan Raspberry Pi di kapal. Dashboard hanya
    | MENAMPILKAN stream ini - tidak pernah mengakses kamera perangkat operator.
    |
    | Penting: di Linux satu perangkat kamera hanya bisa dibuka satu proses.
    | Karena program deteksi OpenCV sudah memegang kameranya, program itulah
    | yang harus menyiarkan frame-nya (lihat catatan di .env.example), bukan
    | mjpg-streamer terpisah yang akan berebut perangkat yang sama.
    |
    | Dibiarkan kosong -> dashboard menampilkan placeholder, bukan error.
    |
    */

    'streams' => [
        'atas' => env('CAMERA_ATAS_URL'),
        'bawah' => env('CAMERA_BAWAH_URL'),
    ],

];
