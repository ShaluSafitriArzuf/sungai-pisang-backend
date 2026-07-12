<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WahanaKegiatan extends Model
{
    protected $table = 'wahana_kegiatan';

    protected $fillable = ['pulau_id', 'nama', 'deskripsi', 'aktif', 'berbayar_lokasi'];

    protected function casts(): array
    {
        return ['aktif' => 'boolean', 'berbayar_lokasi' => 'boolean'];
    }

    public function pulau()
    {
        return $this->belongsTo(Pulau::class, 'pulau_id');
    }
}
