<?php

namespace App\Exports;

use App\Models\Alumni;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class AlumniExport implements FromQuery, WithHeadings, WithMapping, WithStyles, ShouldAutoSize, WithTitle
{
    public function __construct(
        private ?int $studyProgramId = null,
        private ?int $graduationYear = null
    ) {}

    public function title(): string
    {
        return 'Alumni';
    }

    public function query()
    {
        return Alumni::with(['studyProgram', 'latestEmployment'])
            ->when($this->studyProgramId, fn($q) => $q->where('study_program_id', $this->studyProgramId))
            ->when($this->graduationYear, fn($q) => $q->where('graduation_year', $this->graduationYear))
            ->orderBy('graduation_year', 'desc')
            ->orderBy('nim');
    }

    public function headings(): array
    {
        return [
            'NIM', 'Nama Lengkap', 'Kode Prodi', 'Program Studi',
            'Tahun Masuk', 'Tahun Lulus', 'IPK', 'Predikat',
            'Email', 'No. HP', 'Alamat', 'Kota', 'Provinsi',
            'Judul Skripsi/Tesis',
            'Pekerjaan Terkini', 'Perusahaan', 'Industri',
        ];
    }

    public function map($alumni): array
    {
        return [
            $alumni->nim,
            $alumni->name,
            $alumni->studyProgram?->code ?? '',
            $alumni->studyProgram?->name ?? '',
            $alumni->entry_year ?? '',
            $alumni->graduation_year ?? '',
            $alumni->gpa ? number_format($alumni->gpa, 2) : '',
            $alumni->predicate ?? '',
            $alumni->email ?? '',
            $alumni->phone ?? '',
            $alumni->address ?? '',
            $alumni->city ?? '',
            $alumni->province ?? '',
            $alumni->thesis_title ?? '',
            $alumni->latestEmployment?->position ?? '',
            $alumni->latestEmployment?->company_name ?? '',
            $alumni->latestEmployment?->industry ?? '',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '7C3AED']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
        ];
    }
}
