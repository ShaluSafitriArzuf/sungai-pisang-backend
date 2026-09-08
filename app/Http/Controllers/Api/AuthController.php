<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\VerifikasiEmail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    // Bikin link verifikasi email yang "ditandatangani" (signed) — link ini otomatis kadaluarsa
    // sendiri (60 menit) dan tidak bisa diutak-atik (ganti id/hash-nya) tanpa ketahuan tidak
    // valid, tanpa perlu tabel token terpisah kayak reset password.
    private function buatLinkVerifikasi(User $user): string
    {
        return URL::temporarySignedRoute(
            'verifikasi.email',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );
    }

    // Registrasi HANYA untuk wisatawan. Akun pengantar_pulau & pengelola_pulau
    // dibuat manual oleh admin/seeder (5 akun pengelola_pulau tetap, 1 akun pengantar_pulau).
    //
    // PENTING (kenapa ada verifikasi email): tanpa ini, siapa pun bisa daftar pakai email
    // SEMBARANGAN — email orang lain, atau email yang bahkan tidak ada — dan langsung dapat
    // akun aktif tanpa bukti dia benar-benar pemilik email itu. Ini rawan disalahgunakan buat
    // spam reservasi pakai identitas palsu. Jadi sekarang akun baru TIDAK langsung aktif/login
    // otomatis — wajib klik link verifikasi yang dikirim ke email itu dulu.
    //
    // Login lewat Google TIDAK butuh langkah ini — Google sendiri sudah memverifikasi pemilik
    // email itu sebelum mengizinkan login (lihat GoogleAuthController), jadi kita percaya begitu
    // saja (email_verified_at diisi otomatis di sana).

    /**
     * Memeriksa token reCAPTCHA v2 ke server Google.
     *
     * Kotak "Saya bukan robot" di halaman depan hanya menghasilkan token; token itu tidak
     * membuktikan apa pun sampai diperiksa ke Google dari sisi server memakai kunci rahasia.
     * Pemeriksaan sengaja dilakukan di sini, bukan di React, karena apa pun yang berjalan di
     * peramban pengguna bisa dilewati.
     *
     * Token bersifat sekali pakai, jadi setiap percobaan login/daftar butuh centang baru.
     *
     * Kalau RECAPTCHA_SECRET belum diisi di .env, pemeriksaan dilewati. Ini disengaja supaya
     * sistem tetap bisa dijalankan di komputer sendiri saat pengembangan, dan sekaligus
     * menjadi jalan keluar darurat kalau server tidak dapat menghubungi Google.
     */
    private function captchaSah(Request $request): bool
    {
        $rahasia = config('services.recaptcha.secret');

        if (empty($rahasia)) {
            return true;
        }

        $token = $request->input('captcha');

        if (empty($token)) {
            return false;
        }

        try {
            $jawaban = Http::asForm()->timeout(10)->post(
                'https://www.google.com/recaptcha/api/siteverify',
                [
                    'secret' => $rahasia,
                    'response' => $token,
                    'remoteip' => $request->ip(),
                ]
            );
        } catch (\Throwable $e) {
            // Server Google tidak dapat dihubungi. Ditolak, bukan diloloskan begitu saja.
            return false;
        }

        return (bool) ($jawaban->json('success') ?? false);
    }

    private function tolakCaptcha()
    {
        return response()->json([
            'message' => 'Verifikasi keamanan gagal. Centang ulang kotak "Saya bukan robot", lalu coba lagi.',
        ], 422);
    }

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

        if (! $this->captchaSah($request)) {
            return $this->tolakCaptcha();
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'no_hp' => $request->no_hp,
            'role' => 'wisatawan',
        ]);

        $user->notify(new VerifikasiEmail($this->buatLinkVerifikasi($user)));

        // Sengaja TIDAK langsung mengeluarkan token/auto-login — akun ini belum aktif sampai
        // link di email-nya diklik.
        return response()->json([
            'message' => 'Registrasi berhasil! Kami sudah mengirim link verifikasi ke email kamu — klik dulu link itu sebelum bisa login.',
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

        if (! $this->captchaSah($request)) {
            return $this->tolakCaptcha();
        }

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Email atau password salah.'], 401);
        }

        // Akun Google (google_id terisi) selalu dianggap terverifikasi (lihat GoogleAuthController).
        // Ini cuma menyaring akun hasil REGISTRASI MANUAL yang belum klik link verifikasinya.
        if (! $user->google_id && ! $user->email_verified_at) {
            return response()->json([
                'message' => 'Email kamu belum diverifikasi. Cek email untuk link verifikasi, atau minta link baru di bawah ini.',
                'perlu_verifikasi_email' => true,
            ], 403);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login berhasil.',
            'user' => $user->load('pulau'),
            'token' => $token,
        ]);
    }

    // GET /api/verifikasi-email/{id}/{hash} — link yang diklik dari email. Signed URL, jadi
    // Laravel otomatis menolak kalau linknya kadaluarsa atau parameternya diubah-ubah.
    public function verifikasiEmail(Request $request, $id, $hash)
    {
        $frontendUrl = rtrim(env('FRONTEND_URL', 'http://localhost:5173'), '/');

        if (! $request->hasValidSignature()) {
            return redirect("{$frontendUrl}/login?verifikasi=kadaluarsa");
        }

        $user = User::find($id);

        if (! $user || ! hash_equals(sha1($user->email), (string) $hash)) {
            return redirect("{$frontendUrl}/login?verifikasi=gagal");
        }

        if (! $user->email_verified_at) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        return redirect("{$frontendUrl}/login?verifikasi=berhasil");
    }

    // POST /api/verifikasi-email/kirim-ulang — dipakai kalau link di email sudah kadaluarsa,
    // atau emailnya tidak sampai. Balas pesan yang sama persis baik email itu terdaftar atau
    // tidak (sama seperti lupaPassword), supaya tidak bisa dipakai menebak-nebak email terdaftar.
    public function kirimUlangVerifikasi(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::where('email', $request->email)->first();

        if ($user && ! $user->google_id && ! $user->email_verified_at) {
            $user->notify(new VerifikasiEmail($this->buatLinkVerifikasi($user)));
        }

        return response()->json([
            'message' => 'Kalau email tersebut terdaftar dan belum diverifikasi, link verifikasi baru sudah dikirim.',
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

        // Kalau email diganti, jangan langsung dianggap terverifikasi begitu saja — belum ada
        // bukti pengguna benar-benar pemilik alamat email BARU itu. Reset status verifikasi
        // dan kirim ulang link verifikasi ke email baru (pakai mekanisme yang sama seperti
        // registrasi). Akun Google dikecualikan karena verifikasinya ditangani Google, bukan
        // lewat link email kita, dan email Google umumnya tidak diedit manual dari sini.
        $emailBerubah = $request->email !== $user->email;

        $user->fill($validator->validated());

        if ($emailBerubah && ! $user->google_id) {
            $user->email_verified_at = null;
        }

        $user->save();

        if ($emailBerubah && ! $user->google_id) {
            $user->notify(new VerifikasiEmail($this->buatLinkVerifikasi($user)));
        }

        return response()->json([
            'message' => $emailBerubah && ! $user->google_id
                ? 'Profil berhasil diperbarui. Email baru kamu perlu diverifikasi lagi — cek email untuk link verifikasi.'
                : 'Profil berhasil diperbarui.',
            'user' => $user->fresh()->load('pulau'),
        ]);
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
