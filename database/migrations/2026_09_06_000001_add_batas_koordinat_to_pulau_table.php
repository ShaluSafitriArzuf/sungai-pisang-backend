<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pulau', function (Blueprint $table) {
            // Batas wilayah pulau hasil digitasi di atas citra satelit oleh Pengelola Pulau,
            // disimpan sebagai larik koordinat [lintang, bujur].
            //
            // Sebelumnya sebuah pulau hanya diwakili SATU TITIK di peta, sehingga wilayahnya
            // tidak tergambar sama sekali. Dengan poligon, sistem menyimpan dua jenis data
            // spasial sekaligus: titik (posisi) dan poligon (batas wilayah).
            $table->json('batas_koordinat')->nullable()->after('longitude');
        });
    }

    public function down(): void
    {
        Schema::table('pulau', function (Blueprint $table) {
            $table->dropColumn('batas_koordinat');
        });
    }
};
