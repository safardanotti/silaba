<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transaksi extends Model
{
    protected $table = 'transaksi';

    protected $fillable = [
        'tanggal',
        'uraian_kegiatan',
        'jenis_transaksi',
        'jenis_masuk',
        'created_by',
    ];

    // Hanya created_at, tanpa updated_at
    public $timestamps = true;
    const UPDATED_AT = null;

    protected $casts = [
        'tanggal' => 'date',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function detailTransaksi(): HasMany
    {
        return $this->hasMany(DetailTransaksi::class, 'transaksi_id');
    }

    public function scopePenerimaan($query)
    {
        return $query->where('jenis_transaksi', 'penerimaan');
    }

    public function scopePengeluaran($query)
    {
        return $query->where('jenis_transaksi', 'pengeluaran');
    }

    public function scopeRupaRupa($query)
    {
        return $query->where('jenis_transaksi', 'rupa_rupa');
    }

    public function scopeKas($query)
    {
        return $query->where('jenis_masuk', 'kas');
    }

    public function scopeBank($query)
    {
        return $query->where('jenis_masuk', 'bank');
    }

    public function scopePeriode($query, $tahun, $bulan)
    {
        $startDate = "{$tahun}-{$bulan}-01";
        $endDate = date('Y-m-t', strtotime($startDate));
        
        return $query->whereBetween('tanggal', [$startDate, $endDate]);
    }

    public function getTotalDebetAttribute()
    {
        return $this->detailTransaksi->sum('debet');
    }

    public function getTotalKreditAttribute()
    {
        return $this->detailTransaksi->sum('kredit');
    }

    public function getKasDebetAttribute()
    {
        return $this->detailTransaksi
            ->whereIn('kode_akun', ['100', '101'])
            ->sum('debet');
    }

    public function getKasKreditAttribute()
    {
        return $this->detailTransaksi
            ->whereIn('kode_akun', ['100', '101'])
            ->sum('kredit');
    }
}
