<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Krs extends Model
{
    protected $table = 'krs';

    protected $fillable = [
        'student_id', 'semester_id', 'advisor_id',
        'total_credits', 'status', 'advisor_note',
        'submitted_at', 'approved_at', 'signed_by_kaprodi_at', 'signed_by_kaprodi_id',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'approved_at'  => 'datetime',
        'signed_by_kaprodi_at' => 'datetime',
    ];

    public function student(): BelongsTo { return $this->belongsTo(Student::class); }
    public function semester(): BelongsTo { return $this->belongsTo(Semester::class); }
    public function advisor(): BelongsTo { return $this->belongsTo(Lecturer::class, 'advisor_id'); }
    public function details(): HasMany { return $this->hasMany(KrsDetail::class); }

    public function recalculateCredits(): void
    {
        $total = $this->details()
            ->where('krs_details.status', 'AKTIF')
            ->join('courses', 'krs_details.course_id', '=', 'courses.id')
            ->sum('courses.credits');
        $this->update(['total_credits' => $total]);
    }
}
