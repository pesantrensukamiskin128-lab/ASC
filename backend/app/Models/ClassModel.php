<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClassModel extends Model
{
    protected $table = 'classes';

    protected $fillable = [
        'study_program_id', 'semester_id', 'course_id', 'lecturer_id', 'room_id',
        'name', 'capacity', 'academic_level', 'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function studyProgram(): BelongsTo
    {
        return $this->belongsTo(StudyProgram::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function lecturer(): BelongsTo
    {
        return $this->belongsTo(Lecturer::class);
    }

    public function lecturers(): BelongsToMany
    {
        return $this->belongsToMany(Lecturer::class, 'class_lecturers', 'class_id', 'lecturer_id')
            ->withPivot('teaching_order', 'planned_meetings', 'actual_meetings', 'can_input_grades', 'teaching_credits')
            ->withTimestamps();
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /** Jadwal (bisa multiple per kelas) */
    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class, 'class_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(ClassMember::class, 'class_id');
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'class_members', 'class_id', 'student_id')
            ->withTimestamps();
    }

    public function enrolledCount(): int
    {
        return $this->members()->count();
    }

    public function isAvailable(): bool
    {
        return $this->enrolledCount() < $this->capacity;
    }
}
