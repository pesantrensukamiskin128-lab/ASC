<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PracticalAttendance extends Model
{
    protected $fillable = ['participant_id', 'attendance_date', 'status', 'notes', 'latitude', 'longitude', 'proof_url', 'approval_status', 'rejection_note', 'approved_by', 'approved_at'];
    protected $casts = ['attendance_date' => 'date', 'latitude' => 'float', 'longitude' => 'float', 'approved_at' => 'datetime'];
    public function participant(): BelongsTo { return $this->belongsTo(PracticalParticipant::class, 'participant_id'); }
    public function approver(): BelongsTo { return $this->belongsTo(\App\Models\User::class, 'approved_by'); }
}
