<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TracerStudy extends Model
{
    protected $fillable = [
        'alumni_id', 'period_id', 'employment_status', 'months_to_first_job',
        'first_job_relevance', 'first_salary', 'current_salary',
        'competency_feedback', 'curriculum_feedback', 'suggestion',
        'satisfaction_score', 'is_completed', 'completed_at',
    ];
    protected $casts = ['is_completed' => 'boolean', 'completed_at' => 'datetime'];
    public function alumni(): BelongsTo { return $this->belongsTo(Alumni::class); }
    public function period(): BelongsTo { return $this->belongsTo(AcademicYear::class, 'period_id'); }
}
