<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Curriculum extends Model
{
    protected $table = 'curriculums';

    protected $fillable = ['study_program_id', 'code', 'name', 'year', 'description', 'status'];

    public function studyProgram(): BelongsTo
    {
        return $this->belongsTo(StudyProgram::class);
    }

    public function learningOutcomes(): HasMany
    {
        return $this->hasMany(LearningOutcome::class);
    }

    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'curriculum_courses')
            ->withPivot('semester', 'is_required')
            ->withTimestamps();
    }

    public function curriculumCourses(): HasMany
    {
        return $this->hasMany(CurriculumCourse::class);
    }
}
