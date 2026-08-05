<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PmbPath extends Model
{
    protected $fillable = ['code', 'name', 'description', 'requirements', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];
}
