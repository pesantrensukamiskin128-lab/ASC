<?php

namespace App\Imports;

use App\Models\GradeSchema;
use App\Models\Student;
use App\Models\StudentGrade;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;

class GradeClassImport implements ToCollection, WithHeadingRow, SkipsEmptyRows
{
    public int   $imported  = 0;
    public int   $skipped   = 0;
    public array $errors    = [];

    private int    $classId;
    private int    $courseId;
    private int    $semesterId;
    private array  $components; // [['name'=>'UTS','weight'=>30], ...]
    private ?GradeSchema $schema;

    public function __construct(int $classId, int $courseId, int $semesterId, array $components)
    {
        $this->classId    = $classId;
        $this->courseId   = $courseId;
        $this->semesterId = $semesterId;
        $this->components = $components;
        $this->schema     = GradeSchema::where('is_default', true)->first();
    }

    public function collection(Collection $rows)
    {
        foreach ($rows as $i => $row) {
            $rowNum = $i + 2;
            $nim = trim($row['nim'] ?? '');
            if (!$nim) { $this->skipped++; continue; }

            $student = Student::where('nim', $nim)->first();
            if (!$student) {
                $this->skipped++;
                $this->errors[] = "Baris {$rowNum}: NIM {$nim} tidak ditemukan.";
                continue;
            }

            // Ambil nilai tiap komponen dari kolom header yang sesuai
            $componentData = [];
            $finalScore = 0;
            foreach ($this->components as $comp) {
                // Cari kolom berdasarkan nama komponen (lowercase, strip spasi)
                $key = strtolower(str_replace([' ', '-'], '_', $comp['name']));
                // Coba beberapa variasi key
                $score = $row[$key]
                    ?? $row[strtolower($comp['name'])]
                    ?? $row[$comp['name']]
                    ?? null;

                $scoreVal = is_numeric($score) ? (float) $score : 0;
                $scoreVal = max(0, min(100, $scoreVal));

                $componentData[] = [
                    'name'   => $comp['name'],
                    'weight' => $comp['weight'],
                    'score'  => $scoreVal,
                ];
                $finalScore += ($scoreVal * $comp['weight']) / 100;
            }

            $finalScore = round($finalScore, 2);
            $conversion = $this->schema?->convertScore($finalScore);

            try {
                StudentGrade::updateOrCreate(
                    [
                        'student_id'  => $student->id,
                        'course_id'   => $this->courseId,
                        'semester_id' => $this->semesterId,
                    ],
                    [
                        'class_id'     => $this->classId,
                        'components'   => $componentData,
                        'final_score'  => $finalScore,
                        'letter_grade' => $conversion['letter'] ?? null,
                        'grade_point'  => $conversion['grade_point'] ?? null,
                        'graded_by'    => auth()->id(),
                        'graded_at'    => now(),
                    ]
                );
                $this->imported++;
            } catch (\Throwable $e) {
                $this->skipped++;
                $this->errors[] = "Baris {$rowNum} ({$nim}): " . $e->getMessage();
            }
        }
    }
}
