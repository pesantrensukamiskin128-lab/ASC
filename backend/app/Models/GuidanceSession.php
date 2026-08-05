<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GuidanceSession extends Model
{
    protected $fillable = [
        'student_id', 'advisor_id', 'semester_id', 'topic', 'description',
        'type', 'mode', 'scheduled_date', 'scheduled_time', 'location',
        'status', 'requested_by',
    ];

    protected $casts = [
        'scheduled_date' => 'date',
    ];

    public function student(): BelongsTo { return $this->belongsTo(Student::class); }
    public function advisor(): BelongsTo { return $this->belongsTo(Lecturer::class, 'advisor_id'); }
    public function semester(): BelongsTo { return $this->belongsTo(Semester::class); }
    public function requestedBy(): BelongsTo { return $this->belongsTo(User::class, 'requested_by'); }
    public function notes(): HasMany { return $this->hasMany(GuidanceNote::class, 'session_id')->orderBy('created_at'); }
}
