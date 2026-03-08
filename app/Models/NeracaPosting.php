<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NeracaPosting extends Model
{
    protected $table = 'neraca_posting';

    public $timestamps = false;

    protected $fillable = [
        'periode',
        'status',
        'posted_by',
        'posted_at',
        'reviewed_by',
        'reviewed_at',
        'catatan_revisi',
        'approved_at',
    ];

    protected $casts = [
        'periode' => 'date',
        'posted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    /**
     * Get the user who posted the neraca
     */
    public function postedBy()
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    /**
     * Get the user who reviewed the neraca
     */
    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Get the neraca posting details
     */
    public function details()
    {
        return $this->hasMany(NeracaPostingDetail::class, 'posting_id');
    }

    /**
     * Get the revision history
     */
    public function history()
    {
        return $this->hasMany(NeracaRevisiHistory::class, 'posting_id');
    }

    /**
     * Check if neraca is posted
     */
    public function isPosted(): bool
    {
        return $this->status === 'posted';
    }

    /**
     * Check if neraca needs revision
     */
    public function needsRevision(): bool
    {
        return $this->status === 'revisi';
    }

    /**
     * Check if neraca is approved
     */
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * Check if neraca is draft
     */
    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    /**
     * Get status badge color
     */
    public function getStatusBadgeAttribute(): string
    {
        return match($this->status) {
            'posted' => 'info',
            'revisi' => 'warning',
            'approved' => 'success',
            default => 'secondary',
        };
    }
}
