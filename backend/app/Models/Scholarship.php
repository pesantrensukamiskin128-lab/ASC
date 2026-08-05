<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Scholarship extends Model
{
    protected $fillable = [
        'code', 'name', 'provider', 'description', 'requirements',
        'amount', 'type', 'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function students(): HasMany
    {
        return $this->hasMany(StudentScholarship::class);
    }
}
