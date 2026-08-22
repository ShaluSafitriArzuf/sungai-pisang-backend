<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Kolom `kapasitas` semula unsignedInteger (cuma bisa angka tunggal, mis. 4). Diubah jadi
// string supaya Pengelola Pulau bisa mengisi rentang muatan (mis. "4-6") untuk unit yang
// kapasitasnya fleksibel — tanpa bikin tabel/kolom baru, cuma ubah tipe kolom yang sudah ada.
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE akomodasi MODIFY COLUMN kapasitas VARCHAR(50) NULL');
    }

    public function down(): void
    {
        // Balik ke integer: nilai berupa rentang (mis. "4-6") tidak bisa dikonversi otomatis,
        // jadi dikosongkan dulu supaya ALTER tidak gagal karena data tidak valid.
        DB::statement("UPDATE akomodasi SET kapasitas = NULL WHERE kapasitas REGEXP '[^0-9]'");
        DB::statement('ALTER TABLE akomodasi MODIFY COLUMN kapasitas INT UNSIGNED NULL');
    }
};
