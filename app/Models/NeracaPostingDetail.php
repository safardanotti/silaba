<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NeracaPostingDetail extends Model
{
    protected $table = 'neraca_posting_detail';

    public $timestamps = false;

    protected $fillable = [
        'posting_id',
        'kode_akun',
        'nama_akun',
        'tipe_akun',
        'saldo_debet',
        'saldo_kredit',
    ];

    protected $casts = [
        'saldo_debet' => 'decimal:2',
        'saldo_kredit' => 'decimal:2',
    ];

    /**
     * Get the parent posting
     */
    public function posting()
    {
        return $this->belongsTo(NeracaPosting::class, 'posting_id');
    }

    /**
     * Get the actual saldo value
     */
    public function getSaldoAttribute(): float
    {
        return $this->saldo_debet > 0 ? $this->saldo_debet : $this->saldo_kredit;
    }
}
