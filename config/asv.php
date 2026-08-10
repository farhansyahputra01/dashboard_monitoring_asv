<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Token Pengiriman Telemetri
    |--------------------------------------------------------------------------
    |
    | Endpoint /api/telemetry terbuka ke internet lewat ngrok, jadi wajib
    | bertoken - tanpa itu siapa pun yang tahu URL-nya bisa menyuntikkan data
    | sensor palsu. Program Python di kapal mengirimkannya lewat header
    | X-ASV-Token.
    |
    | Dibiarkan kosong -> endpoint MENOLAK semua permintaan (fail closed).
    |
    */

    'ingest_token' => env('ASV_INGEST_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Alamat Kendali Kapal
    |--------------------------------------------------------------------------
    |
    | Server Flask di dalam program Python (stream_server.py). Program itulah
    | pemilik tunggal port serial ESP32, jadi berhenti darurat harus lewat dia -
    | Laravel tidak bisa menulis ke serial secara langsung.
    |
    | Berada di mesin yang sama (Raspberry Pi), maka 127.0.0.1 dan tanpa token.
    | Jangan proxy alamat ini lewat nginx: /control/resume bisa menjalankan
    | kembali kapal, dan itu tidak boleh terjangkau dari luar.
    |
    */

    'control_url' => env('ASV_CONTROL_URL', 'http://127.0.0.1:8000'),

    'control_timeout' => (float)env('ASV_CONTROL_TIMEOUT', 3),

];
