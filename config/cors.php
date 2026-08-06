<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Frontend (React) dan backend (Laravel) berjalan pada alamat yang berbeda, sehingga
    | peramban akan memblokir permintaan dari frontend kecuali backend menyatakan alamat
    | tersebut diizinkan. Pengaturan di bawah inilah yang memberikan izin tersebut.
    |
    | Sebelumnya berkas ini belum ada, sehingga Laravel memakai nilai bawaan yang
    | mengizinkan SEMUA domain ('*'). Untuk aplikasi yang di-hosting, daftar domain
    | dibatasi hanya pada alamat frontend milik sendiri supaya situs lain tidak dapat
    | memanggil API ini langsung dari peramban pengunjung.
    |
    | Alamat frontend diambil dari FRONTEND_URL pada berkas .env agar tidak perlu
    | mengubah kode ketika domain berganti.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'storage/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_filter([
        env('FRONTEND_URL', 'http://localhost:5173'),
        // Alamat cadangan saat pengembangan di komputer sendiri (Vite kadang memakai
        // 127.0.0.1, bukan localhost).
        env('APP_ENV') === 'local' ? 'http://127.0.0.1:5173' : null,
    ]),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => ['Retry-After'],

    'max_age' => 0,

    // Sistem ini memakai Bearer Token (Laravel Sanctum), bukan cookie sesi, sehingga
    // kredensial berbasis cookie tidak diperlukan.
    'supports_credentials' => false,

];
