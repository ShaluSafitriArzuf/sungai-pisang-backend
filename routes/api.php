<?php

use App\Http\Controllers\Api\AkomodasiController;
use App\Http\Controllers\Api\AkomodasiFotoController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\GaleriFotoController;
use App\Http\Controllers\Api\GoogleAuthController;
use App\Http\Controllers\Api\ManifestController;
use App\Http\Controllers\Api\NotifikasiController;
use App\Http\Controllers\Api\PetaController;
use App\Http\Controllers\Api\PulauController;
use App\Http\Controllers\Api\ReservasiController;
use App\Http\Controllers\Api\UlasanController;
use App\Http\Controllers\Api\WahanaController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| PUBLIK (tidak perlu login) — dipakai halaman Beranda, Detail Pulau, Peta
|--------------------------------------------------------------------------
*/
// Rate limit (throttle:5,1 = maks 5 percobaan per menit per IP) khusus endpoint yang rawan
// disalahgunakan buat brute-force (coba-coba password/email berkali-kali) atau spam — supaya
// oknum tidak bisa asal tembak berulang-ulang dalam waktu singkat. Route publik lain (lihat
// pulau/wahana/dll di bawah) sengaja TIDAK dibatasi karena memang murni baca data, bukan
// aksi yang bisa disalahgunakan.
Route::middleware('throttle:5,1')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/lupa-password', [AuthController::class, 'lupaPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
    Route::post('/verifikasi-email/kirim-ulang', [AuthController::class, 'kirimUlangVerifikasi']);
});

// Link ini yang diklik dari email verifikasi — wajib dikasih nama 'verifikasi.email' persis
// karena dipakai AuthController::buatLinkVerifikasi() buat generate signed URL-nya. Throttle-nya
// dilonggarkan (20/menit, bukan 5) karena link sah dari email bisa saja diklik ulang oleh
// pemiliknya sendiri (klik ganda, refresh, dsb) — bukan aksi yang perlu dibatasi ketat.
Route::get('/verifikasi-email/{id}/{hash}', [AuthController::class, 'verifikasiEmail'])
    ->middleware('throttle:20,1')
    ->name('verifikasi.email');

// Redirect biasa (bukan dipanggil lewat axios/fetch) — makanya boleh di luar grup auth:sanctum.
Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirect']);
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback']);

Route::get('/pulau', [PulauController::class, 'index']);
Route::get('/pulau/{pulau}', [PulauController::class, 'show']);
Route::get('/wahana', [WahanaController::class, 'index']);
Route::get('/akomodasi', [AkomodasiController::class, 'index']);
Route::get('/akomodasi/{akomodasi}', [AkomodasiController::class, 'show']);
Route::get('/akomodasi/{akomodasi}/ketersediaan', [AkomodasiController::class, 'ketersediaan']);
Route::get('/ulasan', [UlasanController::class, 'index']);
Route::get('/peta', [PetaController::class, 'index']);

/*
|--------------------------------------------------------------------------
| TERPROTEKSI (wajib login via Sanctum Bearer Token)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'me']);
    Route::put('/user', [AuthController::class, 'updateProfile']);
    Route::put('/user/password', [AuthController::class, 'updatePassword']);

    Route::get('/notifikasi', [NotifikasiController::class, 'index']);
    Route::patch('/notifikasi/{notifikasi}/baca', [NotifikasiController::class, 'markAsRead']);

    // ── Wisatawan ────────────────────────────────
    Route::middleware('role:wisatawan')->group(function () {
        Route::post('/reservasi', [ReservasiController::class, 'store']);
        Route::post('/reservasi/{reservasi}/ajukan-batal', [ReservasiController::class, 'ajukanPembatalan']);
        Route::post('/reservasi/{reservasi}/upload-ulang-bukti', [ReservasiController::class, 'uploadUlangBukti']);
        Route::post('/ulasan', [UlasanController::class, 'store']);
    });

    // ── Pengantar Pulau ──────────────────────────
    Route::middleware('role:pengantar_pulau')->group(function () {
        Route::patch('/reservasi/{reservasi}/verifikasi', [ReservasiController::class, 'verifikasi']);
        Route::patch('/reservasi/{reservasi}/proses-batal', [ReservasiController::class, 'prosesPembatalan']);
        Route::get('/manifest', [ManifestController::class, 'index']);
        Route::post('/manifest/export', [ManifestController::class, 'export']);
        Route::post('/peta/lokasi-saya', [PetaController::class, 'updateLokasiSaya']);
    });

    // ── Pengelola Pulau (5 akun terpisah, 1 pulau per akun) ──
    Route::middleware('role:pengelola_pulau')->group(function () {
        Route::put('/pulau/{pulau}', [PulauController::class, 'update']);
        Route::get('/galeri-foto', [GaleriFotoController::class, 'index']);
        Route::post('/galeri-foto', [GaleriFotoController::class, 'store']);
        Route::delete('/galeri-foto/{galeriFoto}', [GaleriFotoController::class, 'destroy']);

        Route::post('/wahana', [WahanaController::class, 'store']);
        Route::put('/wahana/{wahana}', [WahanaController::class, 'update']);
        Route::delete('/wahana/{wahana}', [WahanaController::class, 'destroy']);

        Route::get('/akomodasi/kelola/semua', [AkomodasiController::class, 'kelola']);
        Route::post('/akomodasi', [AkomodasiController::class, 'store']);
        Route::put('/akomodasi/{akomodasi}', [AkomodasiController::class, 'update']);
        Route::delete('/akomodasi/{akomodasi}', [AkomodasiController::class, 'destroy']);
        Route::post('/akomodasi/{akomodasi}/foto', [AkomodasiFotoController::class, 'store']);
        Route::delete('/akomodasi/{akomodasi}/foto/{fotoAkomodasi}', [AkomodasiFotoController::class, 'destroy']);

        Route::delete('/ulasan/{ulasan}', [UlasanController::class, 'destroy']);
    });

    // ── Reservasi: index & show dipakai 3 role sekaligus (filter beda per role, lihat controller) ──
    Route::get('/reservasi', [ReservasiController::class, 'index']);
    Route::get('/reservasi/{reservasi}', [ReservasiController::class, 'show']);
});
