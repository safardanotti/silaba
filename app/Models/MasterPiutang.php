<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MasterPiutang extends Model
{
    use HasFactory;

    protected $table = 'master_piutang';

    // Disable updated_at karena tabel hanya punya created_at
    public $timestamps = true;
    const UPDATED_AT = null;

    protected $fillable = [
        'nama_debitur',
        'keterangan',
        'kode_akun_default',
        'aktif',
    ];

    protected $casts = [
        'aktif' => 'boolean',
    ];

    /**
     * Get the kode akun for this piutang
     */
    public function kodeAkun()
    {
        return $this->belongsTo(KodeAkun::class, 'kode_akun_default', 'kode_akun');
    }

    /**
     * Get the transaksi piutang for this master
     */
    public function transaksiPiutang()
    {
        return $this->hasMany(TransaksiPiutang::class, 'master_piutang_id');
    }

    /**
     * Scope a query to only include active piutang
     */
    public function scopeAktif($query)
    {
        return $query->where('aktif', 1);
    }
}
