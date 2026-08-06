<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Event extends Model
{
    protected $fillable = [
        'created_by', 'title', 'organizer', 'category', 'type',
        'location', 'meeting_link', 'event_date', 'start_time', 'end_time',
        'description', 'qr_token', 'is_open',
    ];

    protected $casts = [
        'event_date' => 'date',
        'is_open'    => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $event) {
            $event->qr_token = $event->qr_token ?: Str::random(20);
        });
    }

    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }

    public function invitees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'event_invitees')->withTimestamps();
    }

    public function attendances(): HasMany { return $this->hasMany(EventAttendance::class); }
}
