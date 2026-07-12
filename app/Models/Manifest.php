<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Manifest extends Model
{
    protected $table = 'manifest';

    protected $fillable = [
        'tanggal',
        'dibuat_oleh',
        'jumlah_wisatawan',
        'file_pdf',
        'status',
        'exported_at',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'exported_at' => 'datetime',
        ];
    }

    public function pembuat()
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }
}
