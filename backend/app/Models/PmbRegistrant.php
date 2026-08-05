<?php

namespace App\Models;

use App\Traits\HasFiles;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PmbRegistrant extends Model
{
    use HasFiles;
    protected $fillable = [
        'user_id', 'pmb_period_id', 'pmb_path_id', 'registration_number',
        // Data pribadi
        'full_name', 'gender', 'birth_place', 'birth_date', 'religion',
        'nik', 'phone', 'email', 'address', 'province', 'city',
        'district', 'village', 'postal_code',
        // Orang tua
        'father_name', 'father_occupation', 'father_phone',
        'mother_name', 'mother_occupation', 'mother_phone',
        'guardian_name', 'guardian_occupation', 'guardian_phone',
        // Pendidikan
        'school_name', 'school_address', 'graduation_year', 'diploma_number',
        // Pilihan prodi
        'choice_1', 'choice_2', 'choice_3',
        // Prestasi
        'achievement_description',
        // Dokumen
        'photo_path', 'diploma_link', 'family_card_link', 'identity_link',
        // Pembayaran
        'is_paid', 'payment_proof', 'paid_at',
        // Status & admin
        'status', 'accepted_program_id', 'admin_note', 'verified_by', 'verified_at',
    ];

    protected $casts = [
        'birth_date'  => 'date',
        'is_paid'     => 'boolean',
        'paid_at'     => 'datetime',
        'verified_at' => 'datetime',
    ];

    // --- Relationships ---

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(PmbPeriod::class, 'pmb_period_id');
    }

    public function path(): BelongsTo
    {
        return $this->belongsTo(PmbPath::class, 'pmb_path_id');
    }

    public function studyProgramChoice1(): BelongsTo
    {
        return $this->belongsTo(StudyProgram::class, 'choice_1');
    }

    public function studyProgramChoice2(): BelongsTo
    {
        return $this->belongsTo(StudyProgram::class, 'choice_2');
    }

    public function studyProgramChoice3(): BelongsTo
    {
        return $this->belongsTo(StudyProgram::class, 'choice_3');
    }

    public function acceptedProgram(): BelongsTo
    {
        return $this->belongsTo(StudyProgram::class, 'accepted_program_id');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function examScores(): HasMany
    {
        return $this->hasMany(PmbExamScore::class, 'registrant_id');
    }

    public function selectionResult(): HasOne
    {
        return $this->hasOne(PmbSelectionResult::class, 'registrant_id');
    }

    public function reRegistration(): HasOne
    {
        return $this->hasOne(PmbReRegistration::class, 'registrant_id');
    }

    // --- Helpers ---

    public static function generateRegistrationNumber(int $periodId): string
    {
        $year  = now()->format('Y');
        $count = self::where('pmb_period_id', $periodId)->count() + 1;
        return "PMB-{$year}-" . str_pad($count, 5, '0', STR_PAD_LEFT);
    }
}
