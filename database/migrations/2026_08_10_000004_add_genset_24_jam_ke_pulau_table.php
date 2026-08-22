<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Genset tiap pulau ga selalu nyala 24 jam -- ada yang memang 24 jam penuh, ada juga yang
// cuma dinyalain berjadwal (mis. kalau lagi sepi kunjungan). Kolom boolean ini jadi penanda
// status genset_24_jam; kalau false, jam_operasional_genset (kolom lama) dipakai sebagai
// jadwalnya. Kolom baru di tabel yang sudah ada -- bukan tabel baru.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pulau', function (Blueprint $table) {
            $table->boolean('genset_24_jam')->default(false)->after('jam_operasional_genset')
                ->comment('true = genset menyala 24 jam penuh, false = mengikuti jadwal di jam_operasional_genset');
        });
    }

    public function down(): void
    {
        Schema::table('pulau', function (Blueprint $table) {
            $table->dropColumn('genset_24_jam');
        });
    }
};
