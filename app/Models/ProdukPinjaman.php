<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProdukPinjaman extends Model
{
    use HasFactory;

    protected $table = 'produk_pinjaman';

    protected $fillable = [
        'kode_produk',
        'nama_produk',
        'bunga_persen',
        'max_tenor',
        'max_pinjaman',
        'min_pinjaman',
        'syarat_ketentuan',
        'kode_akun_piutang',
        'kode_akun_bunga',
        'aktif',
    ];

    protected $casts = [
        'bunga_persen' => 'decimal:2',
        'max_pinjaman' => 'decimal:2',
        'min_pinjaman' => 'decimal:2',
        'aktif' => 'boolean',
    ];

    public function kodeAkunPiutang()
    {
        return $this->belongsTo(KodeAkun::class, 'kode_akun_piutang', 'kode_akun');
    }

    public function kodeAkunBunga()
    {
        return $this->belongsTo(KodeAkun::class, 'kode_akun_bunga', 'kode_akun');
    }

    public function pinjaman()
    {
        return $this->hasMany(Pinjaman::class, 'produk_pinjaman_id');
    }

    public function pengajuan()
    {
        return $this->hasMany(PengajuanPinjaman::class, 'produk_pinjaman_id');
    }

    public function scopeAktif($query)
    {
        return $query->where('aktif', true);
    }

    // Hitung angsuran bulanan (flat rate)
    public function hitungAngsuranBulanan($jumlahPinjaman, $tenor)
    {
        $bungaPerBulan = ($this->bunga_persen / 100 / 12);
        $totalBunga = $jumlahPinjaman * $bungaPerBulan * $tenor;
        $angsuranPokok = $jumlahPinjaman / $tenor;
        $angsuranBunga = $totalBunga / $tenor;
        
        return [
            'angsuran_pokok' => round($angsuranPokok, 2),
            'angsuran_bunga' => round($angsuranBunga, 2),
            'total_angsuran' => round($angsuranPokok + $angsuranBunga, 2),
            'total_bunga' => round($totalBunga, 2),
            'total_bayar' => round($jumlahPinjaman + $totalBunga, 2),
        ];
    }
}
