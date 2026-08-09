<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PracticalLogbook extends Model
{
    protected $fillable = ['participant_id', 'activity_date', 'start_time', 'end_time', 'activity', 'result', 'notes', 'attachment_path', 'attachment_url', 'status', 'approved_by'];
    protected $casts = ['activity_date' => 'date'];
    public function participant(): BelongsTo { return $this->belongsTo(PracticalParticipant::class, 'participant_id'); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }
}
