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

class TemplateExport implements FromArray, WithHeadings, WithStyles, ShouldAutoSize, WithTitle
{
    private string $type;

    private const HEADINGS = [
        'courses' => [
            'kode_mk', 'nama_mata_kuliah', 'kode_prodi',
            'sks', 'semester', 'jenis', 'status',
        ],
        'lecturers' => [
            'nidn', 'nuptk', 'nip', 'gelar_depan', 'nama_lengkap', 'gelar_belakang',
            'kode_prodi', 'jenis_kelamin', 'tempat_lahir', 'tanggal_lahir',
            'email', 'no_hp', 'jabatan_akademik', 'status_kepegawaian', 'status',
        ],
        'students' => [
            'nim', 'nama_lengkap', 'kode_prodi',
            'jenis_kelamin', 'tempat_lahir', 'tanggal_lahir',
            'email', 'no_hp', 'asal_sekolah',
            'tahun_masuk', 'status', 'dosen_wali',
        ],
        'staff' => [
            'nip', 'nama_lengkap', 'jenis_kelamin',
            'tempat_lahir', 'tanggal_lahir',
            'email', 'no_hp', 'jabatan', 'unit_bagian',
            'status_kepegawaian', 'status',
        ],
    ];

    /** Baris contoh agar user paham format yang diharapkan */
    private const EXAMPLES = [
        'courses'   => [['TI999', 'Contoh Mata Kuliah', 'TI', 3, 1, 'Wajib', 'Aktif']],
        'lecturers' => [['1234567890', '', '', 'Dr.', 'Nama Dosen Contoh', 'S.Kom., M.T.', 'TI', 'Laki-laki', 'Bandung', '1980-01-15', 'dosen@example.com', '08123456789', 'Asisten Ahli', 'Tetap', 'Aktif']],
        'students'  => [['2024001001', 'Nama Mahasiswa Contoh', 'TI', 'Laki-laki', 'Jakarta', '2002-05-20', 'mhs@example.com', '08198765432', 'SMA Negeri 1', 2024, 'Aktif', '']],
        'staff'     => [['198001012010011001', 'Nama Staff Contoh', 'Laki-laki', 'Surabaya', '1980-01-01', 'staff@example.com', '08111111111', 'Kepala Bagian', 'Akademik', 'Tetap', 'Aktif']],
    ];

    public function __construct(string $type)
    {
        $this->type = $type;
    }

    public function title(): string
    {
        return 'Template';
    }

    public function array(): array
    {
        return self::EXAMPLES[$this->type] ?? [];
    }

    public function headings(): array
    {
        return self::HEADINGS[$this->type] ?? [];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '16A34A']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
            2 => [
                'font' => ['italic' => true, 'color' => ['rgb' => '6B7280']],
            ],
        ];
    }
}
