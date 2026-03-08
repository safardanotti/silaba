<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KodeAkun extends Model
{
    protected $table = 'kode_akun';
    protected $primaryKey = 'kode_akun';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'kode_akun',
        'nama_akun',
        'tipe_akun',
    ];

    // Hanya created_at, tanpa updated_at
    public $timestamps = true;
    const UPDATED_AT = null;

    public function detailTransaksi()
    {
        return $this->hasMany(DetailTransaksi::class, 'kode_akun', 'kode_akun');
    }

    public function saldoAwal()
    {
        return $this->hasMany(SaldoAwal::class, 'kode_akun', 'kode_akun');
    }

    public function isAktiva(): bool
    {
        return $this->tipe_akun === 'aktiva';
    }

    public function isPassiva(): bool
    {
        return $this->tipe_akun === 'passiva';
    }

    public function isPendapatan(): bool
    {
        return $this->tipe_akun === 'pendapatan';
    }

    public function isBiaya(): bool
    {
        return $this->tipe_akun === 'biaya';
    }

    public function scopeAktiva($query)
    {
        return $query->where('tipe_akun', 'aktiva');
    }

    public function scopePassiva($query)
    {
        return $query->where('tipe_akun', 'passiva');
    }

    public function scopePendapatan($query)
    {
        return $query->where('tipe_akun', 'pendapatan');
    }

    public function scopeBiaya($query)
    {
        return $query->where('tipe_akun', 'biaya');
    }
}
