<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    // Registrasi HANYA untuk wisatawan. Akun pengantar_pulau & pengelola_pulau
    // dibuat manual oleh admin/seeder (5 akun pengelola_pulau tetap, 1 akun pengantar_pulau).
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'no_hp' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'no_hp' => $request->no_hp,
            'role' => 'wisatawan',
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Registrasi berhasil.',
            'user' => $user,
            'token' => $token,
        ], 201);
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Email atau password salah.'], 401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login berhasil.',
            'user' => $user->load('pulau'),
            'token' => $token,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logout berhasil.']);
    }

    public function me(Request $request)
    {
        return response()->json($request->user()->load('pulau'));
    }

    // PUT /api/user — dipakai halaman Edit Profil (semua role bisa pakai endpoint ini)
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,'.$user->id,
            'no_hp' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user->update($validator->validated());

        return response()->json(['message' => 'Profil berhasil diperbarui.', 'user' => $user->fresh()->load('pulau')]);
    }

    // PUT /api/user/password — ganti password, wajib verifikasi password lama dulu
    public function updatePassword(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'password_lama' => 'required|string',
            'password_baru' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if (! Hash::check($request->password_lama, $user->password)) {
            return response()->json(['message' => 'Password lama yang Anda masukkan salah.'], 422);
        }

        $user->update(['password' => Hash::make($request->password_baru)]);

        return response()->json(['message' => 'Password berhasil diganti.']);
    }

    // POST /api/lupa-password — dipakai halaman "Lupa Password" (belum login).
    // Selama MAIL_MAILER masih "log" (belum ada SMTP asli di .env), link reset TIDAK terkirim
    // sebagai email sungguhan — cukup buka storage/logs/laravel.log untuk mengambil link-nya.
    // Begitu SMTP asli diisi di .env, tanpa ubah kode apa pun, email akan benar-benar terkirim.
    public function lupaPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Selalu balas pesan yang sama baik email terdaftar atau tidak, supaya orang lain
        // tidak bisa dipakai untuk menebak-nebak email mana saja yang punya akun di sistem ini.
        Password::sendResetLink($request->only('email'));

        return response()->json([
            'message' => 'Kalau email tersebut terdaftar, link reset password sudah dikirim.',
        ]);
    }

    // POST /api/reset-password — halaman "Reset Password" (dari link/token di atas)
    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
            'email' => 'required|email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->update(['password' => Hash::make($password)]);
                // Cabut semua token lama supaya sesi login sebelumnya (kalau ada) ikut terputus.
                $user->tokens()->delete();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json([
                'message' => $status === Password::INVALID_TOKEN
                    ? 'Link reset ini sudah tidak berlaku. Coba minta link baru.'
                    : 'Gagal reset password. Coba lagi.',
            ], 422);
        }

        return response()->json(['message' => 'Password berhasil direset, silakan login dengan password baru.']);
    }
}
