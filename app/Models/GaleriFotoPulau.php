<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GaleriFotoPulau extends Model
{
    protected $table = 'galeri_foto_pulau';

    protected $fillable = ['pulau_id', 'foto', 'tipe'];

    public function pulau()
    {
        return $this->belongsTo(Pulau::class, 'pulau_id');
    }
}
