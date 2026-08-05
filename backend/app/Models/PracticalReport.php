<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PracticalReport extends Model
{
    protected $fillable = ['participant_id', 'title', 'abstract', 'file_path', 'file_url', 'status', 'reviewer_notes', 'reviewed_by', 'submitted_at', 'approved_at'];
    protected $casts = ['submitted_at' => 'datetime', 'approved_at' => 'datetime'];
    public function participant(): BelongsTo { return $this->belongsTo(PracticalParticipant::class, 'participant_id'); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewed_by'); }
}
