<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Memastikan kolom `tipe` pada tabel akomodasi menerima nilai 'backpacker'.
//
// Riwayat masalahnya: kolom ini dibuat sebagai enum tiga nilai ('cottage','gazebo','tenda')
// pada migrasi awal. Nilai 'backpacker' ditambahkan belakangan lewat dua migrasi terpisah
// yang isinya sama persis — 2026_08_10_000001 dan 2026_08_10_000005 — karena yang kedua
// dibuat tanpa menyadari yang pertama sudah ada.
//
// Duplikat itulah yang jadi jebakannya. Ketika batch migrasi terakhir pernah di-rollback,
// method down() milik 2026_08_10_000005 dijalankan dan mengembalikan enum ke tiga nilai
// semula. Sementara 2026_08_10_000001 tetap tercatat "sudah dijalankan" di tabel migrations,
// sehingga perintah php artisan migrate berikutnya tidak menjalankannya lagi. Hasilnya kode
// tetap mengizinkan tipe backpacker (lihat AkomodasiController: in:cottage,gazebo,tenda,
// backpacker) tetapi MySQL menolaknya dengan galat "Data truncated for column 'tipe'" —
// muncul di layar sebagai "Gagal menyimpan" pada halaman Kelola Akomodasi.
//
// Migrasi ini memakai tanggal baru sehingga pasti berstatus pending dan pasti dijalankan.
// Perintahnya idempoten: aman dijalankan berapa kali pun, dan tidak menyentuh data yang ada.
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE akomodasi MODIFY COLUMN tipe ENUM('cottage', 'gazebo', 'tenda', 'backpacker') NOT NULL DEFAULT 'tenda'");
    }

    // Sengaja dikosongkan. Mengembalikan enum ke tiga nilai justru yang dulu memunculkan
    // masalah ini, dan akan menghapus/mengubah data akomodasi bertipe backpacker yang sudah
    // tersimpan. Membiarkan satu nilai enum tambahan saat rollback tidak merusak apa pun.
    public function down(): void
    {
        //
    }
};
