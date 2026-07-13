<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservasi', function (Blueprint $table) {
            // Khusus jenis 'menginap': tanggal_kunjungan = tanggal check-in,
            // tanggal_selesai = tanggal check-out. Untuk 'one_day_trip' tetap null.
            $table->date('tanggal_selesai')->nullable()->after('tanggal_kunjungan');
        });
    }

    public function down(): void
    {
        Schema::table('reservasi', function (Blueprint $table) {
            $table->dropColumn('tanggal_selesai');
        });
    }
};
