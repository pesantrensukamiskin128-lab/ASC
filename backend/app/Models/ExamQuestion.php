<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamQuestion extends Model
{
    protected $fillable = ['exam_id', 'order', 'type', 'question', 'options', 'correct_answer', 'score', 'explanation'];
    protected $casts = ['options' => 'array'];
    public function exam(): BelongsTo { return $this->belongsTo(Exam::class); }
}
