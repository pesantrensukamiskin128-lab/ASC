<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LegacyMigrationMap extends Model
{
    protected $fillable = [
        'source_system', 'entity', 'source_id',
        'target_table', 'target_id', 'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];
}
