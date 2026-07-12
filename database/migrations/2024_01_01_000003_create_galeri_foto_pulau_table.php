<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('galeri_foto_pulau', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pulau_id')->constrained('pulau')->cascadeOnDelete();
            // Foto & video sama-sama diupload langsung dari device (lihat GaleriFotoController@store),
            // disimpan sebagai file asli di storage/app/public/galeri, kolom ini cuma nyimpan URL-nya.
            $table->longText('foto');
            $table->enum('tipe', ['foto', 'video'])->default('foto');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('galeri_foto_pulau');
    }
};
