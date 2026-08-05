<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubCourseLearningOutcome extends Model
{
    protected $fillable = ['course_learning_outcome_id', 'code', 'description', 'order'];

    public function cpmk(): BelongsTo
    {
        return $this->belongsTo(CourseLearningOutcome::class, 'course_learning_outcome_id');
    }
}
