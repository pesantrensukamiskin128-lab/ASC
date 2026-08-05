<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PracticalParticipant extends Model
{
    protected $fillable = ['program_id', 'student_id', 'group_id', 'location_id', 'supervisor_id', 'status', 'start_date', 'end_date', 'notes'];
    protected $casts = ['start_date' => 'date', 'end_date' => 'date'];

    public function program(): BelongsTo { return $this->belongsTo(PracticalProgram::class, 'program_id'); }
    public function student(): BelongsTo { return $this->belongsTo(Student::class); }
    public function group(): BelongsTo { return $this->belongsTo(PracticalGroup::class, 'group_id'); }
    public function location(): BelongsTo { return $this->belongsTo(PracticalLocation::class, 'location_id'); }
    public function supervisor(): BelongsTo { return $this->belongsTo(Lecturer::class, 'supervisor_id'); }
    public function logbooks(): HasMany { return $this->hasMany(PracticalLogbook::class, 'participant_id'); }
    public function attendances(): HasMany { return $this->hasMany(PracticalAttendance::class, 'participant_id'); }
    public function assessments(): HasMany { return $this->hasMany(PracticalAssessment::class, 'participant_id'); }
    public function reports(): HasMany { return $this->hasMany(PracticalReport::class, 'participant_id'); }
}
