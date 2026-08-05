<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PenelitianPeriod extends Model
{
    protected $fillable = [
        'name', 'type', 'year', 'open_date', 'close_date', 'is_active', 'description',
    ];

    protected $casts = [
        'open_date'  => 'date',
        'close_date' => 'date',
        'is_active'  => 'boolean',
    ];

    public function penelitians(): HasMany
    {
        return $this->hasMany(Penelitian::class, 'period_id');
    }
}
