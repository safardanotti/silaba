<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notifikasi extends Model
{
    use HasFactory;

    protected $table = 'notifikasi';

    protected $fillable = [
        'user_id',
        'judul',
        'pesan',
        'tipe',
        'link',
        'dibaca',
    ];

    protected $casts = [
        'dibaca' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Scope belum dibaca
    public function scopeBelumDibaca($query)
    {
        return $query->where('dibaca', false);
    }

    // Tandai sudah dibaca
    public function tandaiDibaca()
    {
        $this->dibaca = true;
        $this->save();
        return $this;
    }

    // Kirim notifikasi
    public static function kirim($userId, $judul, $pesan, $tipe = 'info', $link = null)
    {
        return self::create([
            'user_id' => $userId,
            'judul' => $judul,
            'pesan' => $pesan,
            'tipe' => $tipe,
            'link' => $link,
        ]);
    }

    // Kirim notifikasi ke semua admin
    public static function kirimKeAdmin($judul, $pesan, $tipe = 'info', $link = null)
    {
        $admins = User::where('role', 'admin')->get();
        
        foreach ($admins as $admin) {
            self::kirim($admin->id, $judul, $pesan, $tipe, $link);
        }
    }
}
