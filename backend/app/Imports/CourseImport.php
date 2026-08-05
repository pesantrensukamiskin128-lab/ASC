<?php

namespace App\Imports;

use App\Models\Course;
use App\Models\StudyProgram;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\SkipsErrors;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;

class CourseImport implements ToModel, WithHeadingRow, WithValidation, SkipsOnError, WithBatchInserts, WithChunkReading
{
    use SkipsErrors;

    /** Cache kode prodi → id */
    private array $programCache = [];

    public function model(array $row): ?Course
    {
        $programId = $this->resolveProgramId($row['kode_prodi'] ?? '');
        if (!$programId) return null;

        return Course::updateOrCreate(
            ['code' => strtoupper(trim($row['kode_mk']))],
            [
                'study_program_id' => $programId,
                'name'             => trim($row['nama_mata_kuliah']),
                'credits'          => (int) ($row['sks'] ?? 2),
                'semester'         => (int) ($row['semester'] ?? 1),
                'type'             => in_array($row['jenis'] ?? '', ['Wajib', 'Pilihan', 'Praktikum'])
                                        ? $row['jenis'] : 'Wajib',
                'status'           => strtolower($row['status'] ?? 'aktif') === 'aktif',
            ]
        );
    }

    public function rules(): array
    {
        return [
            'kode_mk'          => 'required',
            'nama_mata_kuliah' => 'required',
            'kode_prodi'       => 'required',
        ];
    }

    public function batchSize(): int { return 100; }
    public function chunkSize(): int { return 200; }

    private function resolveProgramId(string $code): ?int
    {
        $code = strtoupper(trim($code));
        if (!$code) return null;

        if (!isset($this->programCache[$code])) {
            $program = StudyProgram::where('code', $code)->first();
            $this->programCache[$code] = $program?->id;
        }

        return $this->programCache[$code];
    }
}
