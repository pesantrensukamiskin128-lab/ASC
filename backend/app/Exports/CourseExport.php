<?php

namespace App\Exports;

use App\Models\Course;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class CourseExport implements FromQuery, WithHeadings, WithMapping, WithStyles, ShouldAutoSize, WithTitle
{
    public function __construct(
        private ?int $studyProgramId = null,
        private ?int $semester = null
    ) {}

    public function title(): string
    {
        return 'Mata Kuliah';
    }

    public function query()
    {
        return Course::with('studyProgram')
            ->when($this->studyProgramId, fn($q) => $q->where('study_program_id', $this->studyProgramId))
            ->when($this->semester, fn($q) => $q->where('semester', $this->semester))
            ->orderBy('study_program_id')
            ->orderBy('semester')
            ->orderBy('code');
    }

    public function headings(): array
    {
        return [
            'Kode MK', 'Nama Mata Kuliah', 'Kode Prodi', 'Program Studi',
            'SKS', 'Semester', 'Jenis', 'Status',
        ];
    }

    public function map($course): array
    {
        return [
            $course->code,
            $course->name,
            $course->studyProgram?->code ?? '-',
            $course->studyProgram?->name ?? '-',
            $course->credits,
            $course->semester,
            $course->type,
            $course->status ? 'Aktif' : 'Nonaktif',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2563EB']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
        ];
    }
}
