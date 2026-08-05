<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PenelitianMember extends Model
{
    protected $fillable = [
        'penelitian_id', 'member_type', 'lecturer_id', 'student_id', 'role',
    ];

    public function penelitian(): BelongsTo
    {
        return $this->belongsTo(Penelitian::class);
    }

    public function lecturer(): BelongsTo
    {
        return $this->belongsTo(Lecturer::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
