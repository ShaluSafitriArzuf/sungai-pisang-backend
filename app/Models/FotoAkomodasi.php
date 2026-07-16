<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FotoAkomodasi extends Model
{
    protected $table = 'foto_akomodasi';

    protected $fillable = [
        'akomodasi_id',
        'foto',
        'urutan',
    ];

    public function akomodasi()
    {
        return $this->belongsTo(Akomodasi::class, 'akomodasi_id');
    }
}
