<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RpsMeeting extends Model
{
    protected $fillable = [
        'rps_id', 'meeting_number', 'topic', 'sub_topics',
        'learning_activities', 'learning_methods', 'duration',
        'assessment_indicators', 'weight',
    ];

    public function rps(): BelongsTo
    {
        return $this->belongsTo(Rps::class);
    }
}
