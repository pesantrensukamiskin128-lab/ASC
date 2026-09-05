<?php

namespace App\Services;

use App\Models\Alumni;
use App\Models\GraduationRegistration;
use App\Models\Student;
use App\Models\StudentSemesterSummary;
use App\Models\Thesis;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class AlumniSynchronizer
{
    /**
     * Sinkronkan satu mahasiswa berstatus Lulus ke tabel alumni.
     *
     * @param  array{graduation_date?: mixed, gpa?: mixed, predicate?: mixed, thesis_title?: mixed}  $overrides
     */
    public function sync(Student $student, array $overrides = []): ?Alumni
    {
        if ($student->status !== 'Lulus' || ! Schema::hasTable('alumni') || ! $student->study_program_id) {
            return null;
        }

        $profile = Schema::hasTable('student_profiles') ? $student->profile()->first() : null;
        $domicileAddress = Schema::hasTable('student_addresses') ? $student->domicileAddress()->first() : null;

        $registration = null;
        if (Schema::hasTable('graduation_registrations') && Schema::hasTable('graduation_periods')) {
            $registration = GraduationRegistration::with('period')
                ->where('student_id', $student->id)
                ->where('status', 'WISUDA')
                ->latest('id')
                ->first();
        }

        $graduationHistory = Schema::hasTable('student_status_histories')
            ? $student->statusHistories()->where('status', 'Lulus')->latest('start_date')->first()
            : null;

        $summary = Schema::hasTable('student_semester_summaries')
            ? StudentSemesterSummary::where('student_id', $student->id)
                ->whereNotNull('cumulative_gpa')
                ->latest('semester_id')
                ->first()
            : null;

        $thesis = Schema::hasTable('theses')
            ? Thesis::where('student_id', $student->id)
                ->whereIn('status', [Thesis::STATUS_SELESAI, Thesis::STATUS_DIPUBLIKASIKAN, 'LULUS'])
                ->latest('id')
                ->first()
            : null;

        $graduationDate = $this->dateValue(
            $overrides['graduation_date']
                ?? $registration?->period?->graduation_date
                ?? $graduationHistory?->start_date
                ?? $thesis?->completion_date
                ?? $thesis?->defense_date
                ?? now()
        );

        $gpa = $overrides['gpa']
            ?? ($registration && (float) $registration->gpa > 0 ? $registration->gpa : null)
            ?? $summary?->cumulative_gpa;
        $address = $registration?->address_current ?: $domicileAddress?->full_address;
        $entryYear = $student->entry_year ?: $this->entryYearFromNim((string) $student->nim, $graduationDate->year);

        $payload = [
            'student_id' => $student->id,
            'study_program_id' => $student->study_program_id,
            'nim' => $student->nim,
            'name' => $student->name,
            'email' => $student->email,
            'phone' => $registration?->phone ?: $student->phone,
            'entry_year' => $entryYear,
            'graduation_year' => $graduationDate->year,
            'graduation_date' => $graduationDate->toDateString(),
            'gpa' => $gpa,
            'thesis_title' => $overrides['thesis_title'] ?? $registration?->thesis_title ?? $thesis?->title,
            'predicate' => $overrides['predicate'] ?? $registration?->predicate,
            'photo_path' => $profile?->photo_path,
            'address' => $address,
            'city' => $domicileAddress?->city,
            'province' => $domicileAddress?->province,
        ];

        // Pertahankan data opsional yang telah dilengkapi manual jika sumber mahasiswa kosong.
        $payload = array_filter($payload, fn (mixed $value): bool => $value !== null && $value !== '');

        $alumni = Alumni::query()
            ->where('student_id', $student->id)
            ->orWhere('nim', $student->nim)
            ->first() ?? new Alumni;

        $alumni->fill($payload);
        if (! $alumni->exists) {
            $alumni->is_active = true;
        }
        $alumni->save();

        return $alumni;
    }

    private function dateValue(mixed $value): Carbon
    {
        return $value instanceof Carbon ? $value : Carbon::parse($value);
    }

    private function entryYearFromNim(string $nim, int $fallback): int
    {
        $year = (int) substr($nim, 0, 4);

        return $year >= 1900 && $year <= 2200 ? $year : $fallback;
    }
}
