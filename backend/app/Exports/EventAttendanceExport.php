<?php

namespace App\Exports;

use App\Models\Event;
use App\Models\EventAttendance;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class EventAttendanceExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize, WithTitle, WithEvents
{
    private Event $event;
    private int $rowCount = 0;

    public function __construct(Event $event)
    {
        $this->event = $event;
    }

    public function title(): string
    {
        return 'Daftar Hadir';
    }

    public function collection()
    {
        $data = EventAttendance::where('event_id', $this->event->id)
            ->with('user')
            ->orderBy('attended_at')
            ->get();
        $this->rowCount = $data->count();
        return $data;
    }

    public function headings(): array
    {
        return ['No', 'Nama', 'Instansi/Jabatan', 'No. HP', 'Metode', 'Waktu Hadir'];
    }

    public function map($att): array
    {
        static $no = 0;
        $no++;
        return [
            $no,
            $att->user?->name ?? $att->guest_name ?? '-',
            $att->guest_institution ?? $att->guest_position ?? '-',
            $att->guest_phone ?? '-',
            $att->method === 'APP' ? 'Aplikasi' : ($att->method === 'FORM' ? 'Form Publik' : $att->method),
            $att->attended_at?->format('d/m/Y H:i') ?? '-',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A8A']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // Insert header rows above the table
                $sheet->insertNewRowBefore(1, 4);
                $sheet->setCellValue('A1', $this->event->title);
                $sheet->setCellValue('A2', 'Tanggal: ' . $this->event->event_date?->format('d F Y') . ($this->event->start_time ? ' | Waktu: ' . $this->event->start_time . ' - ' . ($this->event->end_time ?? 'selesai') : ''));
                $sheet->setCellValue('A3', 'Tempat: ' . ($this->event->location ?? '-') . ' | Penyelenggara: ' . ($this->event->organizer ?? '-'));

                $sheet->mergeCells('A1:F1');
                $sheet->mergeCells('A2:F2');
                $sheet->mergeCells('A3:F3');

                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
                $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('A2:A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Footer
                $lastRow = $this->rowCount + 6;
                $sheet->setCellValue('A' . $lastRow, 'Total Hadir: ' . $this->rowCount . ' orang');
                $sheet->getStyle('A' . $lastRow)->getFont()->setBold(true);
            },
        ];
    }
}
