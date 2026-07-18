<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class PerbaikiLokasiPengantar extends Command
{
    // Jalankan sekali: php artisan pengantar:perbaiki-lokasi
    // Update koordinat akun Pengantar Pulau ke lokasi yang benar (data lama masih pakai
    // koordinat perkiraan awal seeding, bukan lokasi asli).
    protected $signature = 'pengantar:perbaiki-lokasi';

    protected $description = 'Update koordinat akun Pengantar Pulau ke lokasi yang benar';

    public function handle(): int
    {
        $jumlah = User::where('role', 'pengantar_pulau')->update([
            'latitude' => -1.11664604555515,
            'longitude' => 100.3868464247046,
            // Foto sementara (placeholder) — nanti diganti sendiri lewat menu Lokasi kalau
            // foto rumah asli sudah ada, tinggal upload ulang lewat "Ganti Foto" di sana.
            'foto' => 'https://images.unsplash.com/photo-1449844908441-8829872d2607?w=700&q=80',
        ]);

        $this->info("Berhasil update lokasi {$jumlah} akun Pengantar Pulau.");

        return self::SUCCESS;
    }
}
