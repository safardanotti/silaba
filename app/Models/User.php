<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $table = 'users';

    // Disable updated_at since we only have created_at
    public $timestamps = false;

    protected $fillable = [
        'username',
        'password',
        'full_name',
        'role',
        'anggota_id',
        'status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isPimpinan(): bool
    {
        return $this->role === 'pimpinan';
    }

    public function isStaff(): bool
    {
        return $this->role === 'staff';
    }

    public function isAnggota(): bool
    {
        return $this->role === 'anggota';
    }

    public function hasPermission(): bool
    {
        return in_array($this->role, ['admin', 'pimpinan']);
    }

    public function canAccessAdmin(): bool
    {
        return in_array($this->role, ['admin', 'pimpinan', 'staff']);
    }

    public function transaksi()
    {
        return $this->hasMany(Transaksi::class, 'created_by');
    }

    public function anggota()
    {
        return $this->belongsTo(Anggota::class, 'anggota_id');
    }

    public function notifikasi()
    {
        return $this->hasMany(Notifikasi::class, 'user_id');
    }

    public function notifikasiBelumDibaca()
    {
        return $this->notifikasi()->where('dibaca', false)->orderBy('created_at', 'desc');
    }
}
