<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['wisatawan', 'pengantar_pulau', 'pengelola_pulau'])
                ->default('wisatawan')
                ->after('email');
            $table->string('no_hp')->nullable()->after('role');

            // Khusus role pengelola_pulau: 1 akun = 1 pulau (5 akun terpisah, sesuai syarat dosen)
            $table->foreignId('pulau_id')->nullable()->after('no_hp')
                ->constrained('pulau')->nullOnDelete();

            // Khusus role pengantar_pulau: lokasi dermaga/rumah untuk peta & rute penjemputan
            $table->decimal('latitude', 10, 7)->nullable()->after('pulau_id');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pulau_id');
            $table->dropColumn(['role', 'no_hp', 'latitude', 'longitude']);
        });
    }
};
