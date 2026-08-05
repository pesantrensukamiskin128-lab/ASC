<?php

namespace App\Exports;

use App\Models\Staff;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class StaffExport implements FromQuery, WithHeadings, WithMapping, WithStyles, ShouldAutoSize, WithTitle
{
    public function __construct(private ?string $department = null) {}

    public function title(): string
    {
        return 'Tenaga Kependidikan';
    }

    public function query()
    {
        return Staff::when($this->department, fn($q) => $q->where('department', $this->department))
            ->orderBy('name');
    }

    public function headings(): array
    {
        return [
            'NIP', 'Nama Lengkap', 'Jenis Kelamin',
            'Tempat Lahir', 'Tanggal Lahir',
            'Email', 'No. HP', 'Jabatan', 'Unit/Bagian',
            'Status Kepegawaian', 'Status',
        ];
    }

    public function map($staff): array
    {
        return [
            $staff->nip ?? '',
            $staff->name,
            $staff->gender === 'L' ? 'Laki-laki' : ($staff->gender === 'P' ? 'Perempuan' : ''),
            $staff->birth_place ?? '',
            $staff->birth_date?->format('Y-m-d') ?? '',
            $staff->email ?? '',
            $staff->phone ?? '',
            $staff->position ?? '',
            $staff->department ?? '',
            $staff->employment_status ?? '',
            $staff->status ? 'Aktif' : 'Nonaktif',
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
