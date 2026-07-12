<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ulasan extends Model
{
    protected $table = 'ulasan';

    protected $fillable = ['user_id', 'pulau_id', 'reservasi_id', 'rating', 'komentar'];

    public function wisatawan()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function pulau()
    {
        return $this->belongsTo(Pulau::class, 'pulau_id');
    }

    public function reservasi()
    {
        return $this->belongsTo(Reservasi::class, 'reservasi_id');
    }
}
