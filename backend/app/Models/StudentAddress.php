<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentAddress extends Model
{
    protected $fillable = [
        'student_id', 'type', 'address',
        'village', 'district', 'city', 'province', 'postal_code',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function getFullAddressAttribute(): string
    {
        return collect([$this->address, $this->village, $this->district, $this->city, $this->province, $this->postal_code])
            ->filter()->join(', ');
    }
}
