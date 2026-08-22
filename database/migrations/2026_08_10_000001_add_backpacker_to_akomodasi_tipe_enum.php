<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Tipe akomodasi sebelumnya cuma cottage/gazebo/tenda, padahal beberapa pulau
     * (mis. Pulau Pagang) punya akomodasi backpacker/dorm — unit yang dipakai
     * bersama beberapa tamu sekaligus dengan harga dihitung per orang, beda dari
     * cottage/gazebo/tenda yang disewa penuh per unit. Tambah 'backpacker' ke enum.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE akomodasi MODIFY COLUMN tipe ENUM('cottage','gazebo','tenda','backpacker') NOT NULL DEFAULT 'tenda'");
    }

    public function down(): void
    {
        DB::statement("UPDATE akomodasi SET tipe = 'tenda' WHERE tipe = 'backpacker'");
        DB::statement("ALTER TABLE akomodasi MODIFY COLUMN tipe ENUM('cottage','gazebo','tenda') NOT NULL DEFAULT 'tenda'");
    }
};
