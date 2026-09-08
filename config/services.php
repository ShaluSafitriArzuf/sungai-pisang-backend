<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // Catatan: tarif penyeberangan dulu diatur global di sini lewat
    // 'penyeberangan.tarif_per_orang'. Sejak tarif kapal ternyata berbeda tiap pulau,
    // nilainya dipindah ke kolom harga_penyeberangan pada tabel pulau dan bisa diubah
    // sendiri oleh Pengelola Pulau lewat halaman Kelola Profil Pulau.

    // Kunci rahasia reCAPTCHA v2. Diisi di .env sebagai RECAPTCHA_SECRET.
    // Kalau dibiarkan kosong, verifikasi captcha DILEWATI (lihat AuthController) —
    // berguna saat pengembangan di komputer sendiri, tetapi di server produksi
    // nilai ini wajib diisi supaya kotak "Saya bukan robot" benar-benar berfungsi.
    'recaptcha' => [
        'secret' => env('RECAPTCHA_SECRET'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

];
