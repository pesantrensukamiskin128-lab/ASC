<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Exam extends Model
{
    protected $fillable = ['class_id', 'title', 'type', 'description', 'start_time', 'end_time', 'duration_minutes', 'token', 'shuffle_questions', 'shuffle_options', 'show_score', 'is_online', 'is_published', 'status', 'room_id', 'supervisor_id'];
    protected $casts = ['start_time' => 'datetime', 'end_time' => 'datetime', 'shuffle_questions' => 'boolean', 'shuffle_options' => 'boolean', 'show_score' => 'boolean', 'is_online' => 'boolean', 'is_published' => 'boolean'];
    protected $appends = ['total_score', 'questions_count'];

    public function getTotalScoreAttribute(): int
    {
        return $this->questions()->sum('score') ?? 0;
    }

    public function getQuestionsCountAttribute(): int
    {
        return $this->questions()->count();
    }
    public function class_(): BelongsTo { return $this->belongsTo(ClassModel::class, 'class_id'); }
    public function room(): BelongsTo { return $this->belongsTo(Room::class); }
    public function supervisor(): BelongsTo { return $this->belongsTo(Lecturer::class, 'supervisor_id'); }
    public function questions(): HasMany { return $this->hasMany(ExamQuestion::class)->orderBy('order'); }
    public function sessions(): HasMany { return $this->hasMany(ExamSession::class); }
}
