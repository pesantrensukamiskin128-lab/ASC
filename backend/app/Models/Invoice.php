<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    protected $fillable = [
        'invoice_number', 'student_id', 'semester_id', 'invoice_date', 'due_date',
        'total_amount', 'discount_amount', 'scholarship_amount', 'paid_amount',
        'status', 'note', 'created_by',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'due_date'     => 'date',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // === Helpers ===

    public function getOutstandingAttribute(): float
    {
        return $this->total_amount - $this->discount_amount - $this->scholarship_amount - $this->paid_amount;
    }

    public function recalculateStatus(): void
    {
        $paidTotal = $this->payments()->where('status', 'VERIFIED')->sum('amount');
        $this->paid_amount = $paidTotal;

        $netAmount = $this->total_amount - $this->discount_amount - $this->scholarship_amount;

        if ($paidTotal >= $netAmount) {
            $this->status = 'PAID';
        } elseif ($paidTotal > 0) {
            $this->status = 'PARTIAL';
        } elseif ($this->due_date < now() && $this->status !== 'WAIVED' && $this->status !== 'CANCELLED') {
            $this->status = 'OVERDUE';
        }

        $this->save();
    }

    public static function generateInvoiceNumber(int $semesterId): string
    {
        $prefix = 'INV';
        $date   = now()->format('Ymd');
        $count  = self::whereDate('created_at', today())->count() + 1;
        return "{$prefix}-{$date}-" . str_pad($count, 4, '0', STR_PAD_LEFT);
    }
}
