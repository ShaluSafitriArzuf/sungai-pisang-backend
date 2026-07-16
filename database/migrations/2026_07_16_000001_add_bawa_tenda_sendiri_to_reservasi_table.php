<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservasi', function (Blueprint $table) {
            // Khusus jenis 'menginap': wisatawan bisa pilih bawa tenda sendiri (gratis, tidak perlu
            // akomodasi berbayar) daripada wajib pilih salah satu akomodasi milik pengelola pulau.
            // Kalau true: akomodasi_id null, biaya_akomodasi 0, dan tidak ikut hitungan sisa unit.
            $table->boolean('bawa_tenda_sendiri')->default(false)->after('akomodasi_id');
        });
    }

    public function down(): void
    {
        Schema::table('reservasi', function (Blueprint $table) {
            $table->dropColumn('bawa_tenda_sendiri');
        });
    }
};
