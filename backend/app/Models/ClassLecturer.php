<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassLecturer extends Model
{
    protected $fillable = [
        'class_id', 'lecturer_id', 'teaching_order',
        'planned_meetings', 'actual_meetings',
        'can_input_grades', 'teaching_credits',
    ];

    protected $casts = [
        'can_input_grades' => 'boolean',
        'teaching_credits' => 'decimal:2',
    ];

    public function classModel(): BelongsTo
    {
        return $this->belongsTo(ClassModel::class, 'class_id');
    }

    public function lecturer(): BelongsTo
    {
        return $this->belongsTo(Lecturer::class);
    }
}
