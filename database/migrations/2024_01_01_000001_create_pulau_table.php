<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pulau', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            // longText karena foto disimpan sebagai base64 data-URL (upload langsung dari galeri/device,
            // tanpa perlu storage disk terpisah) — sama seperti pola bukti_transfer di tabel reservasi.
            $table->longText('foto_utama')->nullable();
            $table->decimal('luas', 10, 2)->nullable()->comment('luas pulau dalam hektar');
            $table->text('deskripsi')->nullable();
            $table->string('badge')->nullable()->comment('contoh: Snorkeling, Camping');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('harga_tiket_masuk', 10, 2)->default(0);
            $table->string('jam_operasional_genset')->nullable()->comment('info teks, bukan kontrol otomatis');
            $table->text('regulasi')->nullable()->comment('tata tertib pulau');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pulau');
    }
};
