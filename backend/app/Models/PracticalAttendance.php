<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PracticalAttendance extends Model
{
    protected $fillable = ['participant_id', 'attendance_date', 'status', 'notes'];
    protected $casts = ['attendance_date' => 'date'];
    public function participant(): BelongsTo { return $this->belongsTo(PracticalParticipant::class, 'participant_id'); }
}
