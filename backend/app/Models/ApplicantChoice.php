<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicantChoice extends Model
{
    protected $fillable = ['applicant_id', 'study_program_id', 'priority'];
    public function applicant(): BelongsTo { return $this->belongsTo(Applicant::class); }
    public function studyProgram(): BelongsTo { return $this->belongsTo(StudyProgram::class); }
}
