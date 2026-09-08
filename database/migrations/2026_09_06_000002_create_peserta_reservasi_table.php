<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Sebelumnya sebuah reservasi hanya menyimpan ANGKA jumlah_orang, sehingga manifest
        // penumpang cuma memuat nama pemesan dan jumlahnya. Untuk angkutan penumpang laut hal
        // itu tidak memadai: bila terjadi kecelakaan, identitas peserta selain pemesan tidak
        // diketahui. Tabel ini menyimpan identitas setiap peserta satu per satu.
        Schema::create('peserta_reservasi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservasi_id')->constrained('reservasi')->cascadeOnDelete();
            $table->string('nama');
            // Nomor KTP untuk dewasa, atau nomor Kartu Identitas Anak bila ada. Dibuat nullable
            // karena anak-anak belum tentu memilikinya; kewajiban pengisiannya diatur di
            // ReservasiController berdasarkan usia (17 tahun ke atas wajib).
            $table->string('no_identitas', 32)->nullable()->comment('NIK KTP atau nomor KIA');
            $table->enum('jenis_kelamin', ['L', 'P']);
            $table->unsignedTinyInteger('usia');
            $table->string('no_hp', 20)->nullable()->comment('nomor yang dapat dihubungi');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('peserta_reservasi');
    }
};
