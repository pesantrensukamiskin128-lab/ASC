<?php

namespace App\Console\Commands;

use App\Models\Semester;
use App\Models\Student;
use App\Services\SqlDumpParser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class MigrateSiakadPhaseFive extends Command
{
    protected $signature = 'siakad:migrate-phase5
        {--source= : Path ke file SQL dump SIAKAD}
        {--dry-run : Simulasi tanpa mengubah database}
        {--table=all : Data: all, academic-history, grade-scales}
        {--report= : Path laporan CSV fase kelima}';

    protected $description = 'Migrasi fase 5 SIAKAD: riwayat akademik mahasiswa, cuti, dan skala nilai.';

    private bool $dryRun = false;

    private string $sqlPath = '';

    /** @var array<string, array{total:int,inserted:int,existing:int,skipped:int}> */
    private array $stats = [];

    /** @var array<int, array<int, string|int|float|null>> */
    private array $reportRows = [];

    /** @var array<string, int> */
    private array $studentIds = [];

    /** @var array<string, object> */
    private array $semesters = [];

    /** @var array<string, array<string, mixed>> */
    private array $academicPlans = [];

    /** @var array<string, array<string, mixed>> */
    private array $leavePlans = [];

    public function __construct(private readonly SqlDumpParser $parser)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->resetState();
        $this->dryRun = (bool) $this->option('dry-run');
        $this->sqlPath = $this->option('source') ?? base_path('../_referensi/siakadstai_siakad.sql');
        $table = (string) $this->option('table');

        if (! in_array($table, ['all', 'academic-history', 'grade-scales'], true)) {
            $this->error("Nilai --table tidak valid: {$table}");

            return self::FAILURE;
        }
        if (! file_exists($this->sqlPath)) {
            $this->error("File tidak ditemukan: {$this->sqlPath}");

            return self::FAILURE;
        }
        if (in_array($table, ['all', 'academic-history'], true)
            && ! Schema::hasTable('student_semester_summaries')) {
            $this->error("Tabel student_semester_summaries belum tersedia. Jalankan 'php artisan migrate' terlebih dahulu.");

            return self::FAILURE;
        }
        if ($this->dryRun) {
            $this->warn('MODE DRY-RUN: database tidak akan diubah.');
        }

        $this->info("Membaca SQL dump: {$this->sqlPath}");
        $this->info('Ukuran file: '.round(filesize($this->sqlPath) / 1048576, 1).' MB');
        $this->loadReferences();

        DB::transaction(function () use ($table): void {
            if (in_array($table, ['all', 'academic-history'], true)) {
                $this->prepareAcademicHistory();
                $this->migrateAcademicHistory();
                $this->prepareLeaves();
                $this->migrateLeaves();
            }
            if (in_array($table, ['all', 'grade-scales'], true)) {
                $this->migrateGradeScales();
            }
        });

        $reportPath = $this->writeReport();
        $this->displaySummary($reportPath);

        return self::SUCCESS;
    }

    private function resetState(): void
    {
        $this->stats = [];
        $this->reportRows = [];
        $this->studentIds = [];
        $this->semesters = [];
        $this->academicPlans = [];
        $this->leavePlans = [];
    }

    private function loadReferences(): void
    {
        $this->studentIds = Student::query()->pluck('id', 'nim')
            ->map(fn ($id): int => (int) $id)->all();

        foreach (Semester::query()->get(['id', 'name', 'type', 'start_date', 'end_date']) as $semester) {
            if (preg_match('/(\d{4})\/(\d{4})/', (string) $semester->name, $match)) {
                $suffix = match (strtolower((string) $semester->type)) {
                    'ganjil' => '1', 'genap' => '2', 'pendek' => '3', default => null,
                };
                if ($suffix) {
                    $this->semesters[$match[1].$suffix] = $semester;
                }
            }
        }
    }

    private function prepareAcademicHistory(): void
    {
        $this->line("  Parsing tabel 'akm' secara streaming...");
        $sourceRows = 0;
        $groups = [];
        foreach ($this->parser->iterateTable($this->sqlPath, 'akm') as $row) {
            $sourceRows++;
            $sourceId = trim((string) ($row['akm_id'] ?? ''));
            $nim = trim((string) ($row['mhs_nim'] ?? ''));
            $semesterCode = trim((string) ($row['sem_id'] ?? ''));
            $studentId = $this->studentIds[$nim] ?? null;
            $semester = $this->semesters[$semesterCode] ?? null;

            if (! $studentId) {
                $this->report('MISSING_STUDENT', 'akm', $sourceId, $nim, 'Mahasiswa tidak ditemukan di ASC.');

                continue;
            }
            if (! $semester) {
                $this->report('MISSING_SEMESTER', 'akm', $sourceId, $semesterCode, 'Semester tidak ditemukan di ASC.');

                continue;
            }

            $key = $studentId.'|'.$semester->id;
            $prepared = [
                'source_id' => $sourceId,
                'source_ids' => [$sourceId],
                'student_id' => $studentId,
                'semester_id' => (int) $semester->id,
                'status' => $this->status((string) ($row['id_stat_mhs'] ?? ''), $sourceId),
                'start_date' => $this->date($semester->start_date) ?? now()->toDateString(),
                'end_date' => $this->date($semester->end_date),
                'semester_gpa' => $this->decimal($row['ip'] ?? null, 0, 4, 'INVALID_GPA', $sourceId),
                'cumulative_gpa' => $this->decimal($row['ipk'] ?? null, 0, 4, 'INVALID_GPA', $sourceId),
                'credit_limit' => $this->integer($row['jatah_sks'] ?? null, 'INVALID_CREDITS', $sourceId),
                'credits_taken' => $this->integer($row['sks_diambil'] ?? null, 'INVALID_CREDITS', $sourceId),
                'required_credits' => $this->integer($row['sks_wajib'] ?? null, 'INVALID_CREDITS', $sourceId),
                'elective_credits' => $this->integer($row['sks_pilihan'] ?? null, 'INVALID_CREDITS', $sourceId),
                'total_credits' => $this->integer($row['total_sks'] ?? null, 'INVALID_CREDITS', $sourceId),
                '_rank' => $this->academicRank($row),
            ];

            if (! isset($groups[$key])) {
                $groups[$key] = $prepared;

                continue;
            }
            $groups[$key]['source_ids'][] = $sourceId;
            if ($prepared['_rank'] > $groups[$key]['_rank']) {
                $sourceIds = $groups[$key]['source_ids'];
                $groups[$key] = $prepared;
                $groups[$key]['source_ids'] = $sourceIds;
            }
        }

        foreach ($groups as $key => $plan) {
            foreach ($plan['source_ids'] as $duplicateId) {
                if ($duplicateId !== $plan['source_id']) {
                    $this->report('DUPLICATE_AKM_MERGED', 'akm', $duplicateId, $plan['source_id'], 'Mahasiswa dan semester sama; baris terlengkap dipakai.');
                }
            }
            unset($plan['_rank']);
            $this->academicPlans[$key] = $plan;
        }
        $this->line("  Ditemukan {$sourceRows} baris; ".count($this->academicPlans).' entitas unik yang dapat dipetakan.');
    }

    private function migrateAcademicHistory(): void
    {
        $existingSummaries = DB::table('student_semester_summaries')->get(['id', 'student_id', 'semester_id'])
            ->keyBy(fn ($row): string => $row->student_id.'|'.$row->semester_id);
        $existingHistories = DB::table('student_status_histories')->get(['id', 'student_id', 'semester_id'])
            ->keyBy(fn ($row): string => $row->student_id.'|'.$row->semester_id);
        $summaryStats = $this->blankStats(count($this->academicPlans));
        $historyStats = $this->blankStats(count($this->academicPlans));
        $summaries = [];
        $histories = [];
        $now = now();

        foreach ($this->academicPlans as $key => $plan) {
            if ($existingSummaries->has($key)) {
                $summaryStats['existing']++;
            } else {
                $summaryStats['inserted']++;
                $summaries[] = array_intersect_key($plan, array_flip([
                    'student_id', 'semester_id', 'status', 'semester_gpa', 'cumulative_gpa',
                    'credit_limit', 'credits_taken', 'required_credits', 'elective_credits', 'total_credits',
                ])) + ['created_at' => $now, 'updated_at' => $now];
            }
            if ($existingHistories->has($key)) {
                $historyStats['existing']++;
            } else {
                $historyStats['inserted']++;
                $histories[] = [
                    'student_id' => $plan['student_id'], 'semester_id' => $plan['semester_id'],
                    'status' => $plan['status'], 'start_date' => $plan['start_date'],
                    'end_date' => $plan['end_date'], 'reason' => 'Migrasi aktivitas kuliah SIAKAD lama',
                    'decree_number' => null, 'created_by' => null, 'created_at' => $now, 'updated_at' => $now,
                ];
            }
        }

        if (! $this->dryRun) {
            $this->insertChunks('student_semester_summaries', $summaries);
            $this->insertChunks('student_status_histories', $histories);
            $this->saveAcademicMaps();
        }
        $this->stats['Ringkasan Akademik'] = $summaryStats;
        $this->stats['Riwayat Status'] = $historyStats;
    }

    private function prepareLeaves(): void
    {
        $this->line("  Parsing tabel 'cuti_mahasiswa' secara streaming...");
        $rows = 0;
        foreach ($this->parser->iterateTable($this->sqlPath, 'cuti_mahasiswa') as $row) {
            $rows++;
            $sourceId = trim((string) ($row['id_cuti'] ?? ''));
            $nim = trim((string) ($row['nim'] ?? ''));
            $studentId = $this->studentIds[$nim] ?? null;
            if (! $studentId) {
                $this->report('MISSING_STUDENT', 'academic_leave', $sourceId, $nim, 'Mahasiswa cuti tidak ditemukan di ASC.');

                continue;
            }
            $startDate = $this->validDate($row['created_at'] ?? null);
            $semester = $startDate ? $this->semesterAt($startDate) : null;
            if (! $semester) {
                $this->report('MISSING_SEMESTER', 'academic_leave', $sourceId, (string) $startDate, 'Semester cuti tidak dapat ditentukan.');

                continue;
            }
            $key = $studentId.'|'.$semester->id.'|CUTI';
            if (isset($this->leavePlans[$key])) {
                $this->report('DUPLICATE_LEAVE_MERGED', 'academic_leave', $sourceId, $this->leavePlans[$key]['source_id'], 'Mahasiswa, semester, dan jenis cuti sama.');

                continue;
            }
            if (trim((string) ($row['file_sk'] ?? '')) !== '') {
                $this->report('LEGACY_DOCUMENT_NOT_COPIED', 'academic_leave', $sourceId, (string) $row['file_sk'], 'Referensi file lama tidak disalin karena berkas fisik tidak tersedia.');
            }
            $this->leavePlans[$key] = [
                'source_id' => $sourceId, 'student_id' => $studentId, 'semester_id' => (int) $semester->id,
                'type' => 'CUTI', 'reason' => trim((string) ($row['keterangan'] ?? '')) ?: 'Migrasi cuti SIAKAD lama',
                'document_path' => null, 'status' => 'SELESAI', 'start_date' => $startDate,
                'end_date' => $this->validDate($row['tgl_berakhir'] ?? null) ?? $this->date($semester->end_date),
                'leave_semester_count' => 1, 'submitted_at' => $this->dateTime($row['created_at'] ?? null),
                'activated_at' => $this->dateTime($row['created_at'] ?? null), 'completed_at' => $this->dateTime($semester->end_date),
                'admin_note' => 'Migrasi SIAKAD fase 5; status historis ditandai selesai.',
            ];
        }
        $this->line("  Ditemukan {$rows} baris cuti; ".count($this->leavePlans).' dapat dipetakan.');
    }

    private function migrateLeaves(): void
    {
        $existing = DB::table('academic_leaves')->get(['id', 'student_id', 'semester_id', 'type'])
            ->keyBy(fn ($row): string => $row->student_id.'|'.$row->semester_id.'|'.$row->type);
        $stats = $this->blankStats(count($this->leavePlans));
        $rows = [];
        $now = now();
        foreach ($this->leavePlans as $key => $plan) {
            if ($existing->has($key)) {
                $stats['existing']++;

                continue;
            }
            $stats['inserted']++;
            $row = $plan;
            unset($row['source_id']);
            $rows[] = $row + ['created_at' => $now, 'updated_at' => $now];
        }
        if (! $this->dryRun) {
            $this->insertChunks('academic_leaves', $rows);
            $this->saveLeaveMaps();
        }
        $this->stats['Cuti Akademik'] = $stats;
    }

    private function migrateGradeScales(): void
    {
        $this->line("  Parsing tabel 'skala_nilai' secara streaming...");
        $byProgram = [];
        $sourceRows = 0;
        foreach ($this->parser->iterateTable($this->sqlPath, 'skala_nilai') as $row) {
            $sourceRows++;
            $sourceId = trim((string) ($row['id'] ?? ''));
            $program = trim((string) ($row['kode_jurusan'] ?? '')) ?: 'GLOBAL';
            $detail = $this->gradeDetail($row, $sourceId);
            if ($detail) {
                $byProgram[$program][] = ['source_id' => $sourceId, 'row' => $detail, 'source' => $row];
            }
        }

        $groups = [];
        foreach ($byProgram as $program => $details) {
            usort($details, fn ($a, $b): int => $b['row']['min_score'] <=> $a['row']['min_score']);
            $fingerprint = $this->gradeFingerprint(array_column($details, 'row'));
            $groups[$fingerprint]['programs'][] = $program;
            $groups[$fingerprint]['details'] ??= $details;
            $groups[$fingerprint]['program_details'][$program] = $details;
        }

        $schemaStats = $this->blankStats(count($byProgram));
        $detailStats = $this->blankStats($sourceRows);
        $existingSchemas = DB::table('grade_schemas')->get(['id', 'name', 'is_default']);
        $existingFingerprints = [];
        foreach ($existingSchemas as $schema) {
            $details = DB::table('grade_schema_details')->where('grade_schema_id', $schema->id)
                ->get(['min_score', 'max_score', 'letter', 'grade_point'])
                ->map(fn ($row): array => (array) $row)->all();
            $existingFingerprints[$this->gradeFingerprint($details)] = $schema;
        }

        foreach ($groups as $fingerprint => $group) {
            $existing = $existingFingerprints[$fingerprint] ?? null;
            $canonicalProgram = $group['programs'][0];
            if ($existing) {
                $schemaStats['existing'] += count($group['programs']);
                $detailStats['existing'] += array_sum(array_map(fn ($program): int => count($byProgram[$program]), $group['programs']));
                if (! $this->dryRun) {
                    $this->saveGradeMaps($group, (int) $existing->id);
                }

                continue;
            }

            $schemaStats['inserted']++;
            $schemaStats['skipped'] += count($group['programs']) - 1;
            $detailStats['inserted'] += count($group['details']);
            $detailStats['skipped'] += array_sum(array_map(fn ($program): int => count($byProgram[$program]), array_slice($group['programs'], 1)));
            foreach (array_slice($group['programs'], 1) as $program) {
                $this->report('DUPLICATE_GRADE_SCHEMA_MERGED', 'grade_schema', $program, $canonicalProgram, 'Rentang nilai identik; digabung menjadi satu skema ASC.');
            }
            if ($this->dryRun) {
                continue;
            }

            $first = $group['details'][0]['source'];
            $start = trim((string) ($first['tgl_mulai_efektif'] ?? ''));
            $end = trim((string) ($first['tgl_akhir_efektif'] ?? ''));
            $baseName = 'Skala Nilai SIAKAD'.($start || $end ? " {$start} s.d. {$end}" : '');
            $name = $baseName;
            if (DB::table('grade_schemas')->where('name', $name)->exists()) {
                $name .= ' '.substr($fingerprint, 0, 8);
            }
            $schemaId = DB::table('grade_schemas')->insertGetId([
                'name' => $name,
                'is_default' => ! DB::table('grade_schemas')->where('is_default', true)->exists(),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach ($group['details'] as $order => &$detail) {
                $detail['target_id'] = DB::table('grade_schema_details')->insertGetId(
                    $detail['row'] + ['grade_schema_id' => $schemaId, 'order' => $order + 1, 'created_at' => now(), 'updated_at' => now()]
                );
            }
            unset($detail);
            $this->saveGradeMaps($group, $schemaId);
        }
        $this->line("  Ditemukan {$sourceRows} baris dalam ".count($byProgram).' konfigurasi program studi.');
        $this->stats['Skema Nilai'] = $schemaStats;
        $this->stats['Detail Skala Nilai'] = $detailStats;
    }

    /** @return array<string, mixed>|null */
    private function gradeDetail(array $row, string $sourceId): ?array
    {
        $letter = strtoupper(trim((string) ($row['nilai_huruf'] ?? '')));
        $point = $this->decimal($row['nilai_indeks'] ?? null, 0, 4, 'INVALID_GRADE_POINT', $sourceId);
        $min = $this->decimal($row['bobot_nilai_min'] ?? null, 0, 100, 'INVALID_GRADE_RANGE', $sourceId);
        $max = $this->decimal($row['bobot_nilai_maks'] ?? null, 0, 100, 'INVALID_GRADE_RANGE', $sourceId);
        if ($letter === '' || $point === null || $min === null || $max === null || $min > $max) {
            $this->report('INVALID_GRADE_SCALE', 'grade_scale', $sourceId, $letter, 'Baris skala nilai tidak lengkap atau rentangnya tidak valid.');

            return null;
        }

        return ['min_score' => $min, 'max_score' => $max, 'letter' => $letter, 'grade_point' => $point];
    }

    /** @param array<int, array<string, mixed>> $details */
    private function gradeFingerprint(array $details): string
    {
        $normalized = array_map(fn ($row): array => [
            number_format((float) $row['min_score'], 2, '.', ''),
            number_format((float) $row['max_score'], 2, '.', ''),
            strtoupper(trim((string) $row['letter'])),
            number_format((float) $row['grade_point'], 2, '.', ''),
        ], $details);
        usort($normalized, fn ($a, $b): int => $b[0] <=> $a[0]);

        return hash('sha256', json_encode($normalized));
    }

    private function saveAcademicMaps(): void
    {
        $summaries = DB::table('student_semester_summaries')->get(['id', 'student_id', 'semester_id'])
            ->keyBy(fn ($row): string => $row->student_id.'|'.$row->semester_id);
        $histories = DB::table('student_status_histories')->get(['id', 'student_id', 'semester_id'])
            ->keyBy(fn ($row): string => $row->student_id.'|'.$row->semester_id);
        $maps = [];
        foreach ($this->academicPlans as $key => $plan) {
            foreach ($plan['source_ids'] as $sourceId) {
                if ($summary = $summaries->get($key)) {
                    $maps[] = $this->mapRow('student_semester_summary', $sourceId, 'student_semester_summaries', $summary->id);
                }
                if ($history = $histories->get($key)) {
                    $maps[] = $this->mapRow('student_status_history', $sourceId, 'student_status_histories', $history->id);
                }
            }
        }
        $this->upsertMaps($maps);
    }

    private function saveLeaveMaps(): void
    {
        $leaves = DB::table('academic_leaves')->get(['id', 'student_id', 'semester_id', 'type'])
            ->keyBy(fn ($row): string => $row->student_id.'|'.$row->semester_id.'|'.$row->type);
        $maps = [];
        foreach ($this->leavePlans as $key => $plan) {
            if ($leave = $leaves->get($key)) {
                $maps[] = $this->mapRow('academic_leave', $plan['source_id'], 'academic_leaves', $leave->id);
            }
        }
        $this->upsertMaps($maps);
    }

    /** @param array<string, mixed> $group */
    private function saveGradeMaps(array $group, int $schemaId): void
    {
        $targetDetails = DB::table('grade_schema_details')->where('grade_schema_id', $schemaId)
            ->get(['id', 'min_score', 'max_score', 'letter', 'grade_point']);
        $maps = [];
        foreach ($group['programs'] as $program) {
            foreach ($group['program_details'][$program] as $sourceDetail) {
                $row = $sourceDetail['row'];
                $target = $targetDetails->first(fn ($detail): bool => strtoupper((string) $detail->letter) === $row['letter']
                    && abs((float) $detail->min_score - $row['min_score']) < 0.001
                    && abs((float) $detail->max_score - $row['max_score']) < 0.001
                    && abs((float) $detail->grade_point - $row['grade_point']) < 0.001
                );
                if ($target) {
                    $maps[] = $this->mapRow(
                        'grade_schema_detail', $sourceDetail['source_id'],
                        'grade_schema_details', $target->id, ['program' => $program]
                    );
                }
            }
        }
        $this->upsertMaps($maps);
    }

    private function semesterAt(string $date): ?object
    {
        foreach ($this->semesters as $semester) {
            $start = $this->date($semester->start_date);
            $end = $this->date($semester->end_date);
            if ($start && $end && $date >= $start && $date <= $end) {
                return $semester;
            }
        }

        return null;
    }

    private function status(string $code, string $sourceId): string
    {
        $status = match (strtoupper(trim($code))) {
            'A' => 'AKTIF', 'C' => 'CUTI', 'D' => 'DO', 'K' => 'KELUAR',
            'L' => 'LULUS', 'N' => 'NONAKTIF', 'G' => 'DOUBLE DEGREE', default => null,
        };
        if ($status === null) {
            $this->report('UNKNOWN_STUDENT_STATUS', 'akm', $sourceId, $code, 'Status disimpan sebagai UNKNOWN.');
        }

        return $status ?? 'UNKNOWN';
    }

    /** @return array<int, int> */
    private function academicRank(array $row): array
    {
        $filled = 0;
        foreach (['ip', 'ipk', 'jatah_sks', 'sks_diambil', 'sks_wajib', 'sks_pilihan', 'total_sks'] as $field) {
            $filled += trim((string) ($row[$field] ?? '')) !== '' ? 1 : 0;
        }

        return [$filled, (int) ($row['akm_id'] ?? 0)];
    }

    private function decimal(mixed $value, float $min, float $max, string $category, string $sourceId): ?float
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }
        if (! is_numeric($raw) || (float) $raw < $min || (float) $raw > $max) {
            $this->report($category, 'academic_metric', $sourceId, $raw, "Nilai harus antara {$min} dan {$max}; dikosongkan.");

            return null;
        }

        return (float) $raw;
    }

    private function integer(mixed $value, string $category, string $sourceId): ?int
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }
        if (! is_numeric($raw) || (int) $raw < 0 || (int) $raw > 999) {
            $this->report($category, 'academic_metric', $sourceId, $raw, 'Nilai SKS tidak valid; dikosongkan.');

            return null;
        }

        return (int) $raw;
    }

    private function validDate(mixed $value): ?string
    {
        $raw = substr(trim((string) $value), 0, 10);
        if ($raw === '' || $raw === '0000-00-00') {
            return null;
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $raw);
        $errors = \DateTimeImmutable::getLastErrors();

        return $date && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))
            && $date->format('Y-m-d') === $raw ? $raw : null;
    }

    private function date(mixed $value): ?string
    {
        return $value ? substr((string) $value, 0, 10) : null;
    }

    private function dateTime(mixed $value): ?string
    {
        $raw = trim((string) $value);
        $timestamp = $raw === '' ? false : strtotime($raw);

        return $timestamp === false ? null : date('Y-m-d H:i:s', $timestamp);
    }

    /** @return array{total:int,inserted:int,existing:int,skipped:int} */
    private function blankStats(int $total): array
    {
        return ['total' => $total, 'inserted' => 0, 'existing' => 0, 'skipped' => 0];
    }

    private function report(string $category, string $entity, string $sourceId, string $reference, string $details): void
    {
        $this->reportRows[] = [$category, $entity, $sourceId, $reference, $details];
    }

    /** @return array<string, mixed> */
    private function mapRow(string $entity, string $sourceId, string $table, int $targetId, array $metadata = []): array
    {
        return [
            'source_system' => 'siakad', 'entity' => $entity, 'source_id' => $sourceId,
            'target_table' => $table, 'target_id' => $targetId,
            'metadata' => $metadata ? json_encode($metadata) : null,
            'created_at' => now(), 'updated_at' => now(),
        ];
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function upsertMaps(array $rows): void
    {
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('legacy_migration_maps')->upsert(
                $chunk, ['source_system', 'entity', 'source_id'],
                ['target_table', 'target_id', 'metadata', 'updated_at']
            );
        }
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function insertChunks(string $table, array $rows): void
    {
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table($table)->insert($chunk);
        }
    }

    private function writeReport(): string
    {
        $path = $this->option('report') ?: storage_path('app/migration/phase5-migration-report.csv');
        File::ensureDirectoryExists(dirname($path));
        $handle = fopen($path, 'wb');
        fputcsv($handle, ['category', 'entity', 'source_id', 'source_reference', 'details']);
        foreach ($this->reportRows as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);

        return $path;
    }

    private function displaySummary(string $reportPath): void
    {
        $this->newLine();
        $this->info('Hasil Migrasi Fase 5:');
        $rows = [];
        foreach ($this->stats as $table => $stats) {
            $rows[] = [$table, $stats['total'], $stats['inserted'], $stats['existing'], $stats['skipped']];
        }
        $this->table(['Tabel', 'Diproses', 'Diinsert', 'Sudah Ada', 'Dilewati'], $rows);
        $this->line('Laporan lengkap: '.$reportPath);
        $this->dryRun
            ? $this->warn('DRY-RUN selesai. Jalankan tanpa --dry-run setelah laporan diperiksa.')
            : $this->info('Migrasi fase 5 selesai. Data ASC yang sudah ada tidak ditimpa.');
    }
}
