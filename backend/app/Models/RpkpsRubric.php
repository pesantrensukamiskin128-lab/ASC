<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RpkpsRubric extends Model
{
    protected $fillable = [
        'rpkps_id', 'rpkps_assessment_id',
        'criteria', 'excellent', 'good', 'fair', 'poor', 'order',
    ];

    public function rpkps(): BelongsTo { return $this->belongsTo(Rpkps::class); }
    public function assessment(): BelongsTo { return $this->belongsTo(RpkpsAssessment::class, 'rpkps_assessment_id'); }
}
