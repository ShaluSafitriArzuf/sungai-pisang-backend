<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservasi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('pulau_id')->constrained('pulau')->cascadeOnDelete();
            $table->enum('jenis', ['one_day_trip', 'menginap']);
            $table->foreignId('akomodasi_id')->nullable()->constrained('akomodasi')->nullOnDelete();
            $table->date('tanggal_kunjungan');
            $table->unsignedInteger('jumlah_orang');

            // Rincian biaya (per formula: penyeberangan + tiket masuk + akomodasi).
            // Wahana & Aktivitas TIDAK berbayar (murni info), jadi tidak ikut dihitung di sini.
            $table->decimal('biaya_penyeberangan', 10, 2)->default(0);
            $table->decimal('biaya_tiket_masuk', 10, 2)->default(0);
            $table->decimal('biaya_akomodasi', 10, 2)->default(0);
            $table->decimal('total_bayar', 10, 2)->default(0);

            $table->longText('bukti_transfer')->nullable();
            $table->enum('status', [
                'menunggu_verifikasi',
                'valid',
                'ditolak',
                'pengajuan_batal',
                'dibatalkan',
                'selesai',
            ])->default('menunggu_verifikasi');
            $table->text('catatan_penolakan')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();

            // Fitur pengajuan pembatalan (minimal H-2 sebelum tanggal_kunjungan)
            $table->enum('alasan_pembatalan', ['cuaca_buruk', 'sakit_mendadak', 'perubahan_jadwal', 'lainnya'])->nullable();
            $table->text('keterangan_pembatalan')->nullable();
            $table->timestamp('tanggal_pengajuan_batal')->nullable();
            $table->enum('status_pengembalian_dana', ['belum_diproses', 'disetujui', 'ditolak'])->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservasi');
    }
};
