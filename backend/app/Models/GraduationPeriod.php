<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GraduationPeriod extends Model
{
    protected $fillable = ['name', 'academic_year_id', 'registration_start', 'registration_end', 'graduation_date', 'venue', 'description', 'is_active'];
    protected $casts = ['registration_start' => 'date', 'registration_end' => 'date', 'graduation_date' => 'date', 'is_active' => 'boolean'];
    public function academicYear(): BelongsTo { return $this->belongsTo(AcademicYear::class); }
    public function registrations(): HasMany { return $this->hasMany(GraduationRegistration::class, 'period_id'); }
}
