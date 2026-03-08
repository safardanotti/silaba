<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SaldoSimpanan extends Model
{
    use HasFactory;

    protected $table = 'saldo_simpanan';

    protected $fillable = [
        'anggota_id',
        'jenis_simpanan_id',
        'total_saldo',
    ];

    protected $casts = [
        'total_saldo' => 'decimal:2',
    ];

    public function anggota()
    {
        return $this->belongsTo(Anggota::class, 'anggota_id');
    }

    public function jenisSimpanan()
    {
        return $this->belongsTo(JenisSimpanan::class, 'jenis_simpanan_id');
    }

    // Update saldo setelah transaksi simpanan
    public static function updateSaldo($anggotaId, $jenisSimpananId)
    {
        $totalSetor = SimpananAnggota::where('anggota_id', $anggotaId)
            ->where('jenis_simpanan_id', $jenisSimpananId)
            ->where('jenis_transaksi', 'setor')
            ->sum('jumlah');

        $totalTarik = SimpananAnggota::where('anggota_id', $anggotaId)
            ->where('jenis_simpanan_id', $jenisSimpananId)
            ->where('jenis_transaksi', 'tarik')
            ->sum('jumlah');

        $saldo = self::firstOrCreate([
            'anggota_id' => $anggotaId,
            'jenis_simpanan_id' => $jenisSimpananId,
        ]);

        $saldo->total_saldo = $totalSetor - $totalTarik;
        $saldo->save();

        return $saldo;
    }
}
