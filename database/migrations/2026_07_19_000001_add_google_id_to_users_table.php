<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Dipakai buat tahu akun mana yang dibuat/pernah login lewat Google — supaya frontend bisa
    // menyembunyikan menu "Ganti Password" untuk akun ini (mereka tidak pernah tahu password
    // asli, karena diisi otomatis pakai string acak saat akun dibuat lewat Google).
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('google_id')->nullable()->after('foto');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('google_id');
        });
    }
};
