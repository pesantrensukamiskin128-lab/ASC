<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicantEducation extends Model
{
    protected $table = 'applicant_education';
    protected $fillable = ['applicant_id', 'school_name', 'school_address', 'graduation_year', 'diploma_number', 'major', 'average_score', 'achievement_description'];
    public function applicant(): BelongsTo { return $this->belongsTo(Applicant::class); }
}
