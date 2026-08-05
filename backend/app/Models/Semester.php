<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Semester extends Model
{
    protected $fillable = [
        'academic_year_id', 'name', 'type',
        'start_date', 'end_date',
        'krs_start', 'krs_end',
        'exam_mid_start', 'exam_mid_end',
        'exam_final_start', 'exam_final_end',
        'is_active',
    ];

    protected $casts = [
        'start_date'       => 'date',
        'end_date'         => 'date',
        'krs_start'        => 'date',
        'krs_end'          => 'date',
        'exam_mid_start'   => 'date',
        'exam_mid_end'     => 'date',
        'exam_final_start' => 'date',
        'exam_final_end'   => 'date',
        'is_active'        => 'boolean',
    ];

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function setAsActive(): void
    {
        self::where('id', '!=', $this->id)->update(['is_active' => false]);
        $this->update(['is_active' => true]);
    }

    public static function getActive(): ?self
    {
        return self::where('is_active', true)->first();
    }
}
