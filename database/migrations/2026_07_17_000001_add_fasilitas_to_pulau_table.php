<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pulau', function (Blueprint $table) {
            // Daftar fasilitas yang tersedia di pulau ini (toilet, mushola, warung makan, dll),
            // disimpan sebagai array JSON berisi key fasilitas — lihat utils/tampilanFasilitas.js
            // di frontend untuk daftar key & labelnya.
            $table->json('fasilitas')->nullable()->after('badge');
        });
    }

    public function down(): void
    {
        Schema::table('pulau', function (Blueprint $table) {
            $table->dropColumn('fasilitas');
        });
    }
};
