<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentProfile extends Model
{
    protected $fillable = [
        'student_id', 'religion', 'nik', 'nisn',
        'blood_type', 'marital_status', 'nationality', 'photo_path',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
