<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KasBank extends Model
{
    protected $table = 'kas_bank';

    protected $fillable = [
        'periode',
        'jenis',
        'nama',
        'saldo_awal',
        'mutasi_debet',
        'mutasi_kredit',
        'saldo_akhir',
    ];

    // Enable timestamps (created_at, updated_at)
    public $timestamps = true;

    protected $casts = [
        'saldo_awal' => 'decimal:2',
        'mutasi_debet' => 'decimal:2',
        'mutasi_kredit' => 'decimal:2',
        'saldo_akhir' => 'decimal:2',
        'periode' => 'date',
    ];

    public function scopeKas($query)
    {
        return $query->where('jenis', 'kas');
    }

    public function scopeBank($query)
    {
        return $query->where('jenis', 'bank');
    }

    public function scopePeriode($query, $periode)
    {
        return $query->where('periode', $periode);
    }

    /**
     * Get or initialize kas data for a period
     */
    public static function getKasData($periode)
    {
        $data = self::where('periode', $periode)
            ->where('jenis', 'kas')
            ->first();

        if (!$data) {
            return [
                'saldo_awal' => 0,
                'mutasi_debet' => 0,
                'mutasi_kredit' => 0,
                'saldo_akhir' => 0
            ];
        }

        return $data->toArray();
    }

    /**
     * Get or initialize bank data for a period
     */
    public static function getBankData($periode)
    {
        $data = self::where('periode', $periode)
            ->where('jenis', 'bank')
            ->first();

        if (!$data) {
            return [
                'saldo_awal' => 0,
                'mutasi_debet' => 0,
                'mutasi_kredit' => 0,
                'saldo_akhir' => 0
            ];
        }

        return $data->toArray();
    }

    /**
     * Calculate saldo akhir
     */
    public function calculateSaldoAkhir()
    {
        return $this->saldo_awal + $this->mutasi_debet - $this->mutasi_kredit;
    }
}
