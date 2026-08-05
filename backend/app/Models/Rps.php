<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rps extends Model
{
    protected $table = 'rps';

    protected $fillable = [
        'course_id', 'academic_year_id', 'lecturer_id', 'code',
        'course_description', 'learning_objectives', 'references',
        'assessment_scheme', 'status', 'approved_by', 'approved_at', 'rejection_note',
    ];

    protected $casts = [
        'assessment_scheme' => 'array',
        'approved_at'       => 'datetime',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function lecturer(): BelongsTo
    {
        return $this->belongsTo(Lecturer::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function meetings(): HasMany
    {
        return $this->hasMany(RpsMeeting::class)->orderBy('meeting_number');
    }
}
