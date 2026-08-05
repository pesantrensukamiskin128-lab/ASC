<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Institution extends Model
{
    protected $fillable = [
        'code', 'name', 'short_name', 'legal_entity_name',
        'address', 'phone', 'email', 'website', 'logo_path', 'letterhead_path', 'accreditation',
    ];

    public function faculties(): HasMany
    {
        return $this->hasMany(Faculty::class);
    }
}
