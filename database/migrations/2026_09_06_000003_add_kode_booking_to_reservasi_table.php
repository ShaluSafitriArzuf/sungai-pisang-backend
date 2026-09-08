<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservasi', function (Blueprint $table) {
            // Kode pemesanan yang tercetak besar pada tiket, berpola SP-TTBBHH-NNNN
            // (SP = Sungai Pisang, tanggal kunjungan, nomor urut reservasi). Dipakai
            // Pengantar Pulau untuk mencocokkan tiket dengan data di manifest.
            $table->string('kode_booking', 24)->nullable()->unique()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('reservasi', function (Blueprint $table) {
            $table->dropUnique(['kode_booking']);
            $table->dropColumn('kode_booking');
        });
    }
};
