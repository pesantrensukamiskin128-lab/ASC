<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'invoice_id', 'student_id', 'payment_number', 'amount',
        'payment_method', 'payment_date', 'reference_number',
        'bank_name', 'account_number', 'account_name',
        'receipt_path', 'status', 'verified_by', 'verified_at',
        'note', 'rejection_reason',
    ];

    protected $casts = [
        'payment_date' => 'datetime',
        'verified_at'  => 'datetime',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public static function generatePaymentNumber(): string
    {
        $prefix = 'PAY';
        $date   = now()->format('Ymd');
        $count  = self::whereDate('created_at', today())->count() + 1;
        return "{$prefix}-{$date}-" . str_pad($count, 4, '0', STR_PAD_LEFT);
    }
}
