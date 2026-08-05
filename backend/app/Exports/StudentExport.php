<?php

namespace App\Exports;

use App\Models\Student;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class StudentExport implements FromQuery, WithHeadings, WithMapping, WithStyles, ShouldAutoSize, WithTitle
{
    public function __construct(
        private ?int $studyProgramId = null,
        private ?string $status = null
    ) {}

    public function title(): string
    {
        return 'Mahasiswa';
    }

    public function query()
    {
        return Student::with(['studyProgram', 'advisor'])
            ->when($this->studyProgramId, fn($q) => $q->where('study_program_id', $this->studyProgramId))
            ->when($this->status, fn($q) => $q->where('status', $this->status))
            ->orderBy('study_program_id')
            ->orderBy('nim');
    }

    public function headings(): array
    {
        return [
            'NIM', 'Nama Lengkap', 'Kode Prodi', 'Program Studi',
            'Jenis Kelamin', 'Tempat Lahir', 'Tanggal Lahir',
            'Email', 'No. HP', 'Asal Sekolah',
            'Tahun Masuk', 'Semester Aktif', 'Status',
            'Dosen Wali',
        ];
    }

    public function map($student): array
    {
        return [
            $student->nim,
            $student->name,
            $student->studyProgram?->code ?? '',
            $student->studyProgram?->name ?? '',
            $student->gender === 'L' ? 'Laki-laki' : ($student->gender === 'P' ? 'Perempuan' : ''),
            $student->birth_place ?? '',
            $student->birth_date?->format('Y-m-d') ?? '',
            $student->email ?? '',
            $student->phone ?? '',
            $student->origin_school ?? '',
            $student->entry_year ?? '',
            $student->current_semester ?? 1,
            $student->status,
            $student->advisor?->name ?? '',
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
