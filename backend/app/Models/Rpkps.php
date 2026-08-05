<?php

namespace App\Models;

use App\Traits\HasFiles;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Rpkps extends Model
{
    use HasFiles;

    protected $table = 'rpkps';

    protected $fillable = [
        'course_id', 'curriculum_id', 'academic_year_id', 'semester_id',
        'lecturer_id', 'coordinator_id', 'code',
        'course_description', 'course_urgency', 'course_scope',
        'course_position', 'prerequisites',
        'assessment_components',
        'status', 'version', 'parent_id',
        'approved_by', 'approved_at', 'revision_note',
        'verification_code', 'pdf_path',
    ];

    protected $casts = [
        'assessment_components' => 'array',
        'approved_at'          => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Rpkps $rpkps) {
            if (!$rpkps->verification_code) {
                $rpkps->verification_code = Str::uuid()->toString();
            }
        });
    }

    // === Relations ===

    public function course(): BelongsTo { return $this->belongsTo(Course::class); }
    public function curriculum(): BelongsTo { return $this->belongsTo(Curriculum::class); }
    public function academicYear(): BelongsTo { return $this->belongsTo(AcademicYear::class); }
    public function semester(): BelongsTo { return $this->belongsTo(Semester::class); }
    public function lecturer(): BelongsTo { return $this->belongsTo(Lecturer::class); }
    public function coordinator(): BelongsTo { return $this->belongsTo(Lecturer::class, 'coordinator_id'); }
    public function approvedByUser(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }
    public function parent(): BelongsTo { return $this->belongsTo(Rpkps::class, 'parent_id'); }

    /** CPL yang dipilih */
    public function cpls(): BelongsToMany
    {
        return $this->belongsToMany(LearningOutcome::class, 'rpkps_cpls', 'rpkps_id', 'learning_outcome_id')
            ->withTimestamps();
    }

    public function cpmks(): HasMany { return $this->hasMany(RpkpsCpmk::class)->orderBy('order'); }
    public function learningMaterials(): HasMany { return $this->hasMany(RpkpsLearningMaterial::class)->orderBy('order'); }
    public function weeklyPlans(): HasMany { return $this->hasMany(RpkpsWeeklyPlan::class)->orderBy('week_number'); }
    public function assessments(): HasMany { return $this->hasMany(RpkpsAssessment::class)->orderBy('order'); }
    public function rubrics(): HasMany { return $this->hasMany(RpkpsRubric::class)->orderBy('order'); }
    public function references(): HasMany { return $this->hasMany(RpkpsReference::class)->orderBy('type')->orderBy('order'); }
    public function approvals(): HasMany { return $this->hasMany(RpkpsApproval::class)->orderByDesc('created_at'); }

    // === Helpers ===

    public static function generateCode(int $courseId): string
    {
        $course = Course::find($courseId);
        $year   = now()->format('Y');
        $count  = self::whereYear('created_at', $year)->count() + 1;
        return "RPKPS-{$course->code}-{$year}-" . str_pad($count, 5, '0', STR_PAD_LEFT);
    }

    /** Duplikasi RPKPS ke semester baru */
    public function duplicate(int $academicYearId, ?int $semesterId = null): self
    {
        $new = $this->replicate(['code', 'status', 'approved_by', 'approved_at', 'revision_note', 'verification_code', 'pdf_path']);
        $new->academic_year_id = $academicYearId;
        $new->semester_id      = $semesterId;
        $new->code             = self::generateCode($this->course_id);
        $new->status           = 'DRAFT';
        $new->version          = $this->version + 1;
        $new->parent_id        = $this->id;
        $new->save();

        // Duplikasi relasi
        $this->cpls->each(fn($cpl) => $new->cpls()->attach($cpl->id));

        $this->cpmks->each(function ($cpmk) use ($new) {
            $newCpmk = $new->cpmks()->create($cpmk->only(['code', 'description', 'order']));
            $cpmk->subCpmks->each(fn($sub) => $newCpmk->subCpmks()->create($sub->only(['code', 'description', 'order'])));
            $cpmk->cpls->each(fn($cpl) => $newCpmk->cpls()->attach($cpl->id));
        });

        $this->learningMaterials->each(fn($m) => $new->learningMaterials()->create($m->only(['topic', 'subtopics', 'order'])));
        $this->weeklyPlans->each(fn($w) => $new->weeklyPlans()->create($w->only([
            'week_number', 'sub_cpmk', 'indicators', 'learning_material', 'methods',
            'lecturer_activity', 'student_activity', 'assessment_form', 'assessment_criteria',
            'media', 'duration', 'weight',
        ])));
        $this->assessments->each(fn($a) => $new->assessments()->create($a->only(['name', 'weight', 'description', 'order'])));
        $this->rubrics->each(fn($r) => $new->rubrics()->create($r->only(['criteria', 'excellent', 'good', 'fair', 'poor', 'order'])));
        $this->references->each(fn($r) => $new->references()->create($r->only(['type', 'category', 'title', 'author', 'year', 'publisher', 'isbn_doi', 'url', 'order'])));

        return $new;
    }
}
