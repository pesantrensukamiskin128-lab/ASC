<?php

namespace App\Console\Commands;

use App\Models\Student;
use App\Services\AlumniSynchronizer;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class UpdateGraduateStatuses extends Command
{
    protected $signature = 'students:update-graduation-status
        {--source= : File Excel atau folder berisi data lulusan}
        {--dry-run : Simulasi tanpa mengubah database}
        {--force-conflicts : Ubah juga mahasiswa berstatus DO atau Mengundurkan Diri}
        {--report= : Lokasi laporan CSV}';

    protected $description = 'Memperbarui status mahasiswa menjadi Lulus dari data Excel secara aman.';

    /** @var array<int, array<int, string>> */
    private array $reportRows = [];

    public function handle(): int
    {
        $source = trim((string) $this->option('source'));
        if ($source === '') {
            $this->error('--source wajib diisi dengan file Excel atau folder data lulusan.');

            return self::FAILURE;
        }

        $files = $this->resolveFiles($source);
        if ($files === []) {
            $this->error('Tidak ditemukan file .xlsx atau .xls pada sumber tersebut.');

            return self::FAILURE;
        }
        if (! Schema::hasTable('students') || ! Schema::hasTable('student_status_histories')) {
            $this->error("Tabel mahasiswa atau riwayat status belum tersedia. Jalankan 'php artisan migrate' terlebih dahulu.");

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        if ($dryRun) {
            $this->warn('MODE DRY-RUN: database tidak akan diubah.');
        }

        $records = $this->readRecords($files);
        $students = Student::query()
            ->whereIn('nim', array_keys($records))
            ->get(['id', 'nim', 'name', 'status'])
            ->keyBy(fn (Student $student): string => trim((string) $student->nim));
        $semesters = $this->semesterMap();
        $forceConflicts = (bool) $this->option('force-conflicts');
        $terminalConflicts = ['DO', 'Mengundurkan Diri'];
        $updates = [];
        $alumniSyncRecords = [];
        $stats = [
            'baris_sumber' => array_sum(array_column($records, 'source_rows')),
            'nim_unik' => count($records),
            'akan_diubah' => 0,
            'sudah_lulus' => 0,
            'tidak_ditemukan' => 0,
            'konflik_status' => 0,
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
            if ($student->status === 'Lulus') {
                $stats['sudah_lulus']++;
                $alumniSyncRecords[$student->id] = $record + ['student_id' => (int) $student->id];
                $this->report('ALREADY_GRADUATED', $nim, $record['exit_date'], $record['source'], 'Status mahasiswa sudah Lulus.');

                continue;
            }
            if (in_array($student->status, $terminalConflicts, true) && ! $forceConflicts) {
                $stats['konflik_status']++;
                $this->report('STATUS_CONFLICT', $nim, $student->status, $record['source'], 'Status terminal tidak diubah tanpa --force-conflicts.');

                continue;
            }

            $record['student_id'] = (int) $student->id;
            $record['previous_status'] = (string) $student->status;
            $record['semester_id'] = $semesters[$record['period']] ?? null;
            $updates[] = $record;
            $alumniSyncRecords[$student->id] = $record;
            $stats['akan_diubah']++;
            $this->report('STATUS_UPDATE', $nim, $student->status, 'Lulus', $dryRun ? 'Akan diubah saat eksekusi nyata.' : 'Status dan riwayat kelulusan diperbarui.');
        }

        if (! $dryRun) {
            DB::transaction(function () use ($updates, $alumniSyncRecords): void {
                foreach ($updates as $record) {
                    DB::table('student_status_histories')
                        ->where('student_id', $record['student_id'])
                        ->whereNull('end_date')
                        ->update(['end_date' => $record['exit_date'], 'updated_at' => now()]);

                    DB::table('student_status_histories')->insert([
                        'student_id' => $record['student_id'],
                        'semester_id' => $record['semester_id'],
                        'status' => 'Lulus',
                        'start_date' => $record['exit_date'],
                        'end_date' => null,
                        'reason' => 'Kelulusan berdasarkan data Excel '.$record['period'].' ('.$record['source'].')',
                        'decree_number' => $record['decree_number'],
                        'created_by' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    DB::table('students')
                        ->where('id', $record['student_id'])
                        ->update(['status' => 'Lulus', 'updated_at' => now()]);
                }

                foreach ($alumniSyncRecords as $record) {
                    $student = Student::find($record['student_id']);
                    if ($student) {
                        app(AlumniSynchronizer::class)->sync($student, ['graduation_date' => $record['exit_date']]);
                    }
                }
            });
        }

        $reportPath = $this->writeReport();
        $this->newLine();
        $this->info('Hasil Pembaruan Status Lulusan:');
        $this->table(
            ['Baris sumber', 'NIM unik', $dryRun ? 'Akan diubah' : 'Diubah', 'Sudah Lulus', 'Tidak ditemukan', 'Konflik status'],
            [[
                $stats['baris_sumber'], $stats['nim_unik'], $stats['akan_diubah'],
                $stats['sudah_lulus'], $stats['tidak_ditemukan'], $stats['konflik_status'],
            ]]
        );
        $this->line('Laporan lengkap: '.$reportPath);
        $dryRun
            ? $this->warn('DRY-RUN selesai. Periksa laporan sebelum menjalankan tanpa --dry-run.')
            : $this->info('Pembaruan status lulusan selesai.');

        return self::SUCCESS;
    }

    /** @return array<int, string> */
    private function resolveFiles(string $source): array
    {
        if (is_file($source)) {
            return in_array(strtolower(pathinfo($source, PATHINFO_EXTENSION)), ['xlsx', 'xls'], true)
                ? [$source]
                : [];
        }
        if (! is_dir($source)) {
            return [];
        }

        return collect(File::files($source))
            ->filter(fn (\SplFileInfo $file): bool => in_array(strtolower($file->getExtension()), ['xlsx', 'xls'], true))
            ->sortBy(fn (\SplFileInfo $file): string => $file->getFilename())
            ->map(fn (\SplFileInfo $file): string => $file->getPathname())
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $files
     * @return array<string, array<string, mixed>>
     */
    private function readRecords(array $files): array
    {
        $records = [];
        foreach ($files as $file) {
            $this->line('  Membaca '.basename($file).'...');
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
                    if (mb_strtolower($exitType) !== 'lulus') {
                        $this->report('NON_GRADUATE_ROW', $nim, $exitType, basename($file), 'Baris bukan berstatus Lulus.');

                        continue;
                    }
                    $exitDate = $this->dateValue($sheet->getCell($headers['tanggal_keluar'].$row)->getValue());
                    if ($exitDate === null) {
                        $this->report('INVALID_EXIT_DATE', $nim, basename($file), (string) $row, 'Tanggal Keluar tidak valid.');

                        continue;
                    }

                    $record = [
                        'nim' => $nim,
                        'name' => trim((string) $sheet->getCell($headers['nama_mahasiswa'].$row)->getValue()),
                        'exit_date' => $exitDate,
                        'period' => $this->normalizePeriod((string) $sheet->getCell($headers['periode_keluar'].$row)->getValue()),
                        'decree_number' => isset($headers['nomor_sk'])
                            ? trim((string) $sheet->getCell($headers['nomor_sk'].$row)->getValue()) ?: null
                            : null,
                        'source' => basename($file),
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
            unset($workbook);
        }

        return $records;
    }

    /** @return array<string, int> */
    private function semesterMap(): array
    {
        $map = [];
        foreach (DB::table('semesters')->get(['id', 'name', 'type']) as $semester) {
            if (preg_match('/(\d{4}\/\d{4})/', (string) $semester->name, $match)) {
                $map[$match[1].' '.ucfirst(mb_strtolower((string) $semester->type))] = (int) $semester->id;
            }
        }

        return $map;
    }

    private function normalizeHeader(string $value): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', '_', mb_strtolower(trim($value))) ?? '', '_');
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

    private function dateValue(mixed $value): ?string
    {
        try {
            if (is_numeric($value)) {
                return ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
            }

            return Carbon::createFromFormat('d-m-Y', trim((string) $value))->format('Y-m-d');
        } catch (\Throwable) {
            try {
                return Carbon::parse((string) $value)->format('Y-m-d');
            } catch (\Throwable) {
                return null;
            }
        }
    }

    private function report(string $category, string $sourceId, string $sourceValue, string $targetValue, string $details): void
    {
        $this->reportRows[] = [$category, $sourceId, $sourceValue, $targetValue, $details];
    }

    private function writeReport(): string
    {
        $path = $this->option('report') ?: storage_path('app/migration/graduation-status-report.csv');
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
