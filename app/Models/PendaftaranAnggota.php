<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PendaftaranAnggota extends Model
{
    use HasFactory;

    protected $table = 'pendaftaran_anggota';

    protected $fillable = [
        'nama_lengkap',
        'nik',
        'tempat_lahir',
        'tanggal_lahir',
        'jenis_kelamin',
        'alamat',
        'no_hp',
        'email',
        'unit_kerja',
        'jabatan',
        'dok_ktp',
        'dok_kk',
        'dok_foto',
        'status',
        'approved_by',
        'approved_at',
        'catatan',
    ];

    protected $casts = [
        'tanggal_lahir' => 'date',
        'approved_at' => 'datetime',
    ];

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // Get status label dengan warna
    public function getStatusLabelAttribute()
    {
        $labels = [
            'pending' => '<span class="badge bg-warning">Pending</span>',
            'disetujui' => '<span class="badge bg-success">Disetujui</span>',
            'ditolak' => '<span class="badge bg-danger">Ditolak</span>',
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

    // Konversi ke anggota setelah disetujui
    public function konversiKeAnggota($userId = null)
    {
        $anggota = Anggota::create([
            'no_anggota' => Anggota::generateNoAnggota(),
            'nama_anggota' => $this->nama_lengkap,
            'nik' => $this->nik,
            'tempat_lahir' => $this->tempat_lahir,
            'tanggal_lahir' => $this->tanggal_lahir,
            'jenis_kelamin' => $this->jenis_kelamin,
            'alamat' => $this->alamat,
            'no_hp' => $this->no_hp,
            'email' => $this->email,
            'unit_kerja' => $this->unit_kerja,
            'jabatan' => $this->jabatan,
            'tanggal_masuk' => now(),
            'status_anggota' => 'aktif',
            'foto' => $this->dok_foto,
            'created_by' => $userId,
        ]);

        // Buat user account untuk anggota
        $user = User::create([
            'username' => strtolower(str_replace(' ', '', $this->nama_lengkap)) . rand(100, 999),
            'password' => bcrypt('password123'), // Default password
            'full_name' => $this->nama_lengkap,
            'role' => 'anggota',
            'anggota_id' => $anggota->id,
            'status' => 'active',
        ]);

        return [
            'anggota' => $anggota,
            'user' => $user,
        ];
    }
}
