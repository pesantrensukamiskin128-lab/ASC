<?php

namespace App\Models;

use App\Traits\HasFiles;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lecturer extends Model
{
    use HasFiles;
    protected $fillable = [
        'user_id', 'study_program_id',
        'nidn', 'nuptk', 'nip',
        'degree_front', 'degree_back',
        'full_name', 'gender', 'birth_place', 'birth_date',
        'email', 'phone', 'photo_path', 'address',
        'academic_rank', 'employment_status', 'status',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'status'     => 'boolean',
    ];

    protected $appends = ['name'];

    /** Alias "name" agar kompatibel dengan relasi yang memanggil ->name */
    public function getNameAttribute(): string
    {
        return $this->display_name;
    }

    /** Nama lengkap beserta gelar, contoh: "Dr. Ahmad Fauzi, S.Kom., M.T." */
    public function getDisplayNameAttribute(): string
    {
        $name = '';
        if ($this->degree_front) {
            $name .= $this->degree_front . ' ';
        }
        $name .= $this->full_name;
        if ($this->degree_back) {
            $name .= ', ' . trim($this->degree_back, '.') . '.';
        }
        return $name;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function studyProgram(): BelongsTo
    {
        return $this->belongsTo(StudyProgram::class);
    }

    public function advisees(): HasMany
    {
        return $this->hasMany(Student::class, 'advisor_id');
    }

    /** Jabatan struktural (bisa lebih dari satu) */
    public function positions(): HasMany
    {
        return $this->hasMany(LecturerPosition::class);
    }

    /** Jabatan aktif */
    public function activePositions(): HasMany
    {
        return $this->hasMany(LecturerPosition::class)->where('is_active', true);
    }
}
