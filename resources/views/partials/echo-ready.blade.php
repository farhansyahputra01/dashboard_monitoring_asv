{{--
    Jembatan antara skrip inline halaman dan window.Echo.

    Bundel @vite dimuat sebagai modul, jadi eksekusinya ditunda sampai HTML
    selesai diurai - artinya skrip inline di dalam halaman SELALU berjalan
    lebih dulu daripada window.Echo dibuat. Halaman tidak boleh memeriksa
    window.Echo sekali lalu menyerah.

    Pola lama melakukan persis itu: setTimeout satu detik, cek sekali, kalau
    belum ada tulis "WebSockets Terputus" dan berhenti. Di laptop bundelnya
    selesai jauh di bawah satu detik sehingga selalu menang balapan; di
    Raspberry Pi - kartu SD lambat, CPU sibuk OpenCV, aset lewat WiFi - sering
    tidak. Listener tidak pernah terpasang dan dashboard beku permanen, tanpa
    satu pun pesan galat di Console.

    Fungsi ini bebas balapan ke dua arah: kalau Echo sudah ada, callback
    dijalankan sekarang juga; kalau belum, ia menunggu peristiwa 'echo:siap'
    yang dikirim resources/js/echo.js.

    Harus berupa skrip biasa (bukan modul) dan berada di <head>, supaya sudah
    terdefinisi sebelum skrip inline halaman mana pun berjalan.
--}}
<script>
    window.saatEchoSiap = function (callback) {
        if (window.Echo) {
            callback();
            return;
        }

        window.addEventListener('echo:siap', function () {
            callback();
        }, { once: true });
    };
</script>
