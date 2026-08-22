<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

// Sama kayak PengelolaAkunAsliSeeder, tapi buat 1 akun Pengantar Pulau (akun bersama,
// dipakai buat verifikasi reservasi di semua pulau -- makanya ga dicocokkan lewat pulau_id,
// cukup role='pengantar_pulau' aja karena cuma ada 1 akun ini di seluruh sistem).
// updateOrCreate: kalau akun dummy (pengantar@test.com) udah ada, cuma name/email/password
// yang diganti -- latitude/longitude titik kumpul yang udah diisi lewat halaman "Pengaturan
// Lokasi" ga ikut kesentuh/kehapus.
//
// PENTING: email dan password akun asli TIDAK ditulis di berkas ini, tapi diambil dari .env
// (berkas .env tidak ikut ke GitHub). Isi dulu dua baris ini di .env sebelum menjalankan:
//   SEED_PENGANTAR_EMAIL=
//   SEED_PENGANTAR_PASSWORD=
//
// Cara jalanin (dari folder backend, lewat terminal):
//   php artisan db:seed --class="Database\Seeders\PengantarAkunAsliSeeder"
class PengantarAkunAsliSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('SEED_PENGANTAR_EMAIL');
        $password = env('SEED_PENGANTAR_PASSWORD');

        if (! $email || ! $password) {
            $this->command?->warn('SEED_PENGANTAR_EMAIL / SEED_PENGANTAR_PASSWORD belum diisi di .env -- seeder dilewati.');

            return;
        }

        User::updateOrCreate(
            ['role' => 'pengantar_pulau'],
            [
                'name' => 'Pengantar Pulau',
                'email' => $email,
                // email_verified_at diisi langsung -- akun ini dibuat manual oleh kita, bukan
                // lewat form Registrasi publik, jadi tidak perlu ikut alur verifikasi email.
                'email_verified_at' => now(),
                'password' => Hash::make($password),
            ]
        );

        $this->command?->info("OK: Pengantar Pulau -> {$email}");
    }
}
