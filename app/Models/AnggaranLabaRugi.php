<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AnggaranLabaRugi extends Model
{
    use HasFactory;

    protected $table = 'anggaran_laba_rugi';

    protected $fillable = [
        'periode',
        'kode_akun',
        'anggaran_tahun',
        'anggaran_triwulan',
        'realisasi_bulan_lalu',
    ];

    protected $casts = [
        'periode' => 'date',
        'anggaran_tahun' => 'decimal:2',
        'anggaran_triwulan' => 'decimal:2',
        'realisasi_bulan_lalu' => 'decimal:2',
    ];

    /**
     * Get the kode akun for this anggaran
     */
    public function kodeAkun()
    {
        return $this->belongsTo(KodeAkun::class, 'kode_akun', 'kode_akun');
    }

    /**
     * Scope a query to filter by periode
     */
    public function scopePeriode($query, $periode)
    {
        return $query->where('periode', $periode);
    }
}
