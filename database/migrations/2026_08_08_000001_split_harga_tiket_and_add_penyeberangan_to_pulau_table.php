<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tarif tiket masuk ternyata berbeda antara kunjungan One Day Trip dan Menginap, dan tarif
     * penyeberangan kapal juga berbeda tiap pulau (bukan tarif global seperti sebelumnya di
     * config/services.php). Karena itu:
     *  - harga_tiket_masuk  -> diganti nama jadi harga_tiket_masuk_one_day
     *  - ditambah kolom     -> harga_tiket_masuk_menginap
     *  - ditambah kolom     -> harga_penyeberangan (per orang, sekali jalan pulang-pergi)
     */
    public function up(): void
    {
        Schema::table('pulau', function (Blueprint $table) {
            $table->renameColumn('harga_tiket_masuk', 'harga_tiket_masuk_one_day');
        });

        Schema::table('pulau', function (Blueprint $table) {
            $table->decimal('harga_tiket_masuk_menginap', 10, 2)->default(0)->after('harga_tiket_masuk_one_day')
                ->comment('tarif tiket masuk per orang untuk kunjungan menginap');
            $table->decimal('harga_penyeberangan', 10, 2)->default(0)->after('harga_tiket_masuk_menginap')
                ->comment('tarif kapal penyeberangan per orang menuju pulau ini');
        });

        // Data lama: tarif menginap disamakan dulu dengan tarif one day trip, dan tarif
        // penyeberangan diisi nilai global lama (Rp50.000) supaya tidak ada baris bernilai 0.
        // Nilai sebenarnya per pulau diisi ulang lewat seeder atau halaman Kelola Profil Pulau.
        \DB::table('pulau')->update([
            'harga_tiket_masuk_menginap' => \DB::raw('harga_tiket_masuk_one_day'),
            'harga_penyeberangan' => 50000,
        ]);
    }

    public function down(): void
    {
        Schema::table('pulau', function (Blueprint $table) {
            $table->dropColumn(['harga_tiket_masuk_menginap', 'harga_penyeberangan']);
        });

        Schema::table('pulau', function (Blueprint $table) {
            $table->renameColumn('harga_tiket_masuk_one_day', 'harga_tiket_masuk');
        });
    }
};
