<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pulau extends Model
{
    use HasFactory;

    protected $table = 'pulau';

    protected $fillable = [
        'nama',
        'foto_utama',
        'luas',
        'deskripsi',
        'badge',
        'latitude',
        'longitude',
        'batas_koordinat',
        'harga_tiket_masuk_one_day',
        'harga_tiket_masuk_menginap',
        'harga_penyeberangan',
        'jam_operasional_genset',
        'genset_24_jam',
        'regulasi',
        'fasilitas',
    ];

    protected function casts(): array
    {
        return [
            'fasilitas' => 'array',
            'batas_koordinat' => 'array',
            'genset_24_jam' => 'boolean',
        ];
    }

    public function galeriFoto()
    {
        return $this->hasMany(GaleriFotoPulau::class, 'pulau_id');
    }

    public function wahanaKegiatan()
    {
        return $this->hasMany(WahanaKegiatan::class, 'pulau_id');
    }

    public function akomodasi()
    {
        return $this->hasMany(Akomodasi::class, 'pulau_id');
    }

    public function reservasi()
    {
        return $this->hasMany(Reservasi::class, 'pulau_id');
    }

    public function ulasan()
    {
        return $this->hasMany(Ulasan::class, 'pulau_id');
    }

    public function pengelola()
    {
        // Akun pengelola_pulau yang terhubung ke pulau ini (idealnya 1 akun)
        return $this->hasMany(User::class, 'pulau_id');
    }

    public function ratingRataRata(): float
    {
        return round($this->ulasan()->avg('rating') ?? 0, 1);
    }

    // Tarif tiket masuk per orang sesuai jenis kunjungan. Dipakai bersama oleh
    // ReservasiController supaya aturan harga cuma ditulis di satu tempat.
    public function tarifTiketMasuk(string $jenis): float
    {
        return (float) ($jenis === 'menginap'
            ? $this->harga_tiket_masuk_menginap
            : $this->harga_tiket_masuk_one_day);
    }
}
