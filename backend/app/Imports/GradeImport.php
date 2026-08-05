<?php

namespace App\Imports;

use App\Models\Course;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentGrade;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\SkipsErrors;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;

class GradeImport implements ToModel, WithHeadingRow, SkipsOnError, WithBatchInserts, WithChunkReading
{
    use SkipsErrors;

    private array $studentCache = [];
    private array $courseCache = [];
    private array $semesterCache = [];
    private int $importedCount = 0;

    public function model(array $row): ?StudentGrade
    {
        $nim = trim($row['nim'] ?? '');
        $courseCode = trim($row['kode_matakuliah'] ?? $row['kode_mk'] ?? '');
        $semesterName = trim($row['semester'] ?? '');
        $letterGrade = strtoupper(trim($row['nilai_huruf'] ?? $row['huruf'] ?? ''));
        $finalScore = $row['nilai_angka'] ?? $row['nilai'] ?? null;
        $gradePoint = $row['bobot'] ?? $row['grade_point'] ?? null;

        if (!$nim || !$courseCode || !$semesterName) return null;

        $studentId = $this->resolveStudent($nim);
        $courseId = $this->resolveCourse($courseCode);
        $semesterId = $this->resolveSemester($semesterName);

        if (!$studentId || !$courseId || !$semesterId) return null;

        // Auto-calculate grade point
        if ($letterGrade && !$gradePoint) {
            $gradePoint = $this->letterToPoint($letterGrade);
        }

        // Cek apakah sudah ada
        $existing = StudentGrade::where('student_id', $studentId)
            ->where('course_id', $courseId)
            ->where('semester_id', $semesterId)
            ->first();

        if ($existing) {
            $existing->update([
                'final_score' => $finalScore ? (float) $finalScore : $existing->final_score,
                'letter_grade' => $letterGrade ?: $existing->letter_grade,
                'grade_point' => $gradePoint ? (float) $gradePoint : $existing->grade_point,
            ]);
            return null; // Jangan create baru
        }

        $this->importedCount++;

        return new StudentGrade([
            'student_id' => $studentId,
            'course_id' => $courseId,
            'semester_id' => $semesterId,
            'final_score' => $finalScore ? (float) $finalScore : null,
            'letter_grade' => $letterGrade ?: null,
            'grade_point' => $gradePoint ? (float) $gradePoint : null,
            'note' => 'Import migrasi',
            'graded_at' => now(),
        ]);
    }

    public function getImportedCount(): int
    {
        return $this->importedCount;
    }

    public function batchSize(): int { return 100; }
    public function chunkSize(): int { return 200; }

    private function resolveStudent(string $nim): ?int
    {
        if (!isset($this->studentCache[$nim])) {
            $this->studentCache[$nim] = Student::where('nim', $nim)->value('id');
        }
        return $this->studentCache[$nim];
    }

    private function resolveCourse(string $code): ?int
    {
        $code = strtoupper($code);
        if (!isset($this->courseCache[$code])) {
            $this->courseCache[$code] = Course::where('code', $code)->value('id');
        }
        return $this->courseCache[$code];
    }

    private function resolveSemester(string $name): ?int
    {
        if (!isset($this->semesterCache[$name])) {
            $semester = Semester::where('name', $name)->first();
            if (!$semester) {
                $semester = Semester::where('name', 'like', "%{$name}%")->first();
            }
            $this->semesterCache[$name] = $semester?->id;
        }
        return $this->semesterCache[$name];
    }

    private function letterToPoint(string $letter): float
    {
        return match ($letter) {
            'A' => 4.00, 'A-' => 3.75, 'B+' => 3.50, 'B' => 3.00,
            'B-' => 2.75, 'C+' => 2.50, 'C' => 2.00, 'D' => 1.00,
            'E' => 0.00, default => 0.00,
        };
    }
}
