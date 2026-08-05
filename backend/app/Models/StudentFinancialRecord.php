<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentFinancialRecord extends Model
{
    protected $fillable = [
        'student_id', 'semester_id', 'type', 'description',
        'amount', 'paid_amount', 'due_date', 'paid_date',
        'payment_method', 'receipt_number', 'status',
    ];

    protected $casts = [
        'due_date'  => 'date',
        'paid_date' => 'date',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function getRemainingAttribute(): float
    {
        return $this->amount - $this->paid_amount;
    }
}
