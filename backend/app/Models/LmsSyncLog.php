<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LmsSyncLog extends Model
{
    protected $fillable = [
        'sync_type', 'status', 'total_items', 'synced_items',
        'failed_items', 'errors', 'triggered_by', 'duration_ms',
    ];

    protected $casts = ['errors' => 'array'];

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }
}
