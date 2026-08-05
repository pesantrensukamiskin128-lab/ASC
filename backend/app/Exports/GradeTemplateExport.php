<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class GradeTemplateExport implements FromArray, WithHeadings, WithStyles, ShouldAutoSize, WithTitle
{
    public function title(): string
    {
        return 'Import Nilai';
    }

    public function headings(): array
    {
        return ['nim', 'kode_matakuliah', 'semester', 'nilai_huruf', 'nilai_angka', 'bobot'];
    }

    public function array(): array
    {
        // Contoh data
        return [
            ['2020110001', 'HES101', 'Ganjil 2020/2021', 'A', 85, 4.00],
            ['2020110001', 'HES102', 'Ganjil 2020/2021', 'B+', 78, 3.50],
            ['2020110001', 'HES201', 'Genap 2020/2021', 'A-', 82, 3.75],
            ['2021110015', 'HES101', 'Ganjil 2021/2022', 'B', 72, 3.00],
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        // Tambah note/keterangan
        $sheet->setCellValue('H1', 'KETERANGAN:');
        $sheet->setCellValue('H2', 'nim = NIM mahasiswa (harus sudah ada di sistem)');
        $sheet->setCellValue('H3', 'kode_matakuliah = Kode MK (harus sudah ada di master)');
        $sheet->setCellValue('H4', 'semester = Nama semester (exact match, cek di Master Data > Semester)');
        $sheet->setCellValue('H5', 'nilai_huruf = A, A-, B+, B, B-, C+, C, D, E');
        $sheet->setCellValue('H6', 'nilai_angka = Nilai numerik 0-100 (opsional)');
        $sheet->setCellValue('H7', 'bobot = Grade point: A=4.00, A-=3.75, B+=3.50, B=3.00, B-=2.75, C+=2.50, C=2.00, D=1.00, E=0 (opsional, auto-hitung dari huruf)');

        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '059669']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
        ];
    }
}
