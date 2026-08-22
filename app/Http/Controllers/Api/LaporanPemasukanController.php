<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reservasi;
use App\Support\PenulisXlsx;
use Illuminate\Http\Request;

/**
 * Laporan pemasukan Pengantar Pulau.
 *
 * BATASAN YANG DISENGAJA — yang dilaporkan HANYA ongkos kapal (biaya_penyeberangan).
 *
 * Satu reservasi memuat tiga komponen biaya: penyeberangan, tiket masuk pulau, dan akomodasi.
 * Ketiganya memang dibayar sekaligus lewat satu transfer, tetapi hanya penyeberangan yang
 * benar-benar menjadi pendapatan Pengantar Pulau. Tiket masuk dan akomodasi adalah hak
 * Pengelola Pulau yang kebetulan dititipkan lewat transfer yang sama, lalu diteruskan di luar
 * sistem. Kalau ketiganya dijumlahkan dan disebut "pemasukan Pengantar Pulau", angkanya
 * menjadi jauh lebih besar daripada pendapatan yang sebenarnya.
 *
 * STATUS YANG DIHITUNG — hanya 'valid' dan 'selesai'.
 *   - menunggu_verifikasi : uang diklaim masuk tapi bukti transfernya belum diperiksa
 *   - ditolak             : bukti transfer tidak sah, uangnya tidak pernah ada
 *   - pengajuan_batal     : masih diproses, belum jelas jadi atau tidak
 *   - dibatalkan          : perjalanan batal; ditampilkan terpisah sebagai catatan, tidak
 *                           ikut dijumlahkan, karena dananya dikembalikan ke wisatawan
 *
 * DASAR PENGELOMPOKAN BULAN — memakai tanggal_kunjungan, bukan tanggal pemesanan, karena yang
 * ingin diketahui adalah pemasukan dari perjalanan yang berangkat pada bulan tersebut.
 */
class LaporanPemasukanController extends Controller
{
    /** Status yang dianggap sebagai pemasukan yang benar-benar diterima. */
    private const STATUS_DIHITUNG = ['valid', 'selesai'];

