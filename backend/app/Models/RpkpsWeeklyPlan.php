<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RpkpsWeeklyPlan extends Model
{
    protected $fillable = [
        'rpkps_id', 'week_number', 'sub_cpmk', 'indicators',
        'learning_material', 'methods', 'lecturer_activity',
        'student_activity', 'assessment_form', 'assessment_criteria',
        'media', 'duration', 'weight',
    ];

    protected $casts = ['methods' => 'array'];

    public function rpkps(): BelongsTo { return $this->belongsTo(Rpkps::class); }
}
