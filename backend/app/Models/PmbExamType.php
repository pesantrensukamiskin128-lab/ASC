<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PmbExamType extends Model
{
    protected $fillable = ['code', 'name', 'weight', 'passing_grade', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];
}
