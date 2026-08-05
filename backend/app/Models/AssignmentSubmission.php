<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssignmentSubmission extends Model
{
    protected $fillable = ['assignment_id', 'student_id', 'content', 'file_url', 'file_path', 'score', 'feedback', 'submitted_at', 'graded_at', 'graded_by'];
    protected $casts = ['submitted_at' => 'datetime', 'graded_at' => 'datetime'];
    public function assignment(): BelongsTo { return $this->belongsTo(Assignment::class); }
    public function student(): BelongsTo { return $this->belongsTo(Student::class); }
}
