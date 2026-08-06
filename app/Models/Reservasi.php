<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reservasi extends Model
{
    protected $table = 'reservasi';

    protected $fillable = [
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

    // Jumlah malam menginap (tanggal_kunjungan = check-in, tanggal_selesai = check-out)
    public function jumlahMalam(): int
    {
        if ($this->jenis !== 'menginap' || ! $this->tanggal_selesai) {
            return 0;
        }

        return max(1, $this->tanggal_kunjungan->diffInDays($this->tanggal_selesai));
    }

    // Aturan bisnis: pembatalan hanya boleh diajukan minimal H-2 sebelum tanggal_kunjungan
    public function bisaAjukanBatal(): bool
    {
        if ($this->status !== 'valid') {
            return false;
        }

        return now()->diffInDays($this->tanggal_kunjungan, false) >= 2;
    }
}
