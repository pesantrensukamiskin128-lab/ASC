<?php

namespace App\Exports;

use App\Models\AcademicCalendar;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class AcademicCalendarExport implements FromQuery, WithHeadings, WithMapping, WithStyles, ShouldAutoSize, WithTitle
{
    public function __construct(private ?int $academicYearId = null) {}

    public function title(): string { return 'Kalender Akademik'; }

    public function query()
    {
        return AcademicCalendar::with('academicYear')
            ->when($this->academicYearId, fn($q) => $q->where('academic_year_id', $this->academicYearId))
            ->orderBy('start_date');
    }

    public function headings(): array
    {
        return ['No', 'Tahun Akademik', 'Kegiatan', 'Deskripsi', 'Tanggal Mulai', 'Tanggal Selesai', 'Kategori'];
    }

    public function map($item): array
    {
        static $no = 0;
        $no++;
        return [
            $no,
            $item->academicYear?->name ?? '-',
            $item->title,
            $item->description ?? '-',
            $item->start_date?->format('d/m/Y') ?? '-',
            $item->end_date?->format('d/m/Y') ?? '-',
            $item->category,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2563EB']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
        ];
    }
}
