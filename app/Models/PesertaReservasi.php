<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PesertaReservasi extends Model
{
    protected $table = 'peserta_reservasi';

    protected $fillable = [
        'reservasi_id',
        'nama',
        'no_identitas',
        'jenis_kelamin',
        'usia',
        'no_hp',
    ];

    protected function casts(): array
    {
        return [
            'usia' => 'integer',
        ];
    }

    public function reservasi()
    {
        return $this->belongsTo(Reservasi::class, 'reservasi_id');
    }

    public function labelJenisKelamin(): string
    {
        return $this->jenis_kelamin === 'P' ? 'Perempuan' : 'Laki-laki';
    }
}
