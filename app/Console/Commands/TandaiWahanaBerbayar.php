<?php

namespace App\Console\Commands;

use App\Models\WahanaKegiatan;
use Illuminate\Console\Command;

class TandaiWahanaBerbayar extends Command
{
    // Jalankan sekali: php artisan wahana:tandai-berbayar
    // Tandai wahana yang sebenarnya dibayar di lokasi (Banana Boat, Donat Boat) untuk data yang
    // sudah ada di database sebelum kolom berbayar_lokasi ditambahkan.
    protected $signature = 'wahana:tandai-berbayar';

    protected $description = 'Tandai wahana yang berbayar di lokasi (Banana Boat, Donat Boat) untuk data lama';

    protected array $namaBerbayar = ['Banana Boat', 'Donat Boat'];

    public function handle(): int
    {
        $jumlah = WahanaKegiatan::whereIn('nama', $this->namaBerbayar)->update(['berbayar_lokasi' => true]);

        $this->info("Berhasil menandai {$jumlah} wahana sebagai \"Bayar di Lokasi\".");

        return self::SUCCESS;
    }
}
