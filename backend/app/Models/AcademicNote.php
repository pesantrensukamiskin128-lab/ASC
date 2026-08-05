<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AcademicNote extends Model
{
    protected $fillable = [
        'student_id', 'advisor_id', 'semester_id', 'type', 'content', 'is_important',
    ];

    protected $casts = ['is_important' => 'boolean'];

    public function student(): BelongsTo { return $this->belongsTo(Student::class); }
    public function advisor(): BelongsTo { return $this->belongsTo(Lecturer::class, 'advisor_id'); }
    public function semester(): BelongsTo { return $this->belongsTo(Semester::class); }
}
