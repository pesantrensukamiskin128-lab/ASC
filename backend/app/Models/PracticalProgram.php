<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PracticalProgram extends Model
{
    protected $fillable = [
        'name', 'program_type', 'semester_id', 'study_program_id', 'description',
        'registration_start', 'registration_end', 'start_date', 'end_date',
        'min_credits', 'credit_value', 'is_active', 'coordinator_id',
    ];
    protected $casts = ['registration_start' => 'date', 'registration_end' => 'date', 'start_date' => 'date', 'end_date' => 'date', 'is_active' => 'boolean'];

    public function semester(): BelongsTo { return $this->belongsTo(Semester::class); }
    public function studyProgram(): BelongsTo { return $this->belongsTo(StudyProgram::class); }
    public function coordinator(): BelongsTo { return $this->belongsTo(Lecturer::class, 'coordinator_id'); }
    public function participants(): HasMany { return $this->hasMany(PracticalParticipant::class, 'program_id'); }
    public function locations(): HasMany { return $this->hasMany(PracticalLocation::class, 'program_id'); }
    public function groups(): HasMany { return $this->hasMany(PracticalGroup::class, 'program_id'); }
}
