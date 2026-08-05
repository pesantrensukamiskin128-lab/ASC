<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AcademicWarning extends Model
{
    protected $fillable = [
        'student_id', 'advisor_id', 'semester_id', 'level', 'reason',
        'description', 'ipk', 'ips', 'requires_consultation',
        'consultation_done', 'consultation_deadline', 'resolution_note', 'status',
    ];

    protected $casts = [
        'requires_consultation' => 'boolean',
        'consultation_done'     => 'boolean',
        'consultation_deadline' => 'date',
    ];

    public function student(): BelongsTo { return $this->belongsTo(Student::class); }
    public function advisor(): BelongsTo { return $this->belongsTo(Lecturer::class, 'advisor_id'); }
    public function semester(): BelongsTo { return $this->belongsTo(Semester::class); }
}
