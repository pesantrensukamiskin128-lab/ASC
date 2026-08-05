<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class TransferCreditApplication extends Model
{
    protected $fillable = [
        'uuid', 'student_id', 'source_institution_id', 'source_study_program',
        'source_degree', 'source_student_number', 'source_total_credits',
        'source_gpa', 'source_semesters', 'transfer_type', 'application_date',
        'status', 'submitted_by', 'admin_note',
    ];

    protected $casts = ['application_date' => 'date'];

    protected static function booted(): void
    {
        static::creating(function ($model) { $model->uuid = $model->uuid ?? Str::uuid()->toString(); });
    }

    public function student(): BelongsTo { return $this->belongsTo(Student::class); }
    public function sourceInstitution(): BelongsTo { return $this->belongsTo(TransferSourceInstitution::class, 'source_institution_id'); }
    public function submittedBy(): BelongsTo { return $this->belongsTo(User::class, 'submitted_by'); }
    public function documents(): HasMany { return $this->hasMany(TransferDocument::class, 'application_id'); }
    public function sourceCourses(): HasMany { return $this->hasMany(TransferSourceCourse::class, 'application_id'); }
    public function conversions(): HasMany { return $this->hasMany(TransferCourseConversion::class, 'application_id'); }
    public function evaluations(): HasMany { return $this->hasMany(TransferEvaluation::class, 'application_id'); }
    public function approvals(): HasMany { return $this->hasMany(TransferApproval::class, 'application_id')->orderBy('approval_level'); }
    public function transferredGrades(): HasMany { return $this->hasMany(TransferredGrade::class, 'application_id'); }
    public function placement(): HasOne { return $this->hasOne(StudentAcademicPlacement::class, 'application_id'); }
}
