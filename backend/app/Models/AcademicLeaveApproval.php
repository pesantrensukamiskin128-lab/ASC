<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AcademicLeaveApproval extends Model
{
    protected $fillable = [
        'academic_leave_id', 'approver_id', 'role', 'order',
        'status', 'notes', 'approved_at',
    ];

    protected $casts = ['approved_at' => 'datetime'];

    public function leave(): BelongsTo { return $this->belongsTo(AcademicLeave::class, 'academic_leave_id'); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approver_id'); }
}
