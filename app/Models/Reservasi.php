<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reservasi extends Model
{
    protected $table = 'reservasi';

    protected $fillable = [
        'kode_booking',
        'user_id',
        'pulau_id',
        'jenis',
        'akomodasi_id',
        'jumlah_unit_dipesan',
        'bawa_tenda_sendiri',
        'tanggal_kunjungan',
        'tanggal_selesai',
        'jumlah_orang',
        'biaya_penyeberangan',
        'biaya_tiket_masuk',
        'biaya_akomodasi',
        'total_bayar',
        'bukti_transfer',
        'status',
        'catatan_penolakan',
        'verified_by',
        'verified_at',
        'alasan_pembatalan',
        'keterangan_pembatalan',
        'tanggal_pengajuan_batal',
        'status_pengembalian_dana',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_kunjungan' => 'date',
            'tanggal_selesai' => 'date',
            'bawa_tenda_sendiri' => 'boolean',
            'verified_at' => 'datetime',
            'tanggal_pengajuan_batal' => 'datetime',
        ];
    }

    public function wisatawan()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function pulau()
    {
        return $this->belongsTo(Pulau::class, 'pulau_id');
    }

    public function akomodasi()
    {
        return $this->belongsTo(Akomodasi::class, 'akomodasi_id');
    }

    public function verifikator()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function ulasan()
    {
        return $this->hasOne(Ulasan::class, 'reservasi_id');
    }

    // Identitas setiap orang yang ikut dalam reservasi ini. Menjadi dasar manifest
    // penumpang dan daftar peserta yang tercetak pada tiket.
    public function peserta()
    {
        return $this->hasMany(PesertaReservasi::class, 'reservasi_id');
    }

    // Nomor tagihan yang tercetak pada invoice. Diturunkan dari tanggal pembuatan dan
    // nomor urut reservasi, sehingga tidak perlu disimpan sebagai kolom tersendiri.
    public function nomorInvoice(): string
    {
        return 'INV/'.$this->created_at->format('Y/m').'/'.str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
    }

    // Jumlah malam menginap (tanggal_kunjungan = check-in, tanggal_selesai = check-out)
    public function jumlahMalam(): int
    {
        if ($this->jenis !== 'menginap' || ! $this->tanggal_selesai) {
            return 0;
        }

        return max(1, $this->tanggal_kunjungan->diffInDays($this->tanggal_selesai));
    }

    // Nomor kuitansi. Diturunkan dari waktu verifikasi pembayaran, bukan waktu pemesanan,
    // karena kuitansi menyatakan kapan uangnya diterima -- berbeda dengan invoice yang
    // menyatakan kapan tagihannya terbit.
    public function nomorKuitansi(): string
    {
        $waktu = $this->verified_at ?: $this->updated_at;

        return 'KW/'.$waktu->format('Y/m').'/'.str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
    }

    // Selisih HARI KALENDER dari hari ini sampai tanggal kunjungan. Bernilai negatif kalau
    // tanggal kunjungannya sudah lewat.
    //
    // PENTING: dihitung dari awal hari (startOfDay), bukan dari jam sekarang. Sebelumnya
    // perhitungannya memakai now() langsung, dan pada Carbon 3 diffInDays() mengembalikan
    // pecahan -- sehingga reservasi yang tanggal kunjungannya memang H-2 tetap ditolak
    // hanya karena jam saat itu sudah lewat tengah hari (selisihnya terbaca 1,4 hari).
    // Akibatnya aturan yang berjalan lebih ketat daripada yang tertulis di ketentuan.
    public function sisaHariKunjungan(): int
    {
        if (! $this->tanggal_kunjungan) {
            return 0;
        }

        return (int) \Carbon\Carbon::today()->diffInDays(
            \Carbon\Carbon::parse($this->tanggal_kunjungan)->startOfDay(),
            false
        );
    }

    // Aturan bisnis: pembatalan hanya boleh diajukan minimal H-2 sebelum tanggal_kunjungan
    public function bisaAjukanBatal(): bool
    {
        if ($this->status !== 'valid') {
            return false;
        }

        return $this->sisaHariKunjungan() >= 2;
    }
}
