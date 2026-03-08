<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaldoAwal extends Model
{
    protected $table = 'saldo_awal';

    protected $fillable = [
        'periode',
        'kode_akun',
        'debet',
        'kredit',
        'saldo_akhir_debet',
        'saldo_akhir_kredit',
        'is_initial',
    ];

    // Hanya created_at, tanpa updated_at
    public $timestamps = true;
    const UPDATED_AT = null;

    protected $casts = [
        'periode' => 'date',
        'debet' => 'decimal:2',
        'kredit' => 'decimal:2',
        'saldo_akhir_debet' => 'decimal:2',
        'saldo_akhir_kredit' => 'decimal:2',
        'is_initial' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function kodeAkun(): BelongsTo
    {
        return $this->belongsTo(KodeAkun::class, 'kode_akun', 'kode_akun');
    }

    public function scopePeriode($query, $tahun, $bulan)
    {
        return $query->where('periode', "{$tahun}-{$bulan}-01");
    }
}
