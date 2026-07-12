<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manifest', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal');
            $table->foreignId('dibuat_oleh')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('jumlah_wisatawan')->default(0);
            $table->string('file_pdf')->nullable();
            $table->enum('status', ['draft', 'exported'])->default('draft');
            $table->timestamp('exported_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manifest');
    }
};
