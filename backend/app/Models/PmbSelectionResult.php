<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PmbSelectionResult extends Model
{
    protected $fillable = [
        'registrant_id', 'final_score', 'rank',
        'recommendation', 'final_status',
        'decided_by', 'decided_at', 'note',
    ];

    protected $casts = [
        'decided_at' => 'datetime',
    ];

    public function registrant(): BelongsTo
    {
        return $this->belongsTo(PmbRegistrant::class, 'registrant_id');
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
