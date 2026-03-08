<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AngsuranPinjaman extends Model
{
    use HasFactory;

    protected $table = 'angsuran_pinjaman';

    protected $fillable = [
        'pinjaman_id',
        'angsuran_ke',
        'tanggal_jatuh_tempo',
        'tanggal_bayar',
        'angsuran_pokok',
        'angsuran_bunga',
        'total_angsuran',
        'jumlah_bayar',
        'denda',
        'sisa_pokok_sebelum',
        'sisa_pokok_sesudah',
        'status',
        'transaksi_id',
        'keterangan',
        'created_by',
    ];

    protected $casts = [
        'tanggal_jatuh_tempo' => 'date',
        'tanggal_bayar' => 'date',
        'angsuran_pokok' => 'decimal:2',
        'angsuran_bunga' => 'decimal:2',
        'total_angsuran' => 'decimal:2',
        'jumlah_bayar' => 'decimal:2',
        'denda' => 'decimal:2',
        'sisa_pokok_sebelum' => 'decimal:2',
        'sisa_pokok_sesudah' => 'decimal:2',
    ];

    public function pinjaman()
    {
        return $this->belongsTo(Pinjaman::class, 'pinjaman_id');
    }

    public function transaksi()
    {
        return $this->belongsTo(Transaksi::class, 'transaksi_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Get status label dengan warna
    public function getStatusLabelAttribute()
    {
        $labels = [
            'belum_bayar' => '<span class="badge bg-secondary">Belum Bayar</span>',
            'sebagian' => '<span class="badge bg-warning">Sebagian</span>',
            'lunas' => '<span class="badge bg-success">Lunas</span>',
            'terlambat' => '<span class="badge bg-danger">Terlambat</span>',
        ];
        
        return $labels[$this->status] ?? $this->status;
    }

    // Cek apakah sudah jatuh tempo
    public function getIsTerlambatAttribute()
    {
        if ($this->status === 'lunas') return false;
        return now()->gt($this->tanggal_jatuh_tempo);
    }

    // Hitung denda keterlambatan (misal 1% dari angsuran per bulan)
    public function hitungDenda($persenDenda = 1)
    {
        if (!$this->is_terlambat) return 0;
        
        $hariTerlambat = now()->diffInDays($this->tanggal_jatuh_tempo);
        $bulanTerlambat = ceil($hariTerlambat / 30);
        
        return round($this->total_angsuran * ($persenDenda / 100) * $bulanTerlambat, 2);
    }

    // Proses pembayaran angsuran
    public function prosesPembayaran($jumlahBayar, $transaksiId = null, $userId = null)
    {
        $this->tanggal_bayar = now();
        $this->jumlah_bayar = $jumlahBayar;
        $this->transaksi_id = $transaksiId;
        $this->created_by = $userId;
        
        if ($jumlahBayar >= $this->total_angsuran) {
            $this->status = 'lunas';
        } else {
            $this->status = 'sebagian';
        }
        
        $this->save();
        
        // Update saldo pinjaman
        $pinjaman = $this->pinjaman;
        $pinjaman->saldo_pokok -= $this->angsuran_pokok;
        $pinjaman->saldo_bunga -= $this->angsuran_bunga;
        $pinjaman->angsuran_ke = $this->angsuran_ke;
        
        // Cek apakah lunas semua
        if ($pinjaman->saldo_pokok <= 0) {
            $pinjaman->status = 'lunas';
            $pinjaman->saldo_pokok = 0;
            $pinjaman->saldo_bunga = 0;
        }
        
        $pinjaman->save();
        
        return $this;
    }

    // Scope by status
    public function scopeBelumBayar($query)
    {
        return $query->where('status', 'belum_bayar');
    }

    public function scopeLunas($query)
    {
        return $query->where('status', 'lunas');
    }

    public function scopeTerlambat($query)
    {
        return $query->where('status', '!=', 'lunas')
            ->where('tanggal_jatuh_tempo', '<', now());
    }
}
