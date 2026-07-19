<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    // GET /api/auth/google/redirect — dipanggil lewat <a href> biasa (bukan fetch/axios),
    // jadi browser pindah halaman langsung ke Google, bukan lewat request AJAX.
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')->stateless()->redirect();
    }

    // GET /api/auth/google/callback — Google balik ke sini setelah user login & setuju.
    // "stateless" dipakai karena SPA React ini murni API berbasis token (Sanctum), tidak
    // pakai session/cookie Laravel, jadi tidak ada state sesi untuk dicocokkan.
    public function callback(): RedirectResponse
    {
        $frontendUrl = rtrim(env('FRONTEND_URL', 'http://localhost:5173'), '/');

        try {
            $googleUser = Socialite::driver('google')->stateless()->user();
        } catch (\Throwable $e) {
            return redirect("{$frontendUrl}/login?google_error=".urlencode('Login Google gagal, coba lagi.'));
        }

        // Kalau email itu sudah punya akun (wisatawan/pengelola/pengantar), langsung login
        // sebagai akun itu. Kalau belum ada, buat akun wisatawan baru otomatis — sama seperti
        // alur Register manual, cuma tanpa perlu isi password (dia login-nya selalu lewat Google).
        $user = User::where('email', $googleUser->getEmail())->first();

        if (! $user) {
            $user = User::create([
                'name' => $googleUser->getName() ?: $googleUser->getNickname() ?: 'Pengguna Google',
                'email' => $googleUser->getEmail(),
                'password' => Hash::make(Str::random(40)),
                'role' => 'wisatawan',
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        // Redirect balik ke halaman React yang tugasnya cuma "tangkap token dari URL, simpan,
        // lanjut ke dashboard" — lihat src/pages/auth/GoogleCallback.jsx di frontend.
        return redirect("{$frontendUrl}/auth/google/callback?token={$token}");
    }
}
