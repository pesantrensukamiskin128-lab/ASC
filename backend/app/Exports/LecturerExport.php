<?php

namespace App\Exports;

use App\Models\Lecturer;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class LecturerExport implements FromQuery, WithHeadings, WithMapping, WithStyles, ShouldAutoSize, WithTitle
{
    public function __construct(private ?int $studyProgramId = null) {}

    public function title(): string { return 'Dosen'; }

    public function query()
    {
        return Lecturer::with('studyProgram')
            ->when($this->studyProgramId, fn($q) => $q->where('study_program_id', $this->studyProgramId))
            ->orderBy('full_name');
    }

    public function headings(): array
    {
        return [
            'NIDN', 'NUPTK', 'NIP', 'Gelar Depan', 'Nama Lengkap', 'Gelar Belakang',
            'Kode Prodi', 'Program Studi',
            'Jenis Kelamin', 'Tempat Lahir', 'Tanggal Lahir',
            'Email', 'No. HP', 'Jabatan Akademik',
            'Status Kepegawaian', 'Status',
        ];
    }

    public function map($lecturer): array
    {
        return [
            $lecturer->nidn ?? '',
            $lecturer->nuptk ?? '',
            $lecturer->nip ?? '',
            $lecturer->degree_front ?? '',
            $lecturer->full_name,
            $lecturer->degree_back ?? '',
            $lecturer->studyProgram?->code ?? '',
            $lecturer->studyProgram?->name ?? '',
            $lecturer->gender === 'L' ? 'Laki-laki' : ($lecturer->gender === 'P' ? 'Perempuan' : ''),
            $lecturer->birth_place ?? '',
            $lecturer->birth_date?->format('Y-m-d') ?? '',
            $lecturer->email ?? '',
            $lecturer->phone ?? '',
            $lecturer->academic_rank ?? '',
            $lecturer->employment_status ?? '',
            $lecturer->status ? 'Aktif' : 'Nonaktif',
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
