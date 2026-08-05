<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PmbPeriod extends Model
{
    protected $fillable = [
        'academic_year_id', 'name',
        'registration_start', 'registration_end',
        'selection_date', 'announcement_date',
        're_registration_start', 're_registration_end',
        'quota', 'registration_fee', 'is_active',
    ];

    protected $casts = [
        'registration_start'   => 'date',
        'registration_end'     => 'date',
        'selection_date'       => 'date',
        'announcement_date'    => 'date',
        're_registration_start' => 'date',
        're_registration_end'  => 'date',
        'is_active'            => 'boolean',
    ];

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function registrants(): HasMany
    {
        return $this->hasMany(PmbRegistrant::class, 'pmb_period_id');
    }
}
