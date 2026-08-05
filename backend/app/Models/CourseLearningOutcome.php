<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CourseLearningOutcome extends Model
{
    protected $fillable = ['curriculum_id', 'course_id', 'code', 'description', 'order'];

    public function curriculum(): BelongsTo
    {
        return $this->belongsTo(Curriculum::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /** CPL yang terhubung (many-to-many via cpmk_cpl_mappings) */
    public function learningOutcomes(): BelongsToMany
    {
        return $this->belongsToMany(LearningOutcome::class, 'cpmk_cpl_mappings', 'course_learning_outcome_id', 'learning_outcome_id')
            ->withTimestamps();
    }

    public function subCpmks(): HasMany
    {
        return $this->hasMany(SubCourseLearningOutcome::class, 'course_learning_outcome_id')->orderBy('order');
    }
}
