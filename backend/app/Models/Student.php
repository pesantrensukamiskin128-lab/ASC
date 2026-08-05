<?php

namespace App\Models;

use App\Traits\HasFiles;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Student extends Model
{
    use HasFiles;
    protected $fillable = [
        'user_id', 'study_program_id', 'academic_year_id', 'advisor_id',
        'nim', 'name', 'gender', 'birth_place', 'birth_date',
        'email', 'phone',
        'entry_year', 'status', 'current_semester',
    ];

    protected $casts = [
        'birth_date' => 'date',
    ];

    // === Core Relations ===

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function studyProgram(): BelongsTo
    {
        return $this->belongsTo(StudyProgram::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function advisor(): BelongsTo
    {
        return $this->belongsTo(Lecturer::class, 'advisor_id');
    }

    // === Extended Relations (tabel terpisah) ===

    public function profile(): HasOne
    {
        return $this->hasOne(StudentProfile::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(StudentAddress::class);
    }

    public function domicileAddress(): HasOne
    {
        return $this->hasOne(StudentAddress::class)->where('type', 'Domisili');
    }

    public function parents(): HasMany
    {
        return $this->hasMany(StudentParent::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(StudentDocument::class);
    }

    public function educationHistories(): HasMany
    {
        return $this->hasMany(StudentEducationHistory::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(StudentStatusHistory::class)->orderByDesc('start_date');
    }

    public function latestStatusHistory(): HasOne
    {
        return $this->hasOne(StudentStatusHistory::class)->latestOfMany('start_date');
    }

    public function financialRecords(): HasMany
    {
        return $this->hasMany(StudentFinancialRecord::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function scholarships(): HasMany
    {
        return $this->hasMany(StudentScholarship::class);
    }

    // === PMB Relation ===

    public function pmbRegistrant(): HasOne
    {
        return $this->hasOne(PmbRegistrant::class, 'user_id', 'user_id');
    }

    // === Helpers ===

    /** Catat perubahan status ke history */
    public function recordStatus(string $status, ?int $semesterId = null, ?string $reason = null, ?string $decreeNumber = null): StudentStatusHistory
    {
        // Tutup history sebelumnya
        $this->statusHistories()
            ->whereNull('end_date')
            ->update(['end_date' => now()]);

        // Buat record baru
        $history = $this->statusHistories()->create([
            'semester_id'    => $semesterId,
            'status'         => $status,
            'start_date'     => now(),
            'reason'         => $reason,
            'decree_number'  => $decreeNumber,
            'created_by'     => auth()->id(),
        ]);

        // Update status di tabel utama (untuk query cepat)
        $this->update(['status' => $status]);

        return $history;
    }

    /** Ambil status saat ini dari history (yang belum ditutup) */
    public function currentStatus(): ?string
    {
        return $this->latestStatusHistory?->status ?? $this->status;
    }
}
