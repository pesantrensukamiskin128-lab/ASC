<?php

namespace App\Console\Commands;

use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class UpdateExitStatuses extends Command
{
    protected $signature = 'students:update-exit-status
        {--source= : File Excel data putus studi dan pengunduran diri}
        {--dry-run : Simulasi tanpa mengubah database}
        {--report= : Lokasi laporan CSV}';

    protected $description = 'Memperbarui status mahasiswa menjadi DO atau Mengundurkan Diri dari data Excel secara aman.';

    /** @var array<int, array<int, string>> */
    private array $reportRows = [];

    public function handle(): int
    {
        $source = trim((string) $this->option('source'));
        if ($source === '' || ! is_file($source)) {
            $this->error('--source wajib menunjuk ke satu file Excel yang tersedia.');

            return self::FAILURE;
        }
        if (! in_array(strtolower(pathinfo($source, PATHINFO_EXTENSION)), ['xlsx', 'xls'], true)) {
            $this->error('Sumber harus berupa file .xlsx atau .xls.');

            return self::FAILURE;
        }
        if (! Schema::hasTable('students') || ! Schema::hasTable('semesters') || ! Schema::hasTable('student_status_histories')) {
            $this->error("Tabel mahasiswa, semester, atau riwayat status belum tersedia. Jalankan 'php artisan migrate' terlebih dahulu.");

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        if ($dryRun) {
            $this->warn('MODE DRY-RUN: database tidak akan diubah.');
        }

        $this->line('Membaca '.basename($source).'...');
        $records = $this->readRecords($source);
        $students = Student::query()
            ->whereIn('nim', array_keys($records))
            ->get(['id', 'nim', 'name', 'status'])
            ->keyBy(fn (Student $student): string => trim((string) $student->nim));
        $semesters = $this->semesterMap();
        $terminalStatuses = ['Lulus', 'DO', 'Mengundurkan Diri'];
        $updates = [];
        $stats = [
            'baris_sumber' => array_sum(array_column($records, 'source_rows')),
            'nim_unik' => count($records),
            'akan_diubah' => 0,
            'sudah_sesuai' => 0,
            'tidak_ditemukan' => 0,
            'konflik_terminal' => 0,
            'konflik_riwayat' => 0,
        ];

        foreach ($records as $nim => $record) {
            $student = $students->get($nim);
            if (! $student) {
                $stats['tidak_ditemukan']++;
                $this->report('MISSING_STUDENT', $nim, $record['name'], $record['source'], 'NIM tidak ditemukan di ASC.');

                continue;
            }
            if ($this->normalizeName($student->name) !== $this->normalizeName($record['name'])) {
                $this->report('NAME_MISMATCH', $nim, $record['name'], $student->name, 'Nama Excel berbeda dengan nama ASC; pencocokan tetap berdasarkan NIM.');
            }
            if ($student->status === $record['target_status']) {
                $stats['sudah_sesuai']++;
                $this->report('ALREADY_MATCHED', $nim, $record['target_status'], $record['source'], 'Status mahasiswa sudah sesuai dengan data Excel.');

                continue;
            }
            if (in_array($student->status, $terminalStatuses, true)) {
                $stats['konflik_terminal']++;
                $this->report('TERMINAL_STATUS_CONFLICT', $nim, $record['target_status'], $student->status, 'Status terminal ASC berbeda dan tidak ditimpa.');

                continue;
            }

            $openHistory = DB::table('student_status_histories')
                ->where('student_id', $student->id)
                ->whereNull('end_date')
                ->orderByDesc('start_date')
                ->first(['id', 'start_date']);
            if ($openHistory && (string) $openHistory->start_date > $record['exit_date']) {
                $stats['konflik_riwayat']++;
                $this->report('HISTORY_DATE_CONFLICT', $nim, (string) $openHistory->start_date, $record['exit_date'], 'Riwayat terbuka dimulai setelah tanggal keluar; status tidak diubah.');

                continue;
            }

            $record['student_id'] = (int) $student->id;
            $record['previous_status'] = (string) $student->status;
            $record['semester_id'] = $semesters[$record['period']] ?? null;
            if ($record['semester_id'] === null) {
                $this->report('SEMESTER_NOT_MAPPED', $nim, $record['period'], $record['source'], 'Status tetap dapat diperbarui dengan semester kosong dan tanggal keluar sebagai tanggal efektif.');
            }
            $updates[] = $record;
            $stats['akan_diubah']++;
            $this->report('STATUS_UPDATE', $nim, $student->status, $record['target_status'], $dryRun ? 'Akan diubah saat eksekusi nyata.' : 'Status dan riwayat keluar diperbarui.');
        }

        if (! $dryRun) {
            DB::transaction(function () use ($updates): void {
                foreach ($updates as $record) {
                    DB::table('student_status_histories')
                        ->where('student_id', $record['student_id'])
                        ->whereNull('end_date')
                        ->whereDate('start_date', '<=', $record['exit_date'])
                        ->update(['end_date' => $record['exit_date'], 'updated_at' => now()]);

                    DB::table('student_status_histories')->insert([
                        'student_id' => $record['student_id'],
                        'semester_id' => $record['semester_id'],
                        'status' => $record['target_status'],
                        'start_date' => $record['exit_date'],
                        'end_date' => null,
                        'reason' => $record['exit_type'].' berdasarkan data Excel ('.$record['source'].')',
                        'decree_number' => null,
                        'created_by' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    DB::table('students')
                        ->where('id', $record['student_id'])
                        ->update(['status' => $record['target_status'], 'updated_at' => now()]);
                }
            });
        }

        $reportPath = $this->writeReport();
        $this->newLine();
        $this->info('Hasil Pembaruan Status Keluar Mahasiswa:');
        $this->table(
            ['Baris sumber', 'NIM unik', $dryRun ? 'Akan diubah' : 'Diubah', 'Sudah sesuai', 'Tidak ditemukan', 'Konflik terminal', 'Konflik riwayat'],
            [[
                $stats['baris_sumber'], $stats['nim_unik'], $stats['akan_diubah'],
                $stats['sudah_sesuai'], $stats['tidak_ditemukan'], $stats['konflik_terminal'], $stats['konflik_riwayat'],
            ]]
        );
        $this->line('Laporan lengkap: '.$reportPath);
        $dryRun
            ? $this->warn('DRY-RUN selesai. Periksa laporan sebelum menjalankan tanpa --dry-run.')
            : $this->info('Pembaruan status keluar mahasiswa selesai.');

        return self::SUCCESS;
    }

    /** @return array<string, array<string, mixed>> */
    private function readRecords(string $file): array
    {
        $records = [];
        $reader = IOFactory::createReaderForFile($file);
        $reader->setReadDataOnly(true);
        $workbook = $reader->load($file);

        foreach ($workbook->getWorksheetIterator() as $sheet) {
            $headers = [];
            foreach ($sheet->getRowIterator(1, 1)->current()->getCellIterator() as $cell) {
                $headers[$this->normalizeHeader((string) $cell->getValue())] = $cell->getColumn();
            }
            foreach (['nim', 'nama_mahasiswa', 'jenis_keluar', 'tanggal_keluar', 'periode_keluar'] as $required) {
                if (! isset($headers[$required])) {
                    $this->report('INVALID_SHEET', basename($file), $sheet->getTitle(), $required, 'Kolom wajib tidak ditemukan.');

                    continue 2;
                }
            }

            for ($row = 2; $row <= $sheet->getHighestDataRow(); $row++) {
                $nim = $this->nimValue($sheet->getCell($headers['nim'].$row)->getValue());
                if ($nim === '') {
                    continue;
                }
                if (! preg_match('/^\d{8,20}$/', $nim)) {
                    $this->report('INVALID_NIM', $nim, basename($file), (string) $row, 'Format NIM tidak valid.');

                    continue;
                }
                $exitType = trim((string) $sheet->getCell($headers['jenis_keluar'].$row)->getValue());
                $targetStatus = $this->targetStatus($exitType);
                if ($targetStatus === null) {
                    $this->report('UNMAPPED_EXIT_TYPE', $nim, $exitType, basename($file), 'Jenis keluar tidak dipetakan oleh command ini.');

                    continue;
                }
                $exitDate = $this->dateValue($sheet->getCell($headers['tanggal_keluar'].$row)->getValue());
                if ($exitDate === null) {
                    $this->report('INVALID_EXIT_DATE', $nim, basename($file), (string) $row, 'Tanggal keluar tidak valid.');

                    continue;
                }

                $record = [
                    'nim' => $nim,
                    'name' => trim((string) $sheet->getCell($headers['nama_mahasiswa'].$row)->getValue()),
                    'exit_type' => $exitType,
                    'target_status' => $targetStatus,
                    'exit_date' => $exitDate,
                    'period' => $this->normalizePeriod((string) $sheet->getCell($headers['periode_keluar'].$row)->getValue()),
                    'source' => isset($headers['file_sumber'])
                        ? trim((string) $sheet->getCell($headers['file_sumber'].$row)->getValue()) ?: basename($file)
                        : basename($file),
                    'source_rows' => 1,
                ];

                if (isset($records[$nim])) {
                    $records[$nim]['source_rows']++;
                    $this->report('DUPLICATE_NIM', $nim, $records[$nim]['source'], $record['source'], 'NIM muncul lebih dari satu kali; tanggal keluar terbaru digunakan.');
                    if ($record['exit_date'] > $records[$nim]['exit_date']) {
                        $record['source_rows'] = $records[$nim]['source_rows'];
                        $records[$nim] = $record;
                    }
                } else {
                    $records[$nim] = $record;
                }
            }
        }
        $workbook->disconnectWorksheets();

        return $records;
    }

    /** @return array<string, int> */
    private function semesterMap(): array
    {
        $map = [];
        foreach (DB::table('semesters')->get(['id', 'name', 'type']) as $semester) {
            if (preg_match('/(\d{4}\/\d{4})/', (string) $semester->name, $match)) {
                $key = $match[1].' '.ucfirst(mb_strtolower((string) $semester->type));
                $map[$key] = (int) $semester->id;
            }
        }

        return $map;
    }

    private function targetStatus(string $value): ?string
    {
        return match ($this->normalizeText($value)) {
            'putus studi', 'do' => 'DO',
            'mengajukan pengunduran diri', 'mengundurkan diri' => 'Mengundurkan Diri',
            default => null,
        };
    }

    private function normalizeHeader(string $value): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', '_', mb_strtolower(trim($value))) ?? '', '_');
    }

    private function normalizeText(string $value): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/', ' ', $value) ?? $value));
    }

    private function normalizePeriod(string $value): string
    {
        if (preg_match('/(\d{4}\/\d{4})\s*(Ganjil|Genap|Pendek)/i', trim($value), $match)) {
            return $match[1].' '.ucfirst(mb_strtolower($match[2]));
        }

        return trim($value);
    }

    private function normalizeName(string $value): string
    {
        return $this->normalizeText($value);
    }

    private function nimValue(mixed $value): string
    {
        if (is_float($value) || is_int($value)) {
            return number_format((float) $value, 0, '', '');
        }

        return trim((string) $value);
    }

    private function dateValue(mixed $value): ?string
    {
        try {
            if (is_numeric($value)) {
                return ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
            }

            $text = trim((string) $value);
            foreach (['Y-m-d', 'd-m-Y', 'd/m/Y'] as $format) {
                try {
                    return Carbon::createFromFormat($format, $text)->format('Y-m-d');
                } catch (\Throwable) {
                    // Coba format berikutnya.
                }
            }

            return Carbon::parse($text)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private function report(string $category, string $sourceId, string $sourceValue, string $targetValue, string $details): void
    {
        $this->reportRows[] = [$category, $sourceId, $sourceValue, $targetValue, $details];
    }

    private function writeReport(): string
    {
        $path = $this->option('report') ?: storage_path('app/migration/exit-status-report.csv');
        File::ensureDirectoryExists(dirname($path));
        $handle = fopen($path, 'wb');
        fputcsv($handle, ['category', 'nim_or_source', 'source_value', 'target_value', 'details']);
        foreach ($this->reportRows as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);

        return $path;
    }
}
