<?php

namespace Database\Seeders;

use App\Models\Notifikasi;
use App\Models\Reservasi;
use App\Models\Ulasan;
use Illuminate\Database\Seeder;

// Pasangan pembatal dari AkomodasiPenuhSeeder -- mengembalikan akomodasi ke keadaan semula.
//
// Hanya menghapus baris yang penandanya persis sama dengan yang dibuat seeder tersebut,
// jadi tidak mungkin menyentuh reservasi asli milik wisatawan.
//
// Menjalankan:
//   php artisan db:seed --class="Database\Seeders\AkomodasiPenuhBatalSeeder"
class AkomodasiPenuhBatalSeeder extends Seeder
{
    public function run(): void
    {
        $data = Reservasi::where('bukti_transfer', AkomodasiPenuhSeeder::PENANDA)->get();

        if ($data->isEmpty()) {
            $this->command->info('Tidak ada data uji kuota yang perlu dihapus -- akomodasi sudah normal.');
            return;
        }

        $idList = $data->pluck('id');

        // Untuk jaga-jaga kalau sempat ada turunan yang menempel pada baris ini.
        Ulasan::whereIn('reservasi_id', $idList)->delete();
        Notifikasi::whereIn('reservasi_id', $idList)->delete();
        Reservasi::whereIn('id', $idList)->delete();

        $this->command->info('Selesai. '.$idList->count().' data uji kuota dihapus.');
        $this->command->info('Akomodasi sudah kembali TERSEDIA seperti semula.');
    }
}
