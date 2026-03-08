<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JenisSimpanan extends Model
{
    use HasFactory;

    protected $table = 'jenis_simpanan';

    protected $fillable = [
        'kode_simpanan',
        'nama_simpanan',
        'minimal_setor',
        'wajib_bulanan',
        'keterangan',
        'kode_akun',
        'aktif',
    ];

    protected $casts = [
        'wajib_bulanan' => 'boolean',
        'aktif' => 'boolean',
        'minimal_setor' => 'decimal:2',
    ];

    public function kodeAkun()
    {
        return $this->belongsTo(KodeAkun::class, 'kode_akun', 'kode_akun');
    }

    public function simpananAnggota()
    {
        return $this->hasMany(SimpananAnggota::class, 'jenis_simpanan_id');
    }

    public function saldoSimpanan()
    {
        return $this->hasMany(SaldoSimpanan::class, 'jenis_simpanan_id');
    }

    public function scopeAktif($query)
    {
        return $query->where('aktif', true);
    }
}
