<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventAttendance extends Model
{
    protected $fillable = [
        'event_id', 'user_id', 'guest_name', 'guest_phone',
        'guest_institution', 'guest_position', 'method', 'attended_at',
    ];

    protected $casts = ['attended_at' => 'datetime'];

    public function event(): BelongsTo { return $this->belongsTo(Event::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
