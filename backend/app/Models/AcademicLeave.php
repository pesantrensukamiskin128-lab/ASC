<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcademicLeave extends Model
{
    protected $fillable = [
        'student_id', 'semester_id', 'type', 'reason', 'document_path',
        'status', 'start_date', 'end_date', 'leave_semester_count',
        'submitted_at', 'activated_at', 'completed_at', 'admin_note',
    ];

    protected $casts = [
        'start_date'    => 'date',
        'end_date'      => 'date',
        'submitted_at'  => 'datetime',
        'activated_at'  => 'datetime',
        'completed_at'  => 'datetime',
    ];

    public function student(): BelongsTo { return $this->belongsTo(Student::class); }
    public function semester(): BelongsTo { return $this->belongsTo(Semester::class); }
    public function approvals(): HasMany { return $this->hasMany(AcademicLeaveApproval::class)->orderBy('order'); }

    /** Cek total semester cuti mahasiswa (validasi maks) */
    public static function totalLeaveSemesters(int $studentId): int
    {
        return self::where('student_id', $studentId)
            ->whereIn('status', ['AKTIF', 'SELESAI', 'APPROVED'])
            ->sum('leave_semester_count');
    }
}
