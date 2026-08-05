<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Alumni extends Model
{
    protected $table = 'alumni';
    protected $fillable = [
        'student_id', 'study_program_id', 'nim', 'name', 'email', 'phone',
        'entry_year', 'graduation_year', 'graduation_date', 'gpa', 'thesis_title',
        'predicate', 'photo_path', 'address', 'city', 'province', 'is_active',
    ];
    protected $casts = ['is_active' => 'boolean'];

    public function student(): BelongsTo { return $this->belongsTo(Student::class); }
    public function studyProgram(): BelongsTo { return $this->belongsTo(StudyProgram::class); }
    public function employments(): HasMany { return $this->hasMany(AlumniEmployment::class)->orderByDesc('start_date'); }
    public function tracerStudies(): HasMany { return $this->hasMany(TracerStudy::class); }
    public function furtherStudies(): HasMany { return $this->hasMany(AlumniFurtherStudy::class); }
    public function latestEmployment(): HasOne { return $this->hasOne(AlumniEmployment::class)->where('is_current', true); }
}
