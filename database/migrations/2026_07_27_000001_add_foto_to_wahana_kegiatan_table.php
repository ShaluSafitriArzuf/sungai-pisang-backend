<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wahana_kegiatan', function (Blueprint $table) {
            // Sebelumnya wahana cuma pakai ikon tebakan dari nama (lihat ikonWahana() di
            // frontend). Kolom ini menyimpan URL foto asli yang diunggah Pengelola Pulau,
            // sama seperti pola akomodasi.foto — kalau kosong, frontend tetap fallback ke ikon.
            $table->longText('foto')->nullable()->after('deskripsi');
        });
    }

    public function down(): void
    {
        Schema::table('wahana_kegiatan', function (Blueprint $table) {
            $table->dropColumn('foto');
        });
    }
};
