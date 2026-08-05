<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Room extends Model
{
    protected $fillable = [
        'building_id', 'code', 'name', 'floor',
        'capacity', 'type', 'facilities', 'status',
    ];

    protected $casts = [
        'facilities' => 'array',
        'status'     => 'boolean',
    ];

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
    }
}
