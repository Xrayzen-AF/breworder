<?php

namespace App\Models;

use Database\Factories\PenggunaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Model tabel `pengguna` — akun login admin / kasir / barista / pelanggan,
 * sesuai ERD (PK: id_pengguna, password: kata_sandi, waktu: dibuat_pada).
 */
class Pengguna extends Authenticatable
{
    /** @use HasFactory<PenggunaFactory> */
    use HasFactory, Notifiable;

    public const ROLES = ['admin', 'kasir', 'barista', 'pelanggan'];

    protected $table = 'pengguna';

    protected $primaryKey = 'id_pengguna';

    const CREATED_AT = 'dibuat_pada';

    const UPDATED_AT = null;

    protected $fillable = [
        'nama_lengkap',
        'email',
        'kata_sandi',
        'nomor_telepon',
        'peran',
        'status_aktif',
    ];

    protected $hidden = [
        'kata_sandi',
    ];

    protected function casts(): array
    {
        return [
            'kata_sandi' => 'hashed',
            'status_aktif' => 'boolean',
        ];
    }

    /**
     * Kolom password autentikasi Laravel memakai `kata_sandi` sesuai ERD.
     */
    public function getAuthPassword(): string
    {
        return $this->kata_sandi;
    }

    public function isAdmin(): bool
    {
        return $this->peran === 'admin';
    }

    public function isKasir(): bool
    {
        return $this->peran === 'kasir';
    }

    public function isBarista(): bool
    {
        return $this->peran === 'barista';
    }

    public function isPelanggan(): bool
    {
        return $this->peran === 'pelanggan';
    }

    /**
     * Pesanan yang ditangani pengguna ini (sebagai kasir).
     */
    public function pesananDitangani(): HasMany
    {
        return $this->hasMany(Pesanan::class, 'id_pengguna', 'id_pengguna');
    }
}
