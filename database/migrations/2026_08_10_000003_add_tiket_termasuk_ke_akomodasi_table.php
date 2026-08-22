<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Sebagian akomodasi (mis. penginapan yang sudah mematok harga all-in) sudah termasuk tiket
// masuk pulau dalam harga per malamnya — bukan cuma penyeberangan. Kolom boolean ini jadi
// penanda supaya ReservasiController tidak menghitung tiket masuk lagi untuk wisatawan yang
// tertampung kapasitas akomodasi tsb (lihat Akomodasi::kapasitasAngkaMaksimal()).
// Kolom baru di tabel yang sudah ada — bukan tabel baru.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('akomodasi', function (Blueprint $table) {
            $table->boolean('tiket_termasuk')->default(false)->after('fasilitas')
                ->comment('true = harga akomodasi ini sudah termasuk tiket masuk pulau, wisatawan cukup bayar penyeberangan');
        });
    }

    public function down(): void
    {
        Schema::table('akomodasi', function (Blueprint $table) {
            $table->dropColumn('tiket_termasuk');
        });
    }
};
