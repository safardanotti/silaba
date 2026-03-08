<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransaksiPiutang extends Model
{
    use HasFactory;

    protected $table = 'transaksi_piutang';

    protected $fillable = [
        'periode',
        'master_piutang_id',
        'saldo_awal',
        'mutasi_debet',
        'mutasi_kredit',
        'saldo_akhir',
        'created_by',
    ];

    protected $casts = [
        'periode' => 'date',
        'saldo_awal' => 'decimal:2',
        'mutasi_debet' => 'decimal:2',
        'mutasi_kredit' => 'decimal:2',
        'saldo_akhir' => 'decimal:2',
    ];

    /**
     * Get the master piutang for this transaksi
     */
    public function masterPiutang()
    {
        return $this->belongsTo(MasterPiutang::class, 'master_piutang_id');
    }

    /**
     * Get the user who created this transaksi
     */
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope a query to filter by periode
     */
    public function scopePeriode($query, $periode)
    {
        return $query->where('periode', $periode);
    }
}
