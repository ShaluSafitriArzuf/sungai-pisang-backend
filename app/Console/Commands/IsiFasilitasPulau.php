<?php

namespace App\Console\Commands;

use App\Models\Pulau;
use Illuminate\Console\Command;

class IsiFasilitasPulau extends Command
{
    // Jalankan sekali: php artisan pulau:isi-fasilitas
    // Isi kolom fasilitas untuk data pulau yang sudah ada (dibuat sebelum kolom ini ditambahkan),
    // supaya tidak perlu centang manual satu-satu lewat Kelola Profil Pulau.
    protected $signature = 'pulau:isi-fasilitas';

    protected $description = 'Isi fasilitas default untuk pulau yang sudah ada di database (data lama sebelum kolom fasilitas ditambahkan)';

    protected array $default = [
        'Pulau Pasumpahan' => ['toilet', 'mushola', 'warung_makan', 'gazebo_umum', 'dermaga', 'spot_foto'],
        'Pulau Sirandah' => ['toilet', 'gazebo_umum', 'dermaga', 'tempat_sampah'],
        'Pulau Ujung Kapuri' => ['dermaga', 'tempat_sampah'],
        'Pulau Pagang' => ['toilet', 'mushola', 'warung_makan', 'dermaga', 'area_parkir', 'spot_foto'],
        'Pulau Pamutusan' => ['toilet', 'gazebo_umum', 'dermaga', 'spot_foto', 'tempat_sampah'],
    ];

    public function handle(): int
    {
        foreach ($this->default as $nama => $fasilitas) {
            $pulau = Pulau::where('nama', $nama)->first();

            if (! $pulau) {
                $this->warn("Lewati: pulau \"{$nama}\" tidak ditemukan.");
                continue;
            }

            $pulau->update(['fasilitas' => $fasilitas]);
            $this->info("Berhasil: {$nama} -> ".implode(', ', $fasilitas));
        }

        return self::SUCCESS;
    }
}
