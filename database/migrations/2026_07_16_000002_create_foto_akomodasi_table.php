<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('foto_akomodasi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('akomodasi_id')->constrained('akomodasi')->cascadeOnDelete();
            // Foto TAMBAHAN selain foto utama/cover (kolom akomodasi.foto tetap yang tampil
            // di kartu list). Ini buat foto-foto lain: interior kamar, sudut lain, dsb — supaya
            // bisa digeser (carousel) di halaman Detail Pulau. Diupload asli lewat storage,
            // sama seperti galeri_foto_pulau, bukan base64.
            $table->longText('foto');
            $table->unsignedInteger('urutan')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('foto_akomodasi');
    }
};
