<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sebelumnya satu reservasi hanya bisa memesan satu unit akomodasi, sehingga rombongan
     * yang jumlahnya melebihi kapasitas satu unit tidak dapat dilayani sistem. Kolom
     * jumlah_unit_dipesan memungkinkan satu reservasi memesan beberapa unit sekaligus,
     * misalnya rombongan 10 orang memesan 3 unit cottage berkapasitas 4 orang.
     *
     * Nilai default 1 dipakai supaya seluruh reservasi lama tetap valid dan perhitungan
     * ketersediaan unit tidak berubah untuk data yang sudah ada.
     */
    public function up(): void
    {
        Schema::table('reservasi', function (Blueprint $table) {
            $table->unsignedInteger('jumlah_unit_dipesan')->default(1)->after('akomodasi_id')
                ->comment('jumlah unit akomodasi yang dipesan dalam satu reservasi');
        });
    }

    public function down(): void
    {
        Schema::table('reservasi', function (Blueprint $table) {
            $table->dropColumn('jumlah_unit_dipesan');
        });
    }
};
