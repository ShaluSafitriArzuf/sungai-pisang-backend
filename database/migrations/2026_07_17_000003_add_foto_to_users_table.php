<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Khusus pengantar_pulau: foto titik kumpul/rumah/dermaga, supaya wisatawan yang
            // belum pernah ke sana bisa langsung kebayang mau berhenti di mana. Diupload asli
            // lewat storage (sama pola dengan foto_utama pulau, foto akomodasi, dll), bukan base64.
            $table->longText('foto')->nullable()->after('longitude');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('foto');
        });
    }
};
