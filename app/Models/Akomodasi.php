<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Akomodasi extends Model
{
    protected $table = 'akomodasi';

    protected $fillable = ['pulau_id', 'nama', 'tipe', 'harga_per_malam', 'jumlah_unit', 'kapasitas', 'foto', 'deskripsi', 'fasilitas', 'aktif', 'tiket_termasuk'];

    protected function casts(): array
    {
        return [
            'aktif' => 'boolean',
            'fasilitas' => 'array',
            'tiket_termasuk' => 'boolean',
        ];
    }

    // Kolom kapasitas disimpan bebas sebagai teks (mis. "4" atau rentang "4-6") supaya
    // Pengelola Pulau bisa isi muatan yang fleksibel. Dipakai ReservasiController buat hitung
    // berapa orang yang "tertanggung" 1 unit akomodasi ini kalau tiket_termasuk aktif — diambil
    // angka TERBESAR yang ditemukan (rentang "4-6" -> 6), supaya rombongan sampai batas maksimal
    // kapasitas tetap dianggap sudah termasuk tiket masuknya.
    public function kapasitasAngkaMaksimal(): int
    {
        preg_match_all('/\d+/', (string) $this->kapasitas, $matches);
        $angka = array_map('intval', $matches[0] ?? []);

        return $angka ? max($angka) : 0;
    }

    public function pulau()
    {
        return $this->belongsTo(Pulau::class, 'pulau_id');
    }

    public function reservasi()
    {
        return $this->hasMany(Reservasi::class, 'akomodasi_id');
    }

    // Foto TAMBAHAN selain foto utama/cover (kolom `foto` di atas) — dipakai carousel di
    // Detail Pulau supaya wisatawan bisa geser lihat interior/sudut lain akomodasi ini.
    public function fotoTambahan()
    {
        return $this->hasMany(FotoAkomodasi::class, 'akomodasi_id')->orderBy('urutan');
    }

    // Berapa unit yang sedang dipakai reservasi aktif (menunggu_verifikasi/valid) pada 1 tanggal malam tertentu.
    // "Aktif" artinya reservasi itu masih menghitung sebagai terpakai; begitu ditolak/dibatalkan/lewat
    // tanggal check-out, reservasi itu otomatis tidak terhitung lagi (tidak perlu proses "kembalikan kuota").
    //
    // PENTING: dijumlahkan dari kolom jumlah_unit_dipesan, BUKAN dihitung per baris reservasi,
    // karena satu reservasi bisa memesan lebih dari satu unit sekaligus (rombongan besar).
    public function unitTerpakaiPadaTanggal(string $tanggal): int
    {
        return (int) $this->reservasi()
            ->whereIn('status', ['menunggu_verifikasi', 'valid'])
            ->where('tanggal_kunjungan', '<=', $tanggal)
            ->where('tanggal_selesai', '>', $tanggal)
            ->sum('jumlah_unit_dipesan');
    }

    // Sisa unit tersedia pada 1 tanggal malam tertentu.
    public function sisaUnitPadaTanggal(string $tanggal): int
    {
        return max(0, $this->jumlah_unit - $this->unitTerpakaiPadaTanggal($tanggal));
    }

    // Sisa unit tersedia untuk seluruh rentang menginap (check-in inklusif s/d check-out eksklusif).
    // Diambil nilai TERKECIL di antara semua malam, karena kalau salah satu malam penuh,
    // seluruh rentang tidak bisa dipesan.
    public function sisaUnitUntukRentang(string $checkin, string $checkout): int
    {
        $cursor = \Carbon\Carbon::parse($checkin);
        $akhir = \Carbon\Carbon::parse($checkout);
        $sisaMin = $this->jumlah_unit;

        while ($cursor->lt($akhir)) {
            $sisaMin = min($sisaMin, $this->sisaUnitPadaTanggal($cursor->format('Y-m-d')));
            $cursor->addDay();
        }

        return max(0, $sisaMin);
    }
}
