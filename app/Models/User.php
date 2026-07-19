<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'no_hp',
        'pulau_id',
        'latitude',
        'longitude',
        'foto',
        'google_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    // ── Role helpers ─────────────────────────────
    public function isWisatawan(): bool
    {
        return $this->role === 'wisatawan';
    }

    public function isPengantarPulau(): bool
    {
        return $this->role === 'pengantar_pulau';
    }

    public function isPengelolaPulau(): bool
    {
        return $this->role === 'pengelola_pulau';
    }

    // ── Relasi ───────────────────────────────────
    public function pulau()
    {
        // Hanya berlaku untuk role pengelola_pulau: 1 akun = 1 pulau
        return $this->belongsTo(Pulau::class, 'pulau_id');
    }

    public function reservasi()
    {
        return $this->hasMany(Reservasi::class, 'user_id');
    }

    public function ulasan()
    {
        return $this->hasMany(Ulasan::class, 'user_id');
    }

    public function notifikasi()
    {
        return $this->hasMany(Notifikasi::class, 'user_id');
    }
}
