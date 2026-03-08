<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Pinjaman extends Model
{
    use HasFactory;

    protected $table = 'pinjaman';

    protected $fillable = [
        'no_pinjaman',
        'pengajuan_id',
        'anggota_id',
        'produk_pinjaman_id',
        'tanggal_pinjaman',
        'tanggal_jatuh_tempo',
        'jumlah_pinjaman',
        'bunga_persen',
        'tenor',
        'angsuran_pokok',
        'angsuran_bunga',
        'total_angsuran',
        'saldo_pokok',
        'saldo_bunga',
        'angsuran_ke',
        'status',
        'transaksi_id',
        'created_by',
    ];

    protected $casts = [
        'tanggal_pinjaman' => 'date',
        'tanggal_jatuh_tempo' => 'date',
        'jumlah_pinjaman' => 'decimal:2',
        'bunga_persen' => 'decimal:2',
        'angsuran_pokok' => 'decimal:2',
        'angsuran_bunga' => 'decimal:2',
        'total_angsuran' => 'decimal:2',
        'saldo_pokok' => 'decimal:2',
        'saldo_bunga' => 'decimal:2',
    ];

    // Generate nomor pinjaman otomatis
    public static function generateNoPinjaman()
    {
        $tahun = date('Y');
        $bulan = date('m');
        $prefix = "PNJ{$tahun}{$bulan}";
        
        $last = self::where('no_pinjaman', 'like', "{$prefix}%")
            ->orderBy('no_pinjaman', 'desc')
            ->first();

        if ($last) {
            $lastNumber = (int) substr($last->no_pinjaman, -4);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return $prefix . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
    }

    public function pengajuan()
    {
        return $this->belongsTo(PengajuanPinjaman::class, 'pengajuan_id');
    }

    public function anggota()
    {
        return $this->belongsTo(Anggota::class, 'anggota_id');
    }

    public function produkPinjaman()
    {
        return $this->belongsTo(ProdukPinjaman::class, 'produk_pinjaman_id');
    }

    public function transaksi()
    {
        return $this->belongsTo(Transaksi::class, 'transaksi_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function angsuran()
    {
        return $this->hasMany(AngsuranPinjaman::class, 'pinjaman_id');
    }

    // Get status label dengan warna
    public function getStatusLabelAttribute()
    {
        $labels = [
            'aktif' => '<span class="badge bg-primary">Aktif</span>',
            'lunas' => '<span class="badge bg-success">Lunas</span>',
            'macet' => '<span class="badge bg-danger">Macet</span>',
            'restruktur' => '<span class="badge bg-warning">Restruktur</span>',
        ];
        
        return $labels[$this->status] ?? $this->status;
    }

    // Get persentase pelunasan
    public function getPersentaseLunasAttribute()
    {
        if ($this->jumlah_pinjaman == 0) return 0;
        $terbayar = $this->jumlah_pinjaman - $this->saldo_pokok;
        return round(($terbayar / $this->jumlah_pinjaman) * 100, 2);
    }

    // Get angsuran berikutnya yang harus dibayar
    public function getAngsuranBerikutnyaAttribute()
    {
        return $this->angsuran()
            ->where('status', '!=', 'lunas')
            ->orderBy('angsuran_ke')
            ->first();
    }

    // Generate jadwal angsuran
    public function generateJadwalAngsuran()
    {
        $tanggalMulai = Carbon::parse($this->tanggal_pinjaman);
        $sisaPokok = $this->jumlah_pinjaman;
        
        for ($i = 1; $i <= $this->tenor; $i++) {
            $tanggalJatuhTempo = $tanggalMulai->copy()->addMonths($i);
            $sisaPokokSebelum = $sisaPokok;
            $sisaPokok -= $this->angsuran_pokok;
            
            AngsuranPinjaman::create([
                'pinjaman_id' => $this->id,
                'angsuran_ke' => $i,
                'tanggal_jatuh_tempo' => $tanggalJatuhTempo,
                'angsuran_pokok' => $this->angsuran_pokok,
                'angsuran_bunga' => $this->angsuran_bunga,
                'total_angsuran' => $this->total_angsuran,
                'sisa_pokok_sebelum' => $sisaPokokSebelum,
                'sisa_pokok_sesudah' => max(0, $sisaPokok),
                'status' => 'belum_bayar',
            ]);
        }
    }

    // Scope by status
    public function scopeAktif($query)
    {
        return $query->where('status', 'aktif');
    }

    public function scopeLunas($query)
    {
        return $query->where('status', 'lunas');
    }
}
