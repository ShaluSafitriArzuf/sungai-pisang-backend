<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Supaya notifikasi (misal "Reservasi Anda Ditolak") bisa dipakai buat langsung mengarahkan
    // wisatawan ke halaman detail reservasi terkait saat notifikasi itu diklik — tanpa ini,
    // notifikasi cuma teks biasa tanpa tahu reservasi mana yang dimaksud.
    public function up(): void
    {
        Schema::table('notifikasi', function (Blueprint $table) {
            $table->foreignId('reservasi_id')->nullable()->after('user_id')->constrained('reservasi')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('notifikasi', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reservasi_id');
        });
    }
};
