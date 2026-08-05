<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class LearningOutcome extends Model
{
    protected $fillable = ['curriculum_id', 'code', 'category', 'description', 'order'];

    public function curriculum(): BelongsTo
    {
        return $this->belongsTo(Curriculum::class);
    }

    public function graduateProfiles(): BelongsToMany
    {
        return $this->belongsToMany(GraduateProfile::class, 'cpl_graduate_profile', 'learning_outcome_id', 'graduate_profile_id')
            ->withTimestamps();
    }
}
