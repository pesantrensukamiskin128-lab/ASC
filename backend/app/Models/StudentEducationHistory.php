<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentEducationHistory extends Model
{
    protected $fillable = [
        'student_id', 'level', 'institution_name', 'institution_address',
        'major', 'entry_year', 'graduation_year', 'diploma_number', 'gpa',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
