<?php

namespace Database\Seeders;

use App\Models\Akomodasi;
use App\Models\Pulau;
use App\Models\Reservasi;
use App\Models\User;
use Illuminate\Database\Seeder;

// SEEDER SEMENTARA -- untuk menguji tampilan "Akomodasi Penuh" pada Form Reservasi.
//
// Di sistem ini tidak ada tombol/kolom "penuh". Status penuh DIHITUNG:
//     sisa = jumlah_unit - jumlah unit yang sedang dipakai reservasi aktif pada tanggal itu
// (lihat Akomodasi::sisaUnitPadaTanggal). Jadi cara paling wajar membuat sebuah akomodasi
// tampak penuh adalah membuat reservasi yang memakai SELURUH unitnya -- bukan mengubah
// jumlah_unit jadi 0, karena kalau begitu badge-nya berbunyi "0 dari 0 unit tersisa" yang
// terlihat seperti data rusak, bukan seperti kamar yang benar-benar habis dipesan.
//
// Dibuat SATU baris reservasi menginap yang membentang 60 hari ke depan, supaya tanggal
// mana pun yang dipilih saat pengujian tetap terbaca penuh.
//
// Menjalankan:
//   php artisan db:seed --class="Database\Seeders\AkomodasiPenuhSeeder"
// Mengembalikan seperti semula:
//   php artisan db:seed --class="Database\Seeders\AkomodasiPenuhBatalSeeder"
class AkomodasiPenuhSeeder extends Seeder
{
    // Penanda unik supaya baris ini bisa dihapus lagi dengan pasti dan tidak mungkin
    // tertukar dengan reservasi asli mana pun.
    public const PENANDA = 'DATA-UJI-KUOTA-PENUH';

    private const NAMA_PULAU = 'Pulau Pagang';
    private const NAMA_AKOMODASI = 'Cottage Family';

    public function run(): void
    {
        $pulau = Pulau::where('nama', self::NAMA_PULAU)->first();
        if (! $pulau) {
            $this->command->error('Pulau "'.self::NAMA_PULAU.'" tidak ditemukan.');
            return;
        }

        $akomodasi = Akomodasi::where('pulau_id', $pulau->id)
            ->where('nama', self::NAMA_AKOMODASI)
            ->first();

        if (! $akomodasi) {
            $this->command->error('Akomodasi "'.self::NAMA_AKOMODASI.'" di '.$pulau->nama.' tidak ditemukan.');
            $this->command->line('Akomodasi yang ada di pulau ini:');
            foreach (Akomodasi::where('pulau_id', $pulau->id)->pluck('nama') as $n) {
                $this->command->line('  - '.$n);
            }
            return;
        }

        $wisatawan = User::where('role', 'wisatawan')->first();
        if (! $wisatawan) {
            $this->command->error('Tidak ada akun wisatawan di database untuk dipakai sebagai pemesan.');
            return;
        }

        // Kalau sudah pernah dijalankan, jangan menumpuk baris baru.
        if (Reservasi::where('bukti_transfer', self::PENANDA)->exists()) {
            $this->command->warn('Data uji kuota sudah ada sebelumnya -- tidak dibuat ulang.');
            $this->command->info(self::NAMA_AKOMODASI.' sudah dalam keadaan PENUH.');
            return;
        }

        $mulai = now()->startOfDay();
        $selesai = now()->startOfDay()->addDays(60);

        Reservasi::create([
            'user_id' => $wisatawan->id,
            'pulau_id' => $pulau->id,
            'jenis' => 'menginap',
            'akomodasi_id' => $akomodasi->id,
            // Memakai SELURUH unit yang ada, berapa pun jumlahnya.
            'jumlah_unit_dipesan' => max(1, (int) $akomodasi->jumlah_unit),
            'bawa_tenda_sendiri' => false,
            'tanggal_kunjungan' => $mulai->format('Y-m-d'),
            'tanggal_selesai' => $selesai->format('Y-m-d'),
            // Sengaja 1 orang dan biaya 0 supaya statistik kunjungan tidak ikut terganggu.
            'jumlah_orang' => 1,
            'biaya_penyeberangan' => 0,
            'biaya_tiket_masuk' => 0,
            'biaya_akomodasi' => 0,
            'total_bayar' => 0,
            'bukti_transfer' => self::PENANDA,
            // 'valid' dipilih (bukan 'menunggu_verifikasi') supaya baris ini TIDAK muncul di
            // antrean verifikasi Pengantar Pulau dan tidak mengotori screenshot dashboard.
            'status' => 'valid',
            'verified_at' => now(),
        ]);

        $this->command->info('Selesai. "'.$akomodasi->nama.'" di '.$pulau->nama.' sekarang PENUH.');
        $this->command->line('  Seluruh '.$akomodasi->jumlah_unit.' unit terpakai dari '
            .$mulai->format('d M Y').' sampai '.$selesai->format('d M Y').'.');
        $this->command->line('  Pilih tanggal menginap kapan pun dalam rentang itu untuk melihat badge "Penuh".');
        $this->command->warn('JANGAN LUPA kembalikan setelah pengujian selesai:');
        $this->command->warn('  php artisan db:seed --class="Database\Seeders\AkomodasiPenuhBatalSeeder"');
    }
}
