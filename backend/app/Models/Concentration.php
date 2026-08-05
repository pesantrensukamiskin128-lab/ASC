<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Concentration extends Model
{
    protected $fillable = ['study_program_id', 'code', 'name', 'description', 'status'];
    protected $casts = ['status' => 'boolean'];

    public function studyProgram(): BelongsTo
    {
        return $this->belongsTo(StudyProgram::class);
    }
}
