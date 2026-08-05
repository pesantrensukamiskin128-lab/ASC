<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamSession extends Model
{
    protected $fillable = ['exam_id', 'student_id', 'started_at', 'finished_at', 'total_score', 'tab_switches', 'activity_log', 'status'];
    protected $casts = ['started_at' => 'datetime', 'finished_at' => 'datetime', 'activity_log' => 'array'];
    public function exam(): BelongsTo { return $this->belongsTo(Exam::class); }
    public function student(): BelongsTo { return $this->belongsTo(Student::class); }
}
