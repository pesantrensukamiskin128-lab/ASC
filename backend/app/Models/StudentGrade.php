<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentGrade extends Model
{
    protected $fillable = ['student_id', 'course_id', 'class_id', 'semester_id', 'components', 'final_score', 'letter_grade', 'grade_point', 'graded_by', 'graded_at', 'note'];
    protected $casts = ['components' => 'array', 'graded_at' => 'datetime'];
    public function student(): BelongsTo { return $this->belongsTo(Student::class); }
    public function course(): BelongsTo { return $this->belongsTo(Course::class); }
    public function class_(): BelongsTo { return $this->belongsTo(ClassModel::class, 'class_id'); }
    public function semester(): BelongsTo { return $this->belongsTo(Semester::class); }
}
