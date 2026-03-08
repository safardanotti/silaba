<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Anggota extends Model
{
    use HasFactory;

    protected $table = 'anggota';

    protected $fillable = [
        'no_anggota',
        'nama_anggota',
        'nik',
        'tempat_lahir',
        'tanggal_lahir',
        'jenis_kelamin',
        'alamat',
        'no_hp',
        'email',
        'unit_kerja',
        'jabatan',
        'tanggal_masuk',
        'status_anggota',
        'foto',
        'keterangan',
        'created_by',
    ];

    protected $casts = [
        'tanggal_lahir' => 'date',
        'tanggal_masuk' => 'date',
    ];

    // Generate nomor anggota otomatis
    public static function generateNoAnggota()
    {
        $tahun = date('Y');
        $lastAnggota = self::where('no_anggota', 'like', "ANG{$tahun}%")
            ->orderBy('no_anggota', 'desc')
            ->first();

        if ($lastAnggota) {
            $lastNumber = (int) substr($lastAnggota->no_anggota, -4);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return "ANG{$tahun}" . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
    }

    // Relationships
    public function user()
    {
        return $this->hasOne(User::class, 'anggota_id');
    }

    public function pinjaman()
    {
        return $this->hasMany(Pinjaman::class, 'anggota_id');
    }

    public function pinjamanAktif()
    {
        return $this->pinjaman()->where('status', 'aktif');
    }

    public function pengajuanPinjaman()
    {
        return $this->hasMany(PengajuanPinjaman::class, 'anggota_id');
    }

    public function simpanan()
    {
        return $this->hasMany(SimpananAnggota::class, 'anggota_id');
    }

    public function saldoSimpanan()
    {
        return $this->hasMany(SaldoSimpanan::class, 'anggota_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Get total saldo simpanan
    public function getTotalSimpananAttribute()
    {
        return $this->saldoSimpanan()->sum('total_saldo');
    }

    // Get total pinjaman aktif
    public function getTotalPinjamanAktifAttribute()
    {
        return $this->pinjamanAktif()->sum('saldo_pokok');
    }

    // Scope untuk anggota aktif
    public function scopeAktif($query)
    {
        return $query->where('status_anggota', 'aktif');
    }

    // Scope untuk pencarian
    public function scopeCari($query, $keyword)
    {
        return $query->where(function ($q) use ($keyword) {
            $q->where('nama_anggota', 'like', "%{$keyword}%")
                ->orWhere('no_anggota', 'like', "%{$keyword}%")
                ->orWhere('nik', 'like', "%{$keyword}%")
                ->orWhere('unit_kerja', 'like', "%{$keyword}%");
        });
    }
}
