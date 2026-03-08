<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SimpananAnggota extends Model
{
    use HasFactory;

    protected $table = 'simpanan_anggota';

    protected $fillable = [
        'anggota_id',
        'jenis_simpanan_id',
        'tanggal_transaksi',
        'jenis_transaksi',
        'jumlah',
        'saldo_sebelum',
        'saldo_sesudah',
        'keterangan',
        'transaksi_id',
        'created_by',
    ];

    protected $casts = [
        'tanggal_transaksi' => 'date',
        'jumlah' => 'decimal:2',
        'saldo_sebelum' => 'decimal:2',
        'saldo_sesudah' => 'decimal:2',
    ];

    public function anggota()
    {
        return $this->belongsTo(Anggota::class, 'anggota_id');
    }

    public function jenisSimpanan()
    {
        return $this->belongsTo(JenisSimpanan::class, 'jenis_simpanan_id');
    }

    public function transaksi()
    {
        return $this->belongsTo(Transaksi::class, 'transaksi_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Scope untuk setor
    public function scopeSetor($query)
    {
        return $query->where('jenis_transaksi', 'setor');
    }

    // Scope untuk tarik
    public function scopeTarik($query)
    {
        return $query->where('jenis_transaksi', 'tarik');
    }
}
