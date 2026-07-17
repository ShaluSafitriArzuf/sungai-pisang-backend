<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wahana_kegiatan', function (Blueprint $table) {
            // Beberapa wahana (mis. Banana Boat, Donat Boat) sebenarnya dibayar tunai LANGSUNG ke
            // operator di lokasi, bukan gratis dan bukan lewat sistem reservasi ini. Field ini
            // cuma penanda informasi buat wisatawan, harganya tidak diatur/dihitung di sistem
            // (karena biasanya nego langsung / beda-beda per operator), makanya tetap tidak ada
            // kolom harga — cuma status "gratis" vs "bayar di lokasi".
            $table->boolean('berbayar_lokasi')->default(false)->after('deskripsi');
        });
    }

    public function down(): void
    {
        Schema::table('wahana_kegiatan', function (Blueprint $table) {
            $table->dropColumn('berbayar_lokasi');
        });
    }
};
