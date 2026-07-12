<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wahana_kegiatan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pulau_id')->constrained('pulau')->cascadeOnDelete();
            $table->string('nama');
            // Murni informasi daya tarik/aktivitas yang ada di pulau (mis. "Bukit Pasumpahan" untuk
            // hiking) — TIDAK berbayar dan TIDAK bisa ditambahkan sebagai biaya di form reservasi.
            $table->text('deskripsi')->nullable();
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wahana_kegiatan');
    }
};
