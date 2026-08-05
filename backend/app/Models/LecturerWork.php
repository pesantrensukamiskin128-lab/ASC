<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LecturerWork extends Model
{
    protected $table = 'lecturer_works';

    protected $fillable = [
        'lecturer_id', 'type', 'title', 'year', 'description', 'keywords',
        'publisher', 'isbn_issn', 'hki_number', 'published_date',
        'main_file_path', 'support_file_path', 'cover_image_path',
        'status', 'revision_note', 'repository_url',
        'verified_by', 'verified_at', 'published_by', 'published_at',
        'submitted_by', 'submitted_at',
    ];

    protected $casts = [
        'published_date' => 'date',
        'verified_at'    => 'datetime',
        'published_at'   => 'datetime',
        'submitted_at'   => 'datetime',
    ];

    const TYPE_LABELS = [
        'buku'                 => 'Buku',
        'modul_ajar'           => 'Modul Ajar',
        'hki_paten'            => 'HKI / Paten',
        'penelitian_mandiri'   => 'Penelitian Mandiri',
        'pengabdian_mandiri'   => 'Pengabdian Mandiri',
    ];

    public function lecturer(): BelongsTo   { return $this->belongsTo(Lecturer::class); }
    public function verifiedBy(): BelongsTo { return $this->belongsTo(User::class, 'verified_by'); }
    public function publishedBy(): BelongsTo{ return $this->belongsTo(User::class, 'published_by'); }
    public function submittedBy(): BelongsTo{ return $this->belongsTo(User::class, 'submitted_by'); }

    public function getTypeLabel(): string
    {
        return self::TYPE_LABELS[$this->type] ?? $this->type;
    }
}
