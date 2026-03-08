<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NeracaRevisiHistory extends Model
{
    protected $table = 'neraca_revisi_history';

    const CREATED_AT = 'created_at';
    const UPDATED_AT = null;

    protected $fillable = [
        'posting_id',
        'user_id',
        'action',
        'catatan',
    ];

    /**
     * Get the parent posting
     */
    public function posting()
    {
        return $this->belongsTo(NeracaPosting::class, 'posting_id');
    }

    /**
     * Get the user who performed the action
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get action badge color
     */
    public function getActionBadgeAttribute(): string
    {
        return match($this->action) {
            'post' => 'primary',
            'revisi' => 'warning',
            'approve' => 'success',
            default => 'secondary',
        };
    }
}
