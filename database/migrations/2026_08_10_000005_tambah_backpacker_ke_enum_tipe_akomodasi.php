<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Kolom `tipe` di tabel akomodasi masih enum 3 nilai ('cottage','gazebo','tenda') sejak
// migration awal, padahal AkomodasiController sudah memvalidasi 'in:cottage,gazebo,tenda,backpacker'
// dan form Kelola Akomodasi di frontend sudah menampilkan pilihan "Backpacker".
//
// Akibatnya menyimpan akomodasi bertipe backpacker LOLOS validasi Laravel tapi DITOLAK MySQL
// ("Data truncated for column 'tipe'" / error 500). Migration ini menambahkan 'backpacker'
// ke daftar nilai enum supaya database sinkron dengan controller dan frontend.
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE akomodasi MODIFY COLUMN tipe ENUM('cottage', 'gazebo', 'tenda', 'backpacker') NOT NULL DEFAULT 'tenda'");
    }

    public function down(): void
    {
        // Baris bertipe 'backpacker' tidak punya padanan di enum lama, jadi dikembalikan ke
        // 'tenda' dulu supaya ALTER tidak gagal karena ada data di luar daftar nilai enum.
        DB::statement("UPDATE akomodasi SET tipe = 'tenda' WHERE tipe = 'backpacker'");
        DB::statement("ALTER TABLE akomodasi MODIFY COLUMN tipe ENUM('cottage', 'gazebo', 'tenda') NOT NULL DEFAULT 'tenda'");
    }
};
