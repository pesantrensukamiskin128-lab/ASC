<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Thesis extends Model
{
    protected $fillable = [
        'student_id', 'study_program_id', 'title', 'title_english', 'abstract', 'abstract_english',
        'type', 'status', 'research_field', 'keywords', 'submission_date', 'approval_date',
        'defense_date', 'completion_date', 'final_document_path', 'final_document_url',
        'final_score', 'final_grade', 'admin_note', 'proposal_file_url',
        'submission_link', 'revision_link', 'final_pdf_path',
        'is_published', 'published_at', 'published_by', 'repository_url', 'cover_image_path',
        'supervisor_assigned_by', 'supervisor_assigned_at',
    ];
    protected $casts = [
        'submission_date' => 'date', 'approval_date' => 'date',
        'defense_date' => 'date', 'completion_date' => 'date',
        'published_at' => 'datetime', 'supervisor_assigned_at' => 'datetime',
        'is_published' => 'boolean',
    ];

    // Status constants
    const STATUS_DRAFT                 = 'DRAFT';
    const STATUS_PENGAJUAN_JUDUL       = 'PENGAJUAN_JUDUL';
    const STATUS_JUDUL_DITOLAK         = 'JUDUL_DITOLAK';
    const STATUS_SEMINAR_PROPOSAL      = 'SEMINAR_PROPOSAL';
    const STATUS_REVISI_PROPOSAL       = 'REVISI_PROPOSAL';
    const STATUS_PEMERIKSAAN_REVISI    = 'PEMERIKSAAN_REVISI';
    const STATUS_PENUNJUKAN_PEMBIMBING = 'PENUNJUKAN_PEMBIMBING';
    const STATUS_BIMBINGAN             = 'BIMBINGAN';
    const STATUS_SIDANG                = 'SIDANG';
    const STATUS_REVISI_SIDANG         = 'REVISI_SIDANG';
    const STATUS_SELESAI               = 'SELESAI';
    const STATUS_DIPUBLIKASIKAN        = 'DIPUBLIKASIKAN';
    const STATUS_GAGAL                 = 'GAGAL';

    public function student(): BelongsTo { return $this->belongsTo(Student::class); }
    public function studyProgram(): BelongsTo { return $this->belongsTo(StudyProgram::class); }
    public function supervisors(): HasMany { return $this->hasMany(ThesisSupervisor::class)->orderBy('role'); }
    public function examiners(): HasMany { return $this->hasMany(ThesisExaminer::class); }
    public function guidances(): HasMany { return $this->hasMany(ThesisGuidance::class)->orderByDesc('guidance_date'); }
    public function defenses(): HasMany { return $this->hasMany(ThesisDefense::class)->orderByDesc('defense_date'); }
    public function titleHistories(): HasMany { return $this->hasMany(ThesisTitleHistory::class); }
    public function revisionReviews(): HasMany { return $this->hasMany(\App\Models\ThesisRevisionReview::class)->orderByDesc('created_at'); }
    public function seminarResults(): HasMany { return $this->hasMany(\App\Models\ThesisSeminarResult::class)->orderByDesc('created_at'); }
    public function publishedBy(): BelongsTo { return $this->belongsTo(\App\Models\User::class, 'published_by'); }
    public function supervisorAssignedBy(): BelongsTo { return $this->belongsTo(\App\Models\User::class, 'supervisor_assigned_by'); }
}
