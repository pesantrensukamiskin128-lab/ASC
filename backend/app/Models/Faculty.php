<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Faculty extends Model
{
    protected $fillable = ['institution_id', 'code', 'name', 'dean_name', 'status'];

    protected $casts = ['status' => 'boolean'];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function studyPrograms(): HasMany
    {
        return $this->hasMany(StudyProgram::class);
    }
}
