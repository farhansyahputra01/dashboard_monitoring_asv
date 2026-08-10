import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            // Semua file yang dipanggil lewat @vite([...]) di Blade harus
            // terdaftar di sini, kalau tidak `npm run build` (produksi/nginx)
            // gagal dengan "Unable to locate file in Vite manifest".
            input: [
                'resources/css/app.css',
                'resources/css/global.css',
                'resources/css/admin.css',
                'resources/css/admin/dashboard.css',
                'resources/css/admin/monitoring.css',
                'resources/css/admin/camera.css',
                'resources/css/admin/alarm.css',
                'resources/css/admin/settings.css',
                'resources/css/user.css',
                'resources/css/user/dashboard.css',
                'resources/css/user/monitoring.css',
                'resources/css/user/camera.css',
                'resources/css/auth/login.css',
                'resources/css/auth/account.css',
                'resources/js/app.js',
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
