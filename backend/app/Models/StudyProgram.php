<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudyProgram extends Model
{
    protected $fillable = [
        'faculty_id', 'code', 'name', 'degree', 'level',
        'accreditation', 'head_lecturer_id', 'status',
    ];

    protected $casts = ['status' => 'boolean'];

    public function faculty(): BelongsTo
    {
        return $this->belongsTo(Faculty::class);
    }

    public function headLecturer(): BelongsTo
    {
        return $this->belongsTo(Lecturer::class, 'head_lecturer_id');
    }

    public function lecturers(): HasMany
    {
        return $this->hasMany(Lecturer::class);
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }
}