    private const NAMA_BULAN = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];

    /** GET /api/pengantar/laporan-pemasukan?bulan=YYYY-MM */
    public function index(Request $request)
    {
        [$tahun, $bulan] = $this->baca($request);

        return response()->json([
            'bulan' => sprintf('%04d-%02d', $tahun, $bulan),
            'label_bulan' => self::NAMA_BULAN[$bulan].' '.$tahun,
            'ringkasan' => $this->ringkasan($tahun, $bulan),
            'rincian' => $this->rincian($tahun, $bulan),
            'rekap_tahun' => $this->rekapTahun($tahun),
        ]);
    }

    /** GET /api/pengantar/laporan-pemasukan/export?bulan=YYYY-MM — mengunduh berkas Excel. */
    public function export(Request $request)
    {
        [$tahun, $bulan] = $this->baca($request);

        $labelBulan = self::NAMA_BULAN[$bulan].' '.$tahun;
        $ringkasan = $this->ringkasan($tahun, $bulan);
        $rincian = $this->rincian($tahun, $bulan);
        $rekap = $this->rekapTahun($tahun);

        $xlsx = new PenulisXlsx();

        // ── Lembar 1: Ringkasan ──────────────────────────────────────────────
        $barisRingkasan = [
            [['LAPORAN PEMASUKAN PENGANTAR PULAU', 'judul']],
            [['Wisata Bahari Sungai Pisang', 'tebal']],
            [['Periode: '.$labelBulan]],
            [['Komponen yang dilaporkan: ongkos kapal penyeberangan']],
            [],
            [['RINGKASAN BULAN INI', 'tebal']],
            [['Keterangan', 'kepala'], ['Nilai', 'kepala']],
            [['Jumlah reservasi berangkat', 'teks'], [$ringkasan['jumlah_reservasi'], 'angka']],
            [['Jumlah wisatawan diantar', 'teks'], [$ringkasan['jumlah_wisatawan'], 'angka']],
            [['Total pemasukan ongkos kapal', 'total'], [$ringkasan['total_ongkos_kapal'], 'totalRupiah']],
            [],
            [['Reservasi dibatalkan (tidak dihitung)', 'teks'], [$ringkasan['jumlah_dibatalkan'], 'angka']],
            [],
            [['REKAP SELURUH BULAN TAHUN '.$tahun, 'tebal']],
            [['Bulan', 'kepala'], ['Reservasi', 'kepala'], ['Wisatawan', 'kepala'], ['Ongkos Kapal', 'kepala']],
        ];

        $totalTahun = 0;
        $totalReservasiTahun = 0;
        $totalOrangTahun = 0;

        foreach ($rekap as $r) {
            $barisRingkasan[] = [
                [$r['label'], 'teks'],
                [$r['jumlah_reservasi'], 'angka'],
                [$r['jumlah_wisatawan'], 'angka'],
                [$r['total_ongkos_kapal'], 'rupiah'],
            ];
            $totalTahun += $r['total_ongkos_kapal'];
            $totalReservasiTahun += $r['jumlah_reservasi'];
            $totalOrangTahun += $r['jumlah_wisatawan'];
        }

        $barisRingkasan[] = [
            ['TOTAL '.$tahun, 'total'],
            [$totalReservasiTahun, 'total'],
            [$totalOrangTahun, 'total'],
            [$totalTahun, 'totalRupiah'],
        ];

        $xlsx->tambahLembar('Ringkasan', $barisRingkasan, [34, 16, 16, 20]);

        // ── Lembar 2: Rincian per reservasi ──────────────────────────────────
        $barisRincian = [
            [['RINCIAN RESERVASI — '.$labelBulan, 'judul']],
            [],
            [
                ['No', 'kepala'],
                ['Tanggal Berangkat', 'kepala'],
                ['Nama Pemesan', 'kepala'],
                ['Pulau Tujuan', 'kepala'],
                ['Jenis Kunjungan', 'kepala'],
                ['Jumlah Orang', 'kepala'],
                ['Ongkos Kapal', 'kepala'],
                ['Status', 'kepala'],
            ],
        ];

        foreach ($rincian as $i => $r) {
            $barisRincian[] = [
                [$i + 1, 'angka'],
                [$r['tanggal_kunjungan'], 'angka'],
                [$r['nama_pemesan'], 'teks'],
                [$r['pulau'], 'teks'],
                [$r['jenis'], 'teks'],
                [$r['jumlah_orang'], 'angka'],
                [$r['ongkos_kapal'], 'rupiah'],
                [$r['status'], 'teks'],
            ];
        }

        if ($rincian === []) {
            $barisRincian[] = [[null, 'teks'], [null, 'teks'], ['Belum ada reservasi pada bulan ini.', 'teks'],
                [null, 'teks'], [null, 'teks'], [null, 'teks'], [null, 'teks'], [null, 'teks']];
        } else {
            $barisRincian[] = [
                [null, 'total'],
                [null, 'total'],
                ['TOTAL', 'total'],
                [null, 'total'],
                [null, 'total'],
                [$ringkasan['jumlah_wisatawan'], 'total'],
                [$ringkasan['total_ongkos_kapal'], 'totalRupiah'],
                [null, 'total'],
            ];
        }

        $xlsx->tambahLembar('Rincian', $barisRincian, [6, 18, 28, 20, 18, 14, 18, 14]);

        $namaBerkas = sprintf('Laporan-Pemasukan-Pengantar-Pulau-%04d-%02d.xlsx', $tahun, $bulan);

        return response($xlsx->jadikanBerkas(), 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$namaBerkas.'"',
            // Tanpa header ini, nama berkasnya tidak terbaca oleh JavaScript di frontend
            // karena Content-Disposition bukan header yang diizinkan secara bawaan.
            'Access-Control-Expose-Headers' => 'Content-Disposition',
        ]);
    }

    // ── Bagian dalam ─────────────────────────────────────────────────────────

    /** @return array{0:int, 1:int} tahun dan bulan yang diminta */
    private function baca(Request $request): array
    {
        $request->validate([
            'bulan' => ['nullable', 'regex:/^\d{4}-\d{2}$/'],
        ], [
            'bulan.regex' => 'Format bulan harus TTTT-BB, contohnya 2026-08.',
        ]);

        $nilai = $request->query('bulan');

        if (! $nilai) {
            return [(int) now()->format('Y'), (int) now()->format('n')];
        }

        [$tahun, $bulan] = array_map('intval', explode('-', $nilai));

        // Bulan di luar 1–12 akan membuat NAMA_BULAN gagal, jadi dijepit di sini.
        $bulan = max(1, min(12, $bulan));

        return [$tahun, $bulan];
    }

    private function dasarKueri(int $tahun, ?int $bulan = null)
    {
        $kueri = Reservasi::whereIn('status', self::STATUS_DIHITUNG)
            ->whereYear('tanggal_kunjungan', $tahun);

        if ($bulan !== null) {
            $kueri->whereMonth('tanggal_kunjungan', $bulan);
        }

        return $kueri;
    }

    private function ringkasan(int $tahun, int $bulan): array
    {
        $kueri = $this->dasarKueri($tahun, $bulan);

        $dibatalkan = Reservasi::where('status', 'dibatalkan')
            ->whereYear('tanggal_kunjungan', $tahun)
            ->whereMonth('tanggal_kunjungan', $bulan)
            ->count();

        return [
            'jumlah_reservasi' => (int) $kueri->clone()->count(),
            'jumlah_wisatawan' => (int) $kueri->clone()->sum('jumlah_orang'),
            'total_ongkos_kapal' => (int) $kueri->clone()->sum('biaya_penyeberangan'),
            'jumlah_dibatalkan' => $dibatalkan,
        ];
    }

    private function rincian(int $tahun, int $bulan): array
    {
        return $this->dasarKueri($tahun, $bulan)
            ->with(['wisatawan:id,name', 'pulau:id,nama'])
            ->orderBy('tanggal_kunjungan')
            ->orderBy('id')
            ->get()
            ->map(fn (Reservasi $r) => [
                'id' => $r->id,
                'tanggal_kunjungan' => $r->tanggal_kunjungan?->format('d-m-Y'),
                'nama_pemesan' => $r->wisatawan?->name ?? '-',
                'pulau' => $r->pulau?->nama ?? '-',
                'jenis' => $r->jenis === 'one_day_trip' ? 'One Day Trip' : 'Menginap',
                'jumlah_orang' => (int) $r->jumlah_orang,
                'ongkos_kapal' => (int) $r->biaya_penyeberangan,
                'status' => $r->status === 'selesai' ? 'Selesai' : 'Valid',
            ])
            ->all();
    }

    /**
     * Rekap 12 bulan pada satu tahun, dipakai untuk tabel perbandingan antarbulan.
     *
     * Pengelompokan per bulan dikerjakan di PHP, bukan lewat GROUP BY MONTH(...) di SQL.
     * Fungsi MONTH() hanya ada di MySQL — dengan mengelompokkan di PHP, laporan ini tetap
     * berjalan andai basis datanya diganti (misalnya SQLite saat pengujian). Jumlah reservasi
     * dalam setahun untuk satu armada masih jauh di bawah batas yang membuat cara ini berat.
     */
    private function rekapTahun(int $tahun): array
    {
        $kosong = ['jumlah_reservasi' => 0, 'jumlah_wisatawan' => 0, 'total_ongkos_kapal' => 0];
        $perBulan = array_fill(1, 12, $kosong);

        $this->dasarKueri($tahun)
            ->get(['tanggal_kunjungan', 'jumlah_orang', 'biaya_penyeberangan'])
            ->each(function (Reservasi $r) use (&$perBulan) {
                $b = (int) $r->tanggal_kunjungan->format('n');
                $perBulan[$b]['jumlah_reservasi']++;
                $perBulan[$b]['jumlah_wisatawan'] += (int) $r->jumlah_orang;
                $perBulan[$b]['total_ongkos_kapal'] += (int) $r->biaya_penyeberangan;
            });

        $hasil = [];

        // Semua bulan tetap ditampilkan walau nilainya nol, supaya tabel perbandingannya
        // punya 12 baris tetap dan bulan yang kosong terlihat jelas sebagai kosong.
        for ($b = 1; $b <= 12; $b++) {
            $hasil[] = ['bulan' => $b, 'label' => self::NAMA_BULAN[$b]] + $perBulan[$b];
        }

        return $hasil;
    }
}
