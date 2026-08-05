<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Penelitian extends Model
{
    // Status constants
    const STATUS_DRAFT              = 'draft';
    const STATUS_REVIEW_KAPRODI     = 'review_kaprodi';
    const STATUS_SUBMITTED          = 'submitted';       // ditolak kaprodi
    const STATUS_SELEKSI_REVIEWER   = 'seleksi_reviewer';
    const STATUS_TIDAK_LOLOS        = 'tidak_lolos';
    const STATUS_KONTRAK            = 'kontrak';
    const STATUS_PELAKSANAAN_1      = 'pelaksanaan_1';
    const STATUS_MONEV              = 'monev';
    const STATUS_REVISI_KEMAJUAN    = 'revisi_kemajuan';
    const STATUS_PELAKSANAAN_2      = 'pelaksanaan_2';
    const STATUS_SEMINAR            = 'seminar';
    const STATUS_REVISI_SEMINAR     = 'revisi_seminar';
    const STATUS_LPJ                = 'lpj';
    const STATUS_REVISI_LPJ         = 'revisi_lpj';
    const STATUS_SELESAI            = 'selesai';

    protected $fillable = [
        'period_id', 'ketua_id', 'study_program_id', 'type', 'title',
        'abstract', 'keywords',
        'proposal_link', 'proposal_revision_link',
        'laporan_kemajuan_link', 'laporan_kemajuan_revision_link',
        'laporan_akhir_link', 'paper_link', 'lpj_link', 'lpj_revision_link',
        'bibliography',
        'laporan_final_path', 'paper_final_path', 'cover_image_path',
        'contract_number', 'total_dana', 'contract_link', 'contract_date',
        'seminar_date',
        'is_published', 'published_at', 'published_by', 'repository_url',
        'status',
        'kaprodi_note', 'lp2m_note',
        'kaprodi_reviewed_by', 'kaprodi_reviewed_at',
        'lp2m_reviewed_by', 'lp2m_reviewed_at',
        'submitted_at',
    ];

    protected $casts = [
        'contract_date'      => 'date',
        'seminar_date'       => 'date',
        'published_at'       => 'datetime',
        'kaprodi_reviewed_at'=> 'datetime',
        'lp2m_reviewed_at'   => 'datetime',
        'submitted_at'       => 'datetime',
        'is_published'       => 'boolean',
        'total_dana'         => 'decimal:2',
    ];

    public function period(): BelongsTo
    {
        return $this->belongsTo(PenelitianPeriod::class, 'period_id');
    }

    public function ketua(): BelongsTo
    {
        return $this->belongsTo(Lecturer::class, 'ketua_id');
    }

    public function studyProgram(): BelongsTo
    {
        return $this->belongsTo(StudyProgram::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(PenelitianMember::class);
    }

    public function dosenMembers(): HasMany
    {
        return $this->hasMany(PenelitianMember::class)->where('member_type', 'dosen');
    }

    public function mahasiswaMembers(): HasMany
    {
        return $this->hasMany(PenelitianMember::class)->where('member_type', 'mahasiswa');
    }

    public function reviewers(): HasMany
    {
        return $this->hasMany(PenelitianReviewer::class);
    }

    public function fundings(): HasMany
    {
        return $this->hasMany(PenelitianFunding::class);
    }

    public function publishedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function kaprodiReviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'kaprodi_reviewed_by');
    }

    public function lp2mReviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lp2m_reviewed_by');
    }
}
