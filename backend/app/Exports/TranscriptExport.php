<?php

namespace App\Exports;

use App\Models\Institution;
use App\Models\Student;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class TranscriptExport implements FromArray, ShouldAutoSize, WithEvents, WithTitle
{
    public function __construct(
        private readonly Student $student,
        private readonly Collection $grades,
        private readonly ?Institution $institution,
        private readonly float $recordedIpk,
    ) {}

    public function array(): array
    {
        $rows = [
            [$this->institution?->name ?? 'Perguruan Tinggi'],
            ['TRANSKRIP NILAI AKADEMIK'],
            [null],
            ['NIM', $this->student->nim, '', 'Program Studi', $this->student->studyProgram?->name ?? '-'],
            ['Nama', $this->student->name, '', 'Jenjang', $this->student->studyProgram?->level ?? '-'],
            ['Fakultas', $this->student->studyProgram?->faculty?->name ?? '-', '', 'Status', $this->student->status],
            [null],
            ['No', 'Semester', 'Kode MK', 'Mata Kuliah', 'SKS', 'Nilai Angka', 'Nilai Huruf', 'Bobot', 'Mutu'],
        ];

        foreach ($this->grades as $index => $grade) {
            $excelRow = 9 + $index;
            $rows[] = [
                $index + 1,
                $grade->semester?->name ?? '-',
                $grade->course?->code ?? '-',
                $grade->course?->name ?? '-',
                (int) ($grade->course?->credits ?? 0),
                $grade->final_score !== null ? (float) $grade->final_score : null,
                $grade->letter_grade,
                $grade->grade_point !== null ? (float) $grade->grade_point : null,
                "=E{$excelRow}*H{$excelRow}",
            ];
        }

        $firstDataRow = 9;
        $lastDataRow = 8 + $this->grades->count();
        $summaryRow = $lastDataRow + 2;
        $totalCredits = $this->grades->isNotEmpty() ? "=SUM(E{$firstDataRow}:E{$lastDataRow})" : 0;
        $totalQuality = $this->grades->isNotEmpty() ? "=SUM(I{$firstDataRow}:I{$lastDataRow})" : 0;
        $rows[] = [null];
        $rows[] = ['', '', '', 'Total SKS', $totalCredits];
        $rows[] = ['', '', '', 'Total Mutu', $totalQuality];
        $rows[] = ['', '', '', 'IPK berdasarkan nilai', "=IF(E{$summaryRow}>0,E".($summaryRow + 1)."/E{$summaryRow},0)"];
        $rows[] = ['', '', '', 'IPK tercatat di ASC', $this->recordedIpk];
        $rows[] = [null];
        $rows[] = ['Catatan: Mutu = SKS x Bobot. IPK berdasarkan nilai dihitung otomatis dari seluruh baris nilai yang diekspor.'];

        return $rows;
    }

    public function title(): string
    {
        return 'Transkrip Nilai';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $lastDataRow = 8 + $this->grades->count();
                $summaryRow = $lastDataRow + 2;
                $noteRow = $summaryRow + 5;

                $sheet->mergeCells('A1:I1');
                $sheet->mergeCells('A2:I2');
                $sheet->mergeCells("A{$noteRow}:I{$noteRow}");
                $sheet->freezePane('A9');
                $sheet->setAutoFilter('A8:I'.max(8, $lastDataRow));
                $sheet->setShowGridlines(false);

                $sheet->getStyle('A1:I'.$noteRow)->getFont()->setName('Arial')->setSize(10);
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14)->getColor()->setRGB('1F2937');
                $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12)->getColor()->setRGB('1D4ED8');
                $sheet->getStyle('A1:A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

                $sheet->getStyle('A4:A6')->getFont()->setBold(true);
                $sheet->getStyle('D4:D6')->getFont()->setBold(true);
                $sheet->getStyle('A8:I8')->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F4E78']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                    'borders' => ['bottom' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => 'FFFFFF']]],
                ]);
                $sheet->getRowDimension(8)->setRowHeight(25);

                if ($this->grades->isNotEmpty()) {
                    $sheet->getStyle("A9:I{$lastDataRow}")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_HAIR)->getColor()->setRGB('D1D5DB');
                    $sheet->getStyle("C9:C{$lastDataRow}")->getBorders()->getRight()->setBorderStyle(Border::BORDER_HAIR)->getColor()->setRGB('E5E7EB');
                    $sheet->getStyle("D9:D{$lastDataRow}")->getBorders()->getRight()->setBorderStyle(Border::BORDER_HAIR)->getColor()->setRGB('E5E7EB');
                    $sheet->getStyle("A9:A{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("E9:I{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    $sheet->getStyle("F9:F{$lastDataRow}")->getNumberFormat()->setFormatCode('0.00');
                    $sheet->getStyle("H9:I{$lastDataRow}")->getNumberFormat()->setFormatCode('0.00');
                    $sheet->getStyle("D9:D{$lastDataRow}")->getAlignment()->setWrapText(true);
                }

                $sheet->getStyle("D{$summaryRow}:E".($summaryRow + 3))->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EFF6FF']],
                    'borders' => ['top' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '93C5FD']]],
                ]);
                $sheet->getStyle("E{$summaryRow}:E".($summaryRow + 3))->getNumberFormat()->setFormatCode('0.00');
                $sheet->getStyle("A{$noteRow}")->getFont()->setItalic(true)->getColor()->setRGB('6B7280');
                $sheet->getStyle("A{$noteRow}")->getAlignment()->setWrapText(true);
                $sheet->getRowDimension($noteRow)->setRowHeight(30);

                foreach (['A' => 6, 'B' => 20, 'C' => 14, 'D' => 38, 'E' => 9, 'F' => 13, 'G' => 13, 'H' => 10, 'I' => 12] as $column => $width) {
                    $sheet->getColumnDimension($column)->setAutoSize(false)->setWidth($width);
                }
                $sheet->getPageSetup()->setOrientation('landscape')->setFitToWidth(1)->setFitToHeight(0);
                $sheet->getPageMargins()->setTop(0.4)->setRight(0.3)->setBottom(0.4)->setLeft(0.3);
                $sheet->getHeaderFooter()->setOddFooter('&LAl-Jawami Smart Campus&RHalaman &P dari &N');
                $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(8, 8);
            },
        ];
    }
}
