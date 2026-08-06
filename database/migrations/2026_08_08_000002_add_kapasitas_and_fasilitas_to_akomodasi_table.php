<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sebelumnya perbedaan antar akomodasi hanya bisa dibaca dari kalimat deskripsi bebas,
     * sehingga wisatawan harus menebak apakah sebuah unit ber-AC, berkipas angin, atau hanya
     * pondok terbuka. Dua kolom berikut membuat informasi itu terstruktur dan bisa
     * ditampilkan sebagai daftar fasilitas yang seragam:
     *
     *  - kapasitas : jumlah orang yang muat dalam satu unit, supaya wisatawan bisa
     *                membandingkan harga per unit secara adil.
     *  - fasilitas : daftar fasilitas unit (AC, kipas angin, kamar mandi dalam, dst.)
     *                disimpan sebagai array JSON, mengikuti pola kolom fasilitas
     *                pada tabel pulau.
     */
    public function up(): void
    {
        Schema::table('akomodasi', function (Blueprint $table) {
            $table->unsignedInteger('kapasitas')->nullable()->after('jumlah_unit')
                ->comment('jumlah orang yang muat dalam satu unit akomodasi');
            $table->json('fasilitas')->nullable()->after('deskripsi')
                ->comment('daftar fasilitas unit, mis. ["ac","kamar_mandi_dalam"]');
        });
    }

    public function down(): void
    {
        Schema::table('akomodasi', function (Blueprint $table) {
            $table->dropColumn(['kapasitas', 'fasilitas']);
        });
    }
};
