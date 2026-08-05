<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentParent extends Model
{
    protected $fillable = [
        'student_id', 'relation', 'name', 'nik',
        'birth_place', 'birth_date', 'occupation', 'income',
        'education', 'phone', 'email', 'address', 'is_alive',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'is_alive'   => 'boolean',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
