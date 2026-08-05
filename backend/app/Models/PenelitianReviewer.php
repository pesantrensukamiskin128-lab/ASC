<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PenelitianReviewer extends Model
{
    protected $fillable = [
        'penelitian_id', 'lecturer_id', 'stage',
        'score_orisinalitas', 'score_metodologi', 'score_manfaat', 'score_kelayakan',
        'score_total', 'catatan', 'rekomendasi', 'reviewed_at', 'assigned_by',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public function penelitian(): BelongsTo
    {
        return $this->belongsTo(Penelitian::class);
    }

    public function lecturer(): BelongsTo
    {
        return $this->belongsTo(Lecturer::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
