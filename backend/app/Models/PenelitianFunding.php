<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PenelitianFunding extends Model
{
    protected $fillable = [
        'penelitian_id', 'stage', 'amount', 'keterangan', 'status',
        'bukti_transfer_path',
        'allocated_by', 'allocated_at',
        'disbursed_by', 'disbursed_at',
    ];

    protected $casts = [
        'amount'       => 'decimal:2',
        'allocated_at' => 'datetime',
        'disbursed_at' => 'datetime',
    ];

    public function penelitian(): BelongsTo
    {
        return $this->belongsTo(Penelitian::class);
    }

    public function allocatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'allocated_by');
    }

    public function disbursedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disbursed_by');
    }
}
