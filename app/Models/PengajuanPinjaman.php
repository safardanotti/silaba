<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PengajuanPinjaman extends Model
{
    use HasFactory;

    protected $table = 'pengajuan_pinjaman';

    protected $fillable = [
        'no_pengajuan',
        'anggota_id',
        'produk_pinjaman_id',
        'jumlah_pinjaman',
        'tenor',
        'keperluan',
        'status',
        'dok_ktp',
        'dok_kk',
        'dok_slip_gaji',
        'dok_lainnya',
        'approved_by',
        'approved_at',
        'catatan_approval',
    ];

    protected $casts = [
        'jumlah_pinjaman' => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    // Generate nomor pengajuan otomatis
    public static function generateNoPengajuan()
    {
        $tahun = date('Y');
        $bulan = date('m');
        $prefix = "PJN{$tahun}{$bulan}";
        
        $last = self::where('no_pengajuan', 'like', "{$prefix}%")
            ->orderBy('no_pengajuan', 'desc')
            ->first();

        if ($last) {
            $lastNumber = (int) substr($last->no_pengajuan, -4);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return $prefix . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
    }

    public function anggota()
    {
        return $this->belongsTo(Anggota::class, 'anggota_id');
    }

    public function produkPinjaman()
    {
        return $this->belongsTo(ProdukPinjaman::class, 'produk_pinjaman_id');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function pinjaman()
    {
        return $this->hasOne(Pinjaman::class, 'pengajuan_id');
    }

    // Get status label dengan warna
    public function getStatusLabelAttribute()
    {
        $labels = [
            'pending' => '<span class="badge bg-warning">Pending</span>',
            'diproses' => '<span class="badge bg-info">Diproses</span>',
            'disetujui' => '<span class="badge bg-success">Disetujui</span>',
            'ditolak' => '<span class="badge bg-danger">Ditolak</span>',
            'dicairkan' => '<span class="badge bg-primary">Dicairkan</span>',
        ];
        
        return $labels[$this->status] ?? $this->status;
    }

    // Scope by status
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeDisetujui($query)
    {
        return $query->where('status', 'disetujui');
    }
}
