<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PmbReRegistration extends Model
{
    protected $fillable = [
        'registrant_id', 'is_completed', 'nim',
        'payment_proof', 'completed_at', 'processed_by', 'note',
    ];

    protected $casts = [
        'is_completed' => 'boolean',
        'completed_at' => 'datetime',
    ];

    public function registrant(): BelongsTo
    {
        return $this->belongsTo(PmbRegistrant::class, 'registrant_id');
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}
