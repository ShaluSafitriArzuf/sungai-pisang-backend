<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Fitur "wajib verifikasi email sebelum login" baru ditambahkan sekarang — kalau tidak
// di-backfill, SEMUA akun yang sudah ada sebelumnya (akun demo wisatawan/pengantar/pengelola,
// dan akun manapun yang sudah didaftarkan sebelum fitur ini ada) akan mendadak TERKUNCI tidak
// bisa login, padahal mereka tidak pernah diminta verifikasi apa pun. Migrasi ini menandai
// semua akun yang SUDAH ADA sebagai terverifikasi, supaya aturan baru ini cuma berlaku buat
// pendaftar BARU sejak sekarang.
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->whereNull('email_verified_at')->update(['email_verified_at' => now()]);
    }

    public function down(): void
    {
        // Sengaja tidak dikembalikan ke null — tidak ada cara membedakan mana yang memang
        // sudah terverifikasi asli vs hasil backfill ini, dan mengunci ulang akun yang sudah
        // jalan cuma akan bikin masalah baru.
    }
};
