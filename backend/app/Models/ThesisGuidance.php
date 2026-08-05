<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ThesisGuidance extends Model
{
    protected $fillable = ['thesis_id', 'supervisor_id', 'guidance_date', 'topic', 'discussion', 'suggestion', 'student_note', 'chapter_reviewed', 'progress_percentage', 'status'];
    protected $casts = ['guidance_date' => 'date'];
    public function thesis(): BelongsTo { return $this->belongsTo(Thesis::class); }
    public function supervisor(): BelongsTo { return $this->belongsTo(Lecturer::class, 'supervisor_id'); }
}
