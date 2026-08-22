<?php

namespace Database\Seeders;

use App\Models\Akomodasi;
use App\Models\Manifest;
use App\Models\Notifikasi;
use App\Models\Pulau;
use App\Models\Reservasi;
use App\Models\Ulasan;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seeder data demo reservasi — dipakai untuk keperluan pengujian dan demonstrasi sidang.
 *
 * Menghasilkan reservasi pada SEMUA pulau dengan SEMUA kombinasi status dan jenis kunjungan,
 * lengkap dengan ulasan, notifikasi, dan manifest, supaya seluruh halaman sistem ada isinya.
 *
 * Cara menjalankan (TIDAK menghapus data lama, hanya menambah):
 *     php artisan db:seed --class=ReservasiDemoSeeder
 *
 * Kalau ingin mengulang dari bersih tanpa menghapus seluruh database:
 *     php artisan db:seed --class=ReservasiDemoSeeder   (jalankan setelah hapus manual)
 */
class ReservasiDemoSeeder extends Seeder
{
    public function run(): void
    {
        $pulauSemua = Pulau::orderBy('id')->get();
        if ($pulauSemua->isEmpty()) {
            $this->command->error('Tabel pulau masih kosong. Jalankan DatabaseSeeder dulu.');
            return;
        }

        // Wisatawan yang dipakai: SEMUA akun berrole wisatawan (termasuk akun Google yang baru
        // didaftarkan). Reservasi dibagi bergiliran ke tiap akun supaya datanya terlihat wajar,
        // tidak menumpuk di satu orang saja.
        $wisatawanSemua = User::where('role', 'wisatawan')->orderBy('id')->get();
        if ($wisatawanSemua->isEmpty()) {
            $this->command->error('Belum ada akun wisatawan. Daftar dulu, atau jalankan DatabaseSeeder.');
            return;
        }

        $pengantar = User::where('role', 'pengantar_pulau')->first();

        $this->command->info('Akun wisatawan terpakai: '.$wisatawanSemua->pluck('email')->implode(', '));

        // Bukti transfer: gambar 1x1 piksel berformat data-URL. Kolom bukti_transfer memang
        // menyimpan base64, jadi isian ini valid dan halaman detail tetap bisa menampilkannya.
        $buktiDummy = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

        // Susunan skenario: tiap baris = 1 reservasi.
        // hariDariSekarang negatif = tanggal sudah lewat, positif = masih akan datang.
        $skenario = [
            ['status' => 'menunggu_verifikasi', 'jenis' => 'one_day_trip', 'hari' => 6,   'orang' => 2],
            ['status' => 'menunggu_verifikasi', 'jenis' => 'menginap',     'hari' => 9,   'orang' => 3, 'malam' => 2],
            ['status' => 'valid',               'jenis' => 'one_day_trip', 'hari' => 5,   'orang' => 4],
            ['status' => 'valid',               'jenis' => 'menginap',     'hari' => 8,   'orang' => 2, 'malam' => 1],
            ['status' => 'valid',               'jenis' => 'menginap',     'hari' => 12,  'orang' => 5, 'tenda' => true, 'malam' => 2],
            ['status' => 'ditolak',             'jenis' => 'one_day_trip', 'hari' => 7,   'orang' => 2,
                'catatan' => 'Foto bukti transfer buram, nominal tidak terbaca. Mohon unggah ulang.'],
            ['status' => 'pengajuan_batal',     'jenis' => 'menginap',     'hari' => 10,  'orang' => 3, 'malam' => 1,
                'alasan' => 'cuaca_buruk'],
            ['status' => 'dibatalkan',          'jenis' => 'one_day_trip', 'hari' => 4,   'orang' => 2,
                'alasan' => 'sakit_mendadak', 'refund' => 'disetujui'],
            ['status' => 'selesai',             'jenis' => 'one_day_trip', 'hari' => -12, 'orang' => 3,
                'rating' => 5, 'komentar' => 'Pulaunya bersih, air lautnya jernih sekali. Pengantarnya ramah dan tepat waktu.'],
            ['status' => 'selesai',             'jenis' => 'menginap',     'hari' => -25, 'orang' => 2, 'malam' => 2,
                'rating' => 4, 'komentar' => 'Suasana malamnya tenang, cocok buat healing. Sayang genset mati agak awal.'],
            ['status' => 'selesai',             'jenis' => 'one_day_trip', 'hari' => -40, 'orang' => 6,
                'rating' => 5, 'komentar' => 'Rombongan kami puas banget. Snorkelingnya seru, ikannya banyak.'],
            ['status' => 'selesai',             'jenis' => 'menginap',     'hari' => -55, 'orang' => 4, 'malam' => 1,
                'tenda' => true, 'rating' => 3, 'komentar' => 'Camping-nya asyik, tapi toiletnya perlu diperbanyak.'],
        ];

        $nomorWisatawan = 0;
        $totalDibuat = 0;
        $tanggalManifest = [];

        foreach ($pulauSemua as $pulau) {
            $akomodasiPulau = Akomodasi::where('pulau_id', $pulau->id)->where('aktif', true)->get();

            foreach ($skenario as $s) {
                $wisatawan = $wisatawanSemua[$nomorWisatawan % $wisatawanSemua->count()];
                $nomorWisatawan++;

                $jenis = $s['jenis'];
                $orang = $s['orang'];
                $pakaiTenda = $s['tenda'] ?? false;

                $tanggalKunjungan = now()->addDays($s['hari'])->startOfDay();
                $malam = $jenis === 'menginap' ? ($s['malam'] ?? 1) : 0;
                $tanggalSelesai = $jenis === 'menginap'
                    ? (clone $tanggalKunjungan)->addDays($malam)
                    : null;

                // Akomodasi hanya untuk menginap yang tidak membawa tenda sendiri
                $akomodasi = null;
                if ($jenis === 'menginap' && ! $pakaiTenda && $akomodasiPulau->isNotEmpty()) {
                    $akomodasi = $akomodasiPulau[$totalDibuat % $akomodasiPulau->count()];
                }

                // Perhitungan biaya mengikuti formula yang sama persis dengan ReservasiController:
                // penyeberangan + tiket masuk + akomodasi
                $biayaPenyeberangan = (float) $pulau->harga_penyeberangan * $orang;
                $biayaTiketMasuk = $pulau->tarifTiketMasuk($jenis) * $orang;
                $biayaAkomodasi = $akomodasi ? (float) $akomodasi->harga_per_malam * $malam : 0;
                $total = $biayaPenyeberangan + $biayaTiketMasuk + $biayaAkomodasi;

                $sudahDiverifikasi = in_array($s['status'], ['valid', 'ditolak', 'pengajuan_batal', 'dibatalkan', 'selesai']);

                $reservasi = Reservasi::create([
                    'user_id' => $wisatawan->id,
                    'pulau_id' => $pulau->id,
                    'jenis' => $jenis,
                    'akomodasi_id' => $akomodasi?->id,
                    'bawa_tenda_sendiri' => $jenis === 'menginap' ? $pakaiTenda : false,
                    'tanggal_kunjungan' => $tanggalKunjungan->toDateString(),
                    'tanggal_selesai' => $tanggalSelesai?->toDateString(),
                    'jumlah_orang' => $orang,
                    'biaya_penyeberangan' => $biayaPenyeberangan,
                    'biaya_tiket_masuk' => $biayaTiketMasuk,
                    'biaya_akomodasi' => $biayaAkomodasi,
                    'total_bayar' => $total,
                    'bukti_transfer' => $buktiDummy,
                    'status' => $s['status'],
                    'catatan_penolakan' => $s['catatan'] ?? null,
                    'verified_by' => $sudahDiverifikasi ? $pengantar?->id : null,
                    'verified_at' => $sudahDiverifikasi ? (clone $tanggalKunjungan)->subDays(3) : null,
                    'alasan_pembatalan' => $s['alasan'] ?? null,
                    'keterangan_pembatalan' => null,
                    'tanggal_pengajuan_batal' => isset($s['alasan']) ? (clone $tanggalKunjungan)->subDays(3) : null,
                    'status_pengembalian_dana' => $s['refund'] ?? (($s['status'] === 'pengajuan_batal') ? 'belum_diproses' : null),
                ]);

                $totalDibuat++;

                if ($s['status'] === 'valid') {
                    $tanggalManifest[$tanggalKunjungan->toDateString()] =
                        ($tanggalManifest[$tanggalKunjungan->toDateString()] ?? 0) + $orang;
                }

                // Ulasan hanya untuk reservasi berstatus selesai (sesuai aturan sistem)
                if ($s['status'] === 'selesai' && isset($s['rating'])) {
                    Ulasan::create([
                        'user_id' => $wisatawan->id,
                        'pulau_id' => $pulau->id,
                        'reservasi_id' => $reservasi->id,
                        'rating' => $s['rating'],
                        'komentar' => $s['komentar'],
                    ]);
                }

                // Notifikasi mengikuti status akhir reservasi
                $notif = match ($s['status']) {
                    'menunggu_verifikasi' => ['Reservasi Terkirim', 'Reservasi kamu ke '.$pulau->nama.' sedang menunggu verifikasi Pengantar Pulau.', 'reservasi_baru'],
                    'valid' => ['Reservasi Anda Valid', 'Bukti transfer Anda telah diverifikasi. Sampai jumpa di hari kunjungan!', 'verifikasi_valid'],
                    'ditolak' => ['Reservasi Anda Ditolak', 'Bukti transfer Anda ditolak. Ketuk notifikasi ini untuk unggah ulang bukti transfer.', 'verifikasi_ditolak'],
                    'pengajuan_batal' => ['Pengajuan Pembatalan Terkirim', 'Pengajuan pembatalan kamu sedang diproses Pengantar Pulau.', 'pembatalan_diajukan'],
                    'dibatalkan' => ['Pembatalan Disetujui', 'Pengajuan pembatalan Anda telah disetujui.', 'pembatalan_disetujui'],
                    'selesai' => ['Perjalanan Selesai', 'Kunjunganmu ke '.$pulau->nama.' sudah selesai. Yuk beri ulasan pengalamanmu!', 'reservasi_selesai'],
                    default => null,
                };

                if ($notif) {
                    Notifikasi::create([
                        'user_id' => $wisatawan->id,
                        'reservasi_id' => $reservasi->id,
                        'judul' => $notif[0],
                        'pesan' => $notif[1],
                        'tipe' => $notif[2],
                        'is_read' => in_array($s['status'], ['selesai', 'dibatalkan']),
                    ]);
                }

                // Reservasi menunggu_verifikasi di dunia nyata otomatis memberi tahu akun
                // Pengantar Pulau (lihat ReservasiController::store()) -- disamakan di sini
                // supaya data demo juga senyata mungkin dan notifikasi Pengantar Pulau tidak
                // kosong melompong pas demo/sidang.
                if ($s['status'] === 'menunggu_verifikasi' && $pengantar) {
                    $jenisLabel = $jenis === 'menginap' ? 'Menginap' : 'One Day Trip';
                    Notifikasi::create([
                        'user_id' => $pengantar->id,
                        'reservasi_id' => $reservasi->id,
                        'judul' => 'Reservasi Baru Menunggu Verifikasi',
                        'pesan' => "{$wisatawan->name} mengajukan reservasi {$jenisLabel} ke {$pulau->nama} untuk tanggal {$tanggalKunjungan->format('d-m-Y')}.",
                        'tipe' => 'reservasi_baru',
                    ]);
                }
            }
        }

        // Manifest untuk tanggal-tanggal yang punya reservasi valid
        if ($pengantar) {
            foreach ($tanggalManifest as $tanggal => $jumlah) {
                Manifest::updateOrCreate(
                    ['tanggal' => $tanggal],
                    [
                        'dibuat_oleh' => $pengantar->id,
                        'jumlah_wisatawan' => $jumlah,
                        'status' => 'draft',
                    ]
                );
            }
        }

        $this->command->info("Selesai. {$totalDibuat} reservasi demo dibuat pada {$pulauSemua->count()} pulau.");
        $this->command->info('Rincian per pulau: '.count($skenario).' reservasi (semua status dan jenis kunjungan).');
        $this->command->info('Manifest dibuat untuk '.count($tanggalManifest).' tanggal keberangkatan.');
    }
}
