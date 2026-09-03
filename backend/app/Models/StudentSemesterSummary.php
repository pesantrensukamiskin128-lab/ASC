<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentSemesterSummary extends Model
{
    protected $fillable = [
        'student_id', 'semester_id', 'status', 'semester_gpa', 'cumulative_gpa',
        'credit_limit', 'credits_taken', 'required_credits', 'elective_credits', 'total_credits',
    ];

    protected $casts = [
        'semester_gpa' => 'decimal:2',
        'cumulative_gpa' => 'decimal:2',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }
}
