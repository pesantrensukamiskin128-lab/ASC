<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PracticalAssessment extends Model
{
    protected $fillable = ['participant_id', 'component', 'score', 'weight', 'notes', 'assessed_by'];
    public function participant(): BelongsTo { return $this->belongsTo(PracticalParticipant::class, 'participant_id'); }
    public function assessor(): BelongsTo { return $this->belongsTo(User::class, 'assessed_by'); }
}
