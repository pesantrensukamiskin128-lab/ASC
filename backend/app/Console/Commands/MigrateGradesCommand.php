<?php

namespace App\Console\Commands;

use App\Models\Course;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentGrade;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class MigrateGradesCommand extends Command
{
    protected $signature = 'migrate:grades {file : Path to Excel/CSV file}';
    protected $description = 'Import riwayat nilai mahasiswa dari Excel (untuk migrasi data eksisting)';

    private int $imported = 0;
    private int $skipped = 0;
    private array $errors = [];

    public function handle(): int
    {
        $file = $this->argument('file');

        if (!file_exists($file)) {
            $this->error("File tidak ditemukan: {$file}");
            return 1;
        }

        $this->info("Memulai import nilai dari: {$file}");
        $this->info("Loading...");

        // Read Excel
        $rows = Excel::toArray(null, $file)[0] ?? [];

        if (empty($rows)) {
            $this->error("File kosong atau format tidak valid.");
            return 1;
        }

        // Header row
        $headers = array_map(fn($h) => strtolower(trim($h)), $rows[0]);
        unset($rows[0]);

        $this->info("Ditemukan " . count($rows) . " baris data.");
        $this->newLine();

        $bar = $this->output->createProgressBar(count($rows));
        $bar->start();

        DB::beginTransaction();
        try {
            foreach ($rows as $index => $row) {
                $data = array_combine($headers, $row);
                $this->processRow($data, $index + 2); // +2 karena 1-indexed + header
                $bar->advance();
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            $this->newLine(2);
            $this->error("Fatal error: " . $e->getMessage());
            return 1;
        }

        $bar->finish();
        $this->newLine(2);

        // Summary
        $this->info("✅ Import selesai!");
        $this->table(['Keterangan', 'Jumlah'], [
            ['Berhasil diimport', $this->imported],
            ['Dilewati (skip)', $this->skipped],
            ['Total error', count($this->errors)],
        ]);

        if (!empty($this->errors)) {
            $this->warn("Detail error (max 20):");
            foreach (array_slice($this->errors, 0, 20) as $err) {
                $this->line("  - {$err}");
            }
        }

        return 0;
    }

    private function processRow(array $data, int $lineNumber): void
    {
        $nim = trim($data['nim'] ?? '');
        $courseCode = trim($data['kode_matakuliah'] ?? $data['kode_mk'] ?? '');
        $semesterName = trim($data['semester'] ?? '');
        $letterGrade = strtoupper(trim($data['nilai_huruf'] ?? $data['huruf'] ?? ''));
        $finalScore = $data['nilai_angka'] ?? $data['nilai'] ?? null;
        $gradePoint = $data['bobot'] ?? $data['grade_point'] ?? null;

        // Validasi
        if (!$nim || !$courseCode || !$semesterName) {
            $this->errors[] = "Baris {$lineNumber}: NIM, kode_matakuliah, atau semester kosong.";
            $this->skipped++;
            return;
        }

        // Cari mahasiswa
        $student = Student::where('nim', $nim)->first();
        if (!$student) {
            $this->errors[] = "Baris {$lineNumber}: Mahasiswa NIM '{$nim}' tidak ditemukan.";
            $this->skipped++;
            return;
        }

        // Cari mata kuliah
        $course = Course::where('code', $courseCode)->first();
        if (!$course) {
            $this->errors[] = "Baris {$lineNumber}: Mata kuliah '{$courseCode}' tidak ditemukan.";
            $this->skipped++;
            return;
        }

        // Cari semester
        $semester = Semester::where('name', $semesterName)->first();
        if (!$semester) {
            // Coba cari partial match
            $semester = Semester::where('name', 'like', "%{$semesterName}%")->first();
        }
        if (!$semester) {
            $this->errors[] = "Baris {$lineNumber}: Semester '{$semesterName}' tidak ditemukan.";
            $this->skipped++;
            return;
        }

        // Auto-calculate grade point jika tidak diisi
        if ($letterGrade && !$gradePoint) {
            $gradePoint = $this->letterToPoint($letterGrade);
        }

        // Insert/Update nilai
        StudentGrade::updateOrCreate(
            [
                'student_id' => $student->id,
                'course_id'  => $course->id,
                'semester_id' => $semester->id,
            ],
            [
                'final_score'  => $finalScore ? (float) $finalScore : null,
                'letter_grade' => $letterGrade ?: null,
                'grade_point'  => $gradePoint ? (float) $gradePoint : null,
                'note'         => 'Migrasi data eksisting',
                'graded_at'    => now(),
            ]
        );

        $this->imported++;
    }

    private function letterToPoint(string $letter): float
    {
        return match ($letter) {
            'A'  => 4.00,
            'A-' => 3.75,
            'B+' => 3.50,
            'B'  => 3.00,
            'B-' => 2.75,
            'C+' => 2.50,
            'C'  => 2.00,
            'D'  => 1.00,
            'E'  => 0.00,
            default => 0.00,
        };
    }
}
