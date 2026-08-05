<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamAnswer extends Model
{
    protected $fillable = ['exam_id', 'student_id', 'question_id', 'answer', 'file_path', 'is_correct', 'score'];
    protected $casts = ['is_correct' => 'boolean'];
    public function exam(): BelongsTo { return $this->belongsTo(Exam::class); }
    public function student(): BelongsTo { return $this->belongsTo(Student::class); }
    public function question(): BelongsTo { return $this->belongsTo(ExamQuestion::class, 'question_id'); }
}
