<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClassOffering extends Model
{
    protected $fillable = [
        'course_id', 'academic_year_id', 'lecturer_id', 'room_id',
        'class_code', 'max_students', 'enrolled_count',
        'day', 'start_time', 'end_time', 'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];

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

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function krsDetails(): HasMany
    {
        return $this->hasMany(KrsDetail::class);
    }

    public function isAvailable(): bool
    {
        return $this->enrolled_count < $this->max_students;
    }
}
