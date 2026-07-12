<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('akomodasi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pulau_id')->constrained('pulau')->cascadeOnDelete();
            $table->string('nama');
            $table->enum('tipe', ['cottage', 'gazebo', 'tenda'])->default('tenda');
            $table->decimal('harga_per_malam', 10, 2);
            // Jumlah unit akomodasi ini yang tersedia (mis. 10 cottage). Sisa kuota di tanggal
            // tertentu dihitung otomatis dari reservasi aktif yang tanggalnya overlap — lihat
            // Akomodasi::sisaUnitPadaTanggal(). Tidak ada input kuota manual per tanggal.
            $table->unsignedInteger('jumlah_unit')->default(1);
            // longText karena foto disimpan sebagai base64 data-URL (upload langsung dari galeri/device).
            $table->longText('foto')->nullable();
            $table->text('deskripsi')->nullable();
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('akomodasi');
    }
};
