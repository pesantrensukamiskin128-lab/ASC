<?php

namespace App\Support;

use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AcademicDocumentVerification
{
    public static function issue(string $type, Student $student, Collection $grades, ?int $semesterId = null): string
    {
        $typeCode = match ($type) {
            'khs' => 'K',
            'transcript' => 'T',
            default => throw new \InvalidArgumentException('Jenis dokumen akademik tidak didukung.'),
        };
        $encoded = implode('-', [
            '1',
            $typeCode,
            base_convert((string) $student->id, 10, 36),
            base_convert((string) ($semesterId ?? 0), 10, 36),
            substr(self::contentHash($type, $student, $grades, $semesterId), 0, 32),
            base_convert((string) ($student->studyProgram?->head_lecturer_id ?? 0), 10, 36),
            base_convert((string) now()->timestamp, 10, 36),
        ]);

        return $encoded.'_'.substr(hash_hmac('sha256', $encoded, self::key()), 0, 32);
    }

    /** @return array<string, mixed>|null */
    public static function decode(string $token): ?array
    {
        $separator = str_contains($token, '_') ? '_' : '.';
        [$encoded, $signature] = array_pad(explode($separator, $token, 2), 2, null);
        if (! $encoded || ! $signature || ! hash_equals(substr(hash_hmac('sha256', $encoded, self::key()), 0, 32), $signature)) {
            return null;
        }

        $parts = explode('-', $encoded);
        if (count($parts) !== 7 || $parts[0] !== '1' || ! in_array($parts[1], ['K', 'T'], true)) {
            return null;
        }

        try {
            $issuedAt = Carbon::createFromTimestampUTC((int) base_convert($parts[6], 36, 10))->toIso8601String();
        } catch (\Throwable) {
            return null;
        }

        return [
            'v' => 1,
            't' => $parts[1] === 'K' ? 'khs' : 'transcript',
            's' => (int) base_convert($parts[2], 36, 10),
            'm' => (int) base_convert($parts[3], 36, 10),
            'h' => $parts[4],
            'g' => (int) base_convert($parts[5], 36, 10),
            'i' => $issuedAt,
        ];
    }

    public static function matches(array $payload, string $type, Student $student, Collection $grades, ?int $semesterId = null): bool
    {
        if (($payload['t'] ?? null) !== $type
            || (int) ($payload['s'] ?? 0) !== (int) $student->id
            || (int) ($payload['m'] ?? 0) !== (int) ($semesterId ?? 0)) {
            return false;
        }

        $hash = (string) ($payload['h'] ?? '');

        return hash_equals($hash, substr(self::contentHash($type, $student, $grades, $semesterId), 0, 32))
            || hash_equals($hash, substr(self::contentHashValue($type, $student, $grades, $semesterId, false), 0, 32));
    }

    public static function contentHash(string $type, Student $student, Collection $grades, ?int $semesterId = null): string
    {
        return self::contentHashValue($type, $student, $grades, $semesterId, true);
    }

    private static function contentHashValue(string $type, Student $student, Collection $grades, ?int $semesterId, bool $includeAdvisor): string
    {
        $data = [
            'type' => $type,
            'student' => [
                'id' => (int) $student->id,
                'nim' => $student->nim,
                'name' => $student->name,
                'status' => $student->status,
                'study_program_id' => $student->study_program_id,
                'head_lecturer_id' => $student->studyProgram?->head_lecturer_id,
            ],
            'semester_id' => $semesterId,
            'grades' => $grades->map(fn ($grade) => [
                'id' => (int) $grade->id,
                'course_id' => (int) $grade->course_id,
                'semester_id' => (int) $grade->semester_id,
                'final_score' => $grade->final_score !== null ? (string) $grade->final_score : null,
                'letter_grade' => $grade->letter_grade,
                'grade_point' => $grade->grade_point !== null ? (string) $grade->grade_point : null,
                'updated_at' => $grade->updated_at?->toIso8601String(),
            ])->sortBy('id')->values()->all(),
        ];

        if ($includeAdvisor) {
            $data['student']['advisor_id'] = $student->advisor_id;
        }

        return hash('sha256', json_encode($data, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    private static function key(): string
    {
        return (string) config('app.key');
    }
}
