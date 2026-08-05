<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class GraduateProfile extends Model
{
    protected $fillable = ['curriculum_id', 'code', 'name', 'description', 'order'];

    public function curriculum(): BelongsTo
    {
        return $this->belongsTo(Curriculum::class);
    }

    public function learningOutcomes(): BelongsToMany
    {
        return $this->belongsToMany(LearningOutcome::class, 'cpl_graduate_profile', 'graduate_profile_id', 'learning_outcome_id')
            ->withTimestamps();
    }
}
