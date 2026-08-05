<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Applicant extends Model
{
    protected $fillable = [
        'user_id', 'pmb_period_id', 'pmb_path_id', 'registration_number',
        'full_name', 'gender', 'birth_place', 'birth_date', 'religion',
        'nik', 'phone', 'email', 'address', 'province', 'city', 'district', 'village', 'postal_code',
        'photo_path', 'is_paid', 'payment_proof', 'paid_at',
        'status', 'accepted_program_id', 'admin_note', 'verified_by', 'verified_at',
    ];
    protected $casts = ['birth_date' => 'date', 'is_paid' => 'boolean', 'paid_at' => 'datetime', 'verified_at' => 'datetime'];
    
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function period(): BelongsTo { return $this->belongsTo(PmbPeriod::class, 'pmb_period_id'); }
    public function path(): BelongsTo { return $this->belongsTo(PmbPath::class, 'pmb_path_id'); }
    public function acceptedProgram(): BelongsTo { return $this->belongsTo(StudyProgram::class, 'accepted_program_id'); }
    public function choices(): HasMany { return $this->hasMany(ApplicantChoice::class)->orderBy('priority'); }
    public function family(): HasMany { return $this->hasMany(ApplicantFamily::class); }
    public function education(): HasOne { return $this->hasOne(ApplicantEducation::class); }
    public function documents(): HasMany { return $this->hasMany(ApplicantDocument::class); }

    public static function generateRegistrationNumber(int $periodId): string
    {
        $year = now()->format('Y');
        $count = self::where('pmb_period_id', $periodId)->count() + 1;
        return "PMB-{$year}-" . str_pad($count, 5, '0', STR_PAD_LEFT);
    }
}
