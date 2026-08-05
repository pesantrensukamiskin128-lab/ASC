<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Building extends Model
{
    protected $fillable = ['institution_id', 'code', 'name', 'floors', 'address', 'status'];
    protected $casts = ['status' => 'boolean'];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }
}
