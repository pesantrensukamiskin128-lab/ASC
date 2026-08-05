<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransferredGrade extends Model
{
    protected $fillable = [
        'student_id', 'application_id', 'source_course_id', 'target_course_id',
        'recognized_credits', 'grade_letter', 'grade_point', 'semester_label',
        'is_included_in_gpa', 'is_included_in_transcript', 'is_included_in_total_credits',
    ];
    protected $casts = ['is_included_in_gpa' => 'boolean', 'is_included_in_transcript' => 'boolean', 'is_included_in_total_credits' => 'boolean'];
    public function student(): BelongsTo { return $this->belongsTo(Student::class); }
    public function application(): BelongsTo { return $this->belongsTo(TransferCreditApplication::class, 'application_id'); }
    public function sourceCourse(): BelongsTo { return $this->belongsTo(TransferSourceCourse::class, 'source_course_id'); }
    public function targetCourse(): BelongsTo { return $this->belongsTo(Course::class, 'target_course_id'); }
}
