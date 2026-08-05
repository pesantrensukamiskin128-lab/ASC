<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentDocument extends Model
{
    protected $fillable = [
        'student_id', 'type', 'name',
        'file_path', 'file_url', 'document_number',
        'issued_date', 'expiry_date',
        'is_verified', 'verified_by', 'verified_at',
    ];

    protected $casts = [
        'issued_date'  => 'date',
        'expiry_date'  => 'date',
        'is_verified'  => 'boolean',
        'verified_at'  => 'datetime',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function verifiedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
