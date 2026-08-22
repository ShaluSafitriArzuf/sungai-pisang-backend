<?php

namespace Database\Seeders;

use App\Models\Manifest;
use App\Models\Notifikasi;
use App\Models\Reservasi;
use App\Models\Ulasan;
use Illuminate\Database\Seeder;

// Bersihin SEMUA reservasi (+ ulasan, notifikasi, manifest turunannya) yang dibuat oleh
// ReservasiDemoSeeder -- termasuk kalau seeder itu kebetulan kejalanin berkali-kali dan
// datanya numpuk dobel. Dikenali lewat bukti_transfer yang PERSIS SAMA dengan gambar dummy
// 1x1 piksel yang dipakai ReservasiDemoSeeder -- reservasi ASLI (diunggah wisatawan lewat
// form beneran, fotonya jauh lebih besar) TIDAK MUNGKIN punya bukti_transfer identik ini,
// jadi aman, tidak akan ikut terhapus.
//
// Cara jalanin (dari folder backend, lewat terminal):
//   php artisan db:seed --class="Database\Seeders\BersihkanDataDemoSeeder"
//
// Setelah ini beres, jalankan ULANG ReservasiDemoSeeder (SEKALI SAJA) buat isi data demo
// yang bersih (10 menunggu_verifikasi, dst -- tidak dobel lagi):
//   php artisan db:seed --class=ReservasiDemoSeeder
class BersihkanDataDemoSeeder extends Seeder
{
    public function run(): void
    {
        $buktiDummy = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

        $reservasiDemo = Reservasi::where('bukti_transfer', $buktiDummy)->get();

        if ($reservasiDemo->isEmpty()) {
            $this->command->info('Tidak ada data demo yang perlu dibersihkan.');
            return;
        }

        $idList = $reservasiDemo->pluck('id');
        $tanggalList = $reservasiDemo->pluck('tanggal_kunjungan')->unique()->map(fn ($t) => $t->toDateString());

        Ulasan::whereIn('reservasi_id', $idList)->delete();
        Notifikasi::whereIn('reservasi_id', $idList)->delete();
        // Catatan: manifest dihapus berdasarkan TANGGAL yang sama dengan reservasi demo yang
        // dihapus. Kalau kebetulan ada reservasi ASLI (bukan demo) di tanggal yang sama, baris
        // manifest tanggal itu ikut kehapus juga -- di tahap testing sekarang harusnya belum
        // ada reservasi asli, tapi kalau nanti sistem sudah dipakai beneran, cek dulu manual
        // sebelum jalanin seeder ini lagi.
        Manifest::whereIn('tanggal', $tanggalList)->delete();
        Reservasi::whereIn('id', $idList)->delete();

        $this->command->info("Selesai. {$idList->count()} reservasi demo (+ ulasan, notifikasi, manifest terkait) sudah dihapus.");
    }
}
