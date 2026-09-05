<?php

namespace App\Console\Commands;

use App\Models\Student;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\IOFactory;

class UpdateNonactiveStatuses extends Command
{
    protected $signature = 'students:update-nonactive-status
        {--source= : File Excel data aktivitas mahasiswa periode terbaru}
        {--dry-run : Simulasi tanpa mengubah database}
        {--report= : Lokasi laporan CSV}';

    protected $description = 'Memperbarui status mahasiswa menjadi Nonaktif dari data aktivitas semester terbaru secara aman.';

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
            'sudah_nonaktif' => 0,
            'tidak_ditemukan' => 0,
            'status_terminal' => 0,
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
            if ($student->status === 'Nonaktif') {
                $stats['sudah_nonaktif']++;
                $this->report('ALREADY_NONACTIVE', $nim, $record['period'], $record['source'], 'Status mahasiswa sudah Nonaktif.');

                continue;
            }
            if (in_array($student->status, $terminalStatuses, true)) {
                $stats['status_terminal']++;
                $this->report('TERMINAL_STATUS_SKIPPED', $nim, $student->status, $record['source'], 'Status terminal tidak ditimpa oleh data aktivitas kuliah.');

                continue;
            }

            $semester = $semesters[$record['period']] ?? null;
            if (! $semester) {
                $this->report('MISSING_SEMESTER', $nim, $record['period'], $record['source'], 'Semester sumber tidak ditemukan di ASC.');

                continue;
            }

            $openHistory = DB::table('student_status_histories')
                ->where('student_id', $student->id)
                ->whereNull('end_date')
                ->orderByDesc('start_date')
                ->first(['id', 'start_date']);
            if ($openHistory && (string) $openHistory->start_date > $semester['start_date']) {
                $stats['konflik_riwayat']++;
                $this->report('HISTORY_DATE_CONFLICT', $nim, (string) $openHistory->start_date, $semester['start_date'], 'Riwayat terbuka dimulai setelah semester sumber; status tidak diubah.');

                continue;
            }

            $record['student_id'] = (int) $student->id;
            $record['previous_status'] = (string) $student->status;
            $record['semester_id'] = $semester['id'];
            $record['effective_date'] = $semester['start_date'];
            $updates[] = $record;
            $stats['akan_diubah']++;
            $this->report('STATUS_UPDATE', $nim, $student->status, 'Nonaktif', $dryRun ? 'Akan diubah saat eksekusi nyata.' : 'Status dan riwayat Nonaktif diperbarui.');
        }

        if (! $dryRun) {
            DB::transaction(function () use ($updates): void {
                foreach ($updates as $record) {
                    DB::table('student_status_histories')
                        ->where('student_id', $record['student_id'])
                        ->whereNull('end_date')
                        ->whereDate('start_date', '<=', $record['effective_date'])
                        ->update(['end_date' => $record['effective_date'], 'updated_at' => now()]);

                    DB::table('student_status_histories')->insert([
                        'student_id' => $record['student_id'],
                        'semester_id' => $record['semester_id'],
                        'status' => 'Nonaktif',
                        'start_date' => $record['effective_date'],
                        'end_date' => null,
                        'reason' => 'Status Nonaktif berdasarkan data aktivitas mahasiswa '.$record['period'].' ('.$record['source'].')',
                        'decree_number' => null,
                        'created_by' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    DB::table('students')
                        ->where('id', $record['student_id'])
                        ->update(['status' => 'Nonaktif', 'updated_at' => now()]);
                }
            });
        }

        $reportPath = $this->writeReport();
        $this->newLine();
        $this->info('Hasil Pembaruan Status Mahasiswa Nonaktif:');
        $this->table(
            ['Baris sumber', 'NIM unik', $dryRun ? 'Akan diubah' : 'Diubah', 'Sudah Nonaktif', 'Tidak ditemukan', 'Status terminal', 'Konflik riwayat'],
            [[
                $stats['baris_sumber'], $stats['nim_unik'], $stats['akan_diubah'],
                $stats['sudah_nonaktif'], $stats['tidak_ditemukan'], $stats['status_terminal'], $stats['konflik_riwayat'],
            ]]
        );
        $this->line('Laporan lengkap: '.$reportPath);
        $dryRun
            ? $this->warn('DRY-RUN selesai. Periksa laporan sebelum menjalankan tanpa --dry-run.')
            : $this->info('Pembaruan status mahasiswa Nonaktif selesai.');

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
            foreach (['nim', 'nama', 'status_mahasiswa', 'periode'] as $required) {
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
                $sourceStatus = trim((string) $sheet->getCell($headers['status_mahasiswa'].$row)->getValue());
                if (! in_array($this->normalizeStatus($sourceStatus), ['nonaktif'], true)) {
                    $this->report('INVALID_STATUS', $nim, $sourceStatus, basename($file), 'Baris bukan berstatus Nonaktif.');

                    continue;
                }
                $period = $this->normalizePeriod((string) $sheet->getCell($headers['periode'].$row)->getValue());
                if ($period === '') {
                    $this->report('INVALID_PERIOD', $nim, basename($file), (string) $row, 'Periode tidak valid.');

                    continue;
                }

                $record = [
                    'nim' => $nim,
                    'name' => trim((string) $sheet->getCell($headers['nama'].$row)->getValue()),
                    'period' => $period,
                    'source' => basename($file),
                    'source_rows' => 1,
                ];
                if (isset($records[$nim])) {
                    $records[$nim]['source_rows']++;
                    $this->report('DUPLICATE_NIM', $nim, $records[$nim]['source'], $record['source'], 'NIM muncul lebih dari satu kali; satu pembaruan digunakan.');
                } else {
                    $records[$nim] = $record;
                }
            }
        }
        $workbook->disconnectWorksheets();

        return $records;
    }

    /** @return array<string, array{id: int, start_date: string}> */
    private function semesterMap(): array
    {
        $map = [];
        foreach (DB::table('semesters')->get(['id', 'name', 'type', 'start_date']) as $semester) {
            if (preg_match('/(\d{4}\/\d{4})/', (string) $semester->name, $match)) {
                $key = $match[1].' '.ucfirst(mb_strtolower((string) $semester->type));
                $map[$key] = ['id' => (int) $semester->id, 'start_date' => (string) $semester->start_date];
            }
        }

        return $map;
    }

    private function normalizeHeader(string $value): string
    {
        $normalized = trim(preg_replace('/[^a-z0-9]+/', '_', mb_strtolower(trim($value))) ?? '', '_');

        return $normalized === 'nama_mahasiswa' ? 'nama' : $normalized;
    }

    private function normalizeStatus(string $value): string
    {
        return preg_replace('/[^a-z]+/', '', mb_strtolower(trim($value))) ?? '';
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
        return mb_strtolower(trim(preg_replace('/\s+/', ' ', $value) ?? $value));
    }

    private function nimValue(mixed $value): string
    {
        if (is_float($value) || is_int($value)) {
            return number_format((float) $value, 0, '', '');
        }

        return trim((string) $value);
    }

    private function report(string $category, string $sourceId, string $sourceValue, string $targetValue, string $details): void
    {
        $this->reportRows[] = [$category, $sourceId, $sourceValue, $targetValue, $details];
    }

    private function writeReport(): string
    {
        $path = $this->option('report') ?: storage_path('app/migration/nonactive-status-report.csv');
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
