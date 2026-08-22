<?php

namespace Database\Seeders;

use App\Models\Pulau;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

// Seeder buat GANTI akun dummy Pengelola Pulau (...@test.com, password: "password" dari
// DatabaseSeeder) jadi akun asli yang beneran dipakai pengelola pulau. Pakai updateOrCreate
// supaya aman dijalankan kapan saja: kalau akunnya udah ada (role=pengelola_pulau utk pulau
// itu), cuma name/email/password/verifikasi-nya yang di-update, BUKAN bikin akun dobel.
// Dicocokkan lewat pulau_id + role (bukan tebak ID pulau langsung), jadi tetap aman walau
// urutan ID pulau di database beda dari urutan seeding awal.
//
// PENTING: email dan password akun asli TIDAK ditulis di berkas ini, tapi diambil dari .env
// (berkas .env tidak ikut ke GitHub). Isi dulu baris berikut di .env sebelum menjalankan:
//   SEED_PENGELOLA_PASUMPAHAN_EMAIL=      SEED_PENGELOLA_PASUMPAHAN_PASSWORD=
//   SEED_PENGELOLA_SIRANDAH_EMAIL=        SEED_PENGELOLA_SIRANDAH_PASSWORD=
//   SEED_PENGELOLA_UJUNG_KAPURI_EMAIL=    SEED_PENGELOLA_UJUNG_KAPURI_PASSWORD=
//   SEED_PENGELOLA_PAGANG_EMAIL=          SEED_PENGELOLA_PAGANG_PASSWORD=
//   SEED_PENGELOLA_PAMUTUSAN_EMAIL=       SEED_PENGELOLA_PAMUTUSAN_PASSWORD=
//
// Cara jalanin (dari folder backend, lewat terminal):
//   php artisan db:seed --class="Database\Seeders\PengelolaAkunAsliSeeder"
class PengelolaAkunAsliSeeder extends Seeder
{
    public function run(): void
    {
        // Nama pulau di database -> awalan nama variabel di .env
        $petaPulau = [
            'Pulau Pasumpahan'   => 'SEED_PENGELOLA_PASUMPAHAN',
            'Pulau Sirandah'     => 'SEED_PENGELOLA_SIRANDAH',
            'Pulau Ujung Kapuri' => 'SEED_PENGELOLA_UJUNG_KAPURI',
            'Pulau Pagang'       => 'SEED_PENGELOLA_PAGANG',
            'Pulau Pamutusan'    => 'SEED_PENGELOLA_PAMUTUSAN',
        ];

        foreach ($petaPulau as $namaPulau => $awalan) {
            $email = env($awalan.'_EMAIL');
            $password = env($awalan.'_PASSWORD');

            if (! $email || ! $password) {
                $this->command?->warn("{$awalan}_EMAIL / {$awalan}_PASSWORD belum diisi di .env -- {$namaPulau} dilewati.");
                continue;
            }

            $pulau = Pulau::where('nama', $namaPulau)->first();

            if (! $pulau) {
                $this->command?->warn("Pulau '{$namaPulau}' tidak ditemukan di database -- dilewati.");
                continue;
            }

            User::updateOrCreate(
                ['pulau_id' => $pulau->id, 'role' => 'pengelola_pulau'],
                [
                    'name' => 'Pengelola '.$pulau->nama,
                    'email' => $email,
                    // email_verified_at diisi langsung -- akun ini dibuat manual oleh kita,
                    // bukan lewat form Registrasi publik, jadi tidak perlu ikut alur verifikasi
                    // email (kalau tidak diisi, akun ini malah tidak bisa dipakai login).
                    'email_verified_at' => now(),
                    'password' => Hash::make($password),
                ]
            );

            $this->command?->info("OK: {$pulau->nama} -> {$email}");
        }
    }
}
