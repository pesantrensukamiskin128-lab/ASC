<?php

namespace App\Console\Commands;

use App\Models\ClassModel;
use App\Models\Course;
use App\Models\Krs;
use App\Models\KrsDetail;
use App\Models\LegacyMigrationMap;
use App\Models\Student;
use App\Models\StudentGrade;
use App\Services\SqlDumpParser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class MigrateSiakadPhaseFour extends Command
{
    protected $signature = 'siakad:migrate-phase4
        {--source= : Path ke file SQL dump SIAKAD}
        {--dry-run : Simulasi tanpa mengubah database}
        {--table=all : Data yang diproses: all, enrollments, grades}
        {--report= : Path laporan CSV fase keempat}';

    protected $description = 'Migrasi fase 4 SIAKAD: KRS, anggota kelas, dan nilai historis.';

    private bool $dryRun = false;

    private string $sqlPath = '';

    /** @var array<string, array{total:int, inserted:int, updated:int, skipped:int}> */
    private array $stats = [];

    /** @var array<int, array<int, string|int|float|null>> */
    private array $reportRows = [];

    /** @var array<string, array<string, mixed>> */
    private array $attemptPlans = [];

    /** @var array<string, array<string, mixed>> */
    private array $krsPlans = [];

    /** @var array<string, array<string, mixed>> */
    private array $componentScores = [];

    /** @var array<string, array<string, float>> */
    private array $componentWeights = [];

    /** @var array<string, string> */
    private array $componentNames = [];

    /** @var array<string, int> */
    private array $studentIds = [];

    /** @var array<int, int|null> */
    private array $studentAdvisors = [];

    /** @var array<string, int> */
    private array $classMap = [];

    /** @var array<int, array{id:int,course_id:int,semester_id:int}> */
    private array $targetClasses = [];

    /** @var array<int, int> */
    private array $courseCredits = [];

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

        if (! in_array($table, ['all', 'enrollments', 'grades'], true)) {
            $this->error("Nilai --table tidak valid: {$table}");

            return self::FAILURE;
        }
        if (! file_exists($this->sqlPath)) {
            $this->error("File tidak ditemukan: {$this->sqlPath}");

            return self::FAILURE;
        }
        if (LegacyMigrationMap::where(['source_system' => 'siakad', 'entity' => 'class'])->doesntExist()) {
            $this->error('Pemetaan kelas fase 3 belum tersedia. Jalankan fase 3 terlebih dahulu.');

            return self::FAILURE;
        }
        if ($this->dryRun) {
            $this->warn('MODE DRY-RUN: database tidak akan diubah.');
        }

        $this->info("Membaca SQL dump: {$this->sqlPath}");
        $this->info('Ukuran file: '.round(filesize($this->sqlPath) / 1048576, 1).' MB');
        $rows = $this->loadSourceRows();
        $this->loadTargetReferences();
        $this->indexComponents($rows);
        $this->buildPlans($rows['krs_detail']);

        DB::transaction(function () use ($table): void {
            if (in_array($table, ['all', 'enrollments'], true)) {
                $this->migrateEnrollments();
            }
            if (in_array($table, ['all', 'grades'], true)) {
                $this->migrateGrades();
            }
        });

        $reportPath = $this->writeReport();
        $this->displaySummary($reportPath, count($rows['krs_detail']));

        return self::SUCCESS;
    }

    private function resetState(): void
    {
        $this->stats = [];
        $this->reportRows = [];
        $this->attemptPlans = [];
        $this->krsPlans = [];
        $this->componentScores = [];
        $this->componentWeights = [];
        $this->componentNames = [];
        $this->studentIds = [];
        $this->studentAdvisors = [];
        $this->classMap = [];
        $this->targetClasses = [];
        $this->courseCredits = [];
    }

    /** @return array<string, array<int, array<string, mixed>>> */
    private function loadSourceRows(): array
    {
        $result = [];
        foreach (['krs_detail', 'krs_penilaian', 'kelas_penilaian', 'komponen_nilai'] as $table) {
            $this->line("  Parsing tabel '{$table}'...");
            $result[$table] = $this->parser->parseTable($this->sqlPath, $table);
            $this->line('  Ditemukan '.count($result[$table]).' baris.');
        }

        return $result;
    }

    private function loadTargetReferences(): void
    {
        $students = Student::query()->get(['id', 'nim', 'advisor_id']);
        foreach ($students as $student) {
            $this->studentIds[trim((string) $student->nim)] = (int) $student->id;
            $this->studentAdvisors[(int) $student->id] = $student->advisor_id ? (int) $student->advisor_id : null;
        }

        $this->classMap = LegacyMigrationMap::query()
            ->where(['source_system' => 'siakad', 'entity' => 'class'])
            ->pluck('target_id', 'source_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $classes = ClassModel::query()->whereIn('id', array_values($this->classMap))
            ->get(['id', 'course_id', 'semester_id']);
        foreach ($classes as $class) {
            $this->targetClasses[(int) $class->id] = [
                'id' => (int) $class->id,
                'course_id' => (int) $class->course_id,
                'semester_id' => (int) $class->semester_id,
            ];
        }
        $this->courseCredits = Course::query()->pluck('credits', 'id')
            ->map(fn ($credits): int => (int) $credits)
            ->all();
    }

    /** @param array<string, array<int, array<string, mixed>>> $rows */
    private function indexComponents(array $rows): void
    {
        foreach ($rows['komponen_nilai'] as $row) {
            $this->componentNames[trim((string) ($row['id'] ?? ''))] = trim((string) ($row['nama_komponen'] ?? ''));
        }
        foreach ($rows['kelas_penilaian'] as $row) {
            $classId = trim((string) ($row['id_kelas'] ?? ''));
            $componentId = trim((string) ($row['id_komponen'] ?? ''));
            if (is_numeric($row['nilai'] ?? null)) {
                $this->componentWeights[$classId][$componentId] = (float) $row['nilai'];
            }
        }
        foreach ($rows['krs_penilaian'] as $row) {
            $detailId = trim((string) ($row['id_krs_detail'] ?? ''));
            $componentId = trim((string) ($row['id_komponen'] ?? ''));
            $score = trim((string) ($row['nilai_angka'] ?? ''));
            if ($score !== '' && is_numeric($score) && (float) $score >= 0 && (float) $score <= 100) {
                $this->componentScores[$detailId][$componentId] = (float) $score;
            }
        }
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function buildPlans(array $rows): void
    {
        $groups = [];
        foreach ($rows as $row) {
            $sourceId = trim((string) ($row['id_krs_detail'] ?? ''));
            $nim = trim((string) ($row['nim'] ?? ''));
            $classSourceId = trim((string) ($row['id_kelas'] ?? ''));
            $studentId = $this->studentIds[$nim] ?? null;
            $classTargetId = $this->classMap[$classSourceId] ?? null;
            $class = $classTargetId ? ($this->targetClasses[$classTargetId] ?? null) : null;

            if (! $studentId || ! $class) {
                $category = ! $studentId ? 'MISSING_STUDENT' : 'MISSING_CLASS_MAP';
                $this->report($category, 'krs_detail', $sourceId, $nim, "Kelas sumber: {$classSourceId}");

                continue;
            }

            $key = $studentId.'|'.$class['semester_id'].'|'.$class['course_id'];
            $row['_source_id'] = $sourceId;
            $row['_nim'] = $nim;
            $row['_student_id'] = $studentId;
            $row['_class_source_id'] = $classSourceId;
            $row['_class_id'] = $class['id'];
            $row['_course_id'] = $class['course_id'];
            $row['_semester_id'] = $class['semester_id'];
            $groups[$key][] = $row;
        }

        foreach ($groups as $key => $group) {
            usort($group, fn (array $left, array $right): int => $this->rowRank($right) <=> $this->rowRank($left));
            $canonical = $group[0];
            $sourceIds = array_column($group, '_source_id');
            if (count($group) > 1) {
                foreach (array_slice($group, 1) as $duplicate) {
                    $this->report(
                        'DUPLICATE_ATTEMPT_MERGED',
                        'krs_detail',
                        $duplicate['_source_id'],
                        $canonical['_source_id'],
                        'Mahasiswa, semester, dan mata kuliah sama; baris terbaik/terbaru dipakai.'
                    );
                }
            }

            $krsKey = $canonical['_student_id'].'|'.$canonical['_semester_id'];
            $this->attemptPlans[$key] = [
                'key' => $key,
                'krs_key' => $krsKey,
                'source_ids' => $sourceIds,
                'row' => $canonical,
                'student_id' => $canonical['_student_id'],
                'class_id' => $canonical['_class_id'],
                'class_source_id' => $canonical['_class_source_id'],
                'course_id' => $canonical['_course_id'],
                'semester_id' => $canonical['_semester_id'],
                'status' => (int) ($canonical['batal'] ?? 0) !== 0 ? 'DIBATALKAN' : 'AKTIF',
            ];

            if (! isset($this->krsPlans[$krsKey])) {
                $this->krsPlans[$krsKey] = [
                    'key' => $krsKey,
                    'student_id' => $canonical['_student_id'],
                    'semester_id' => $canonical['_semester_id'],
                    'all_approved' => true,
                    'latest_date' => null,
                    'credits' => 0,
                ];
            }
            $approved = trim((string) ($canonical['disetujui'] ?? '')) === '1';
            $this->krsPlans[$krsKey]['all_approved'] = $this->krsPlans[$krsKey]['all_approved'] && $approved;
            $date = $this->validDateTime($canonical['tgl_perubahan'] ?? null);
            if ($date && (! $this->krsPlans[$krsKey]['latest_date'] || $date > $this->krsPlans[$krsKey]['latest_date'])) {
                $this->krsPlans[$krsKey]['latest_date'] = $date;
            }
            if ((int) ($canonical['batal'] ?? 0) === 0) {
                $this->krsPlans[$krsKey]['credits'] += $this->courseCredits[$canonical['_course_id']] ?? 0;
            }
        }
    }

    private function migrateEnrollments(): void
    {
        $now = now();
        $existingKrs = Krs::query()->get()->keyBy(fn (Krs $krs): string => $krs->student_id.'|'.$krs->semester_id);
        $stats = $this->blankStats(count($this->krsPlans));
        $insertRows = [];
        foreach ($this->krsPlans as $key => $plan) {
            if ($existingKrs->has($key)) {
                $stats['updated']++;
            } else {
                $stats['inserted']++;
                $insertRows[] = [
                    'student_id' => $plan['student_id'],
                    'semester_id' => $plan['semester_id'],
                    'advisor_id' => $this->studentAdvisors[$plan['student_id']] ?? null,
                    'total_credits' => $plan['credits'],
                    'status' => $plan['all_approved'] ? 'APPROVED' : 'SUBMITTED',
                    'submitted_at' => $plan['latest_date'],
                    'approved_at' => $plan['all_approved'] ? $plan['latest_date'] : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }
        if (! $this->dryRun) {
            $this->insertChunks('krs', $insertRows);
        }
        $this->stats['KRS'] = $stats;

        $krsRecords = Krs::query()->get()->keyBy(fn (Krs $krs): string => $krs->student_id.'|'.$krs->semester_id);
        $existingDetails = KrsDetail::query()->get()->keyBy(fn (KrsDetail $detail): string => $detail->krs_id.'|'.$detail->course_id);
        $detailStats = $this->blankStats(count($this->attemptPlans));
        $detailRows = [];
        $memberKeys = [];
        foreach ($this->attemptPlans as $plan) {
            $krs = $krsRecords->get($plan['krs_key']);
            if (! $krs && $this->dryRun) {
                $detailStats['inserted']++;
            } elseif (! $krs) {
                $detailStats['skipped']++;
            } else {
                $detailKey = $krs->id.'|'.$plan['course_id'];
                if ($existingDetails->has($detailKey)) {
                    $detailStats['updated']++;
                } else {
                    $detailStats['inserted']++;
                    $detailRows[] = [
                        'krs_id' => $krs->id,
                        'course_id' => $plan['course_id'],
                        'class_id' => $plan['class_id'],
                        'status' => $plan['status'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
            $memberKeys[$plan['class_id'].'|'.$plan['student_id']] = [
                'class_id' => $plan['class_id'], 'student_id' => $plan['student_id'],
            ];
        }
        if (! $this->dryRun) {
            $this->insertChunks('krs_details', $detailRows);
        }
        $this->stats['Detail KRS'] = $detailStats;

        $existingMembers = DB::table('class_members')->get(['class_id', 'student_id'])
            ->keyBy(fn ($row): string => $row->class_id.'|'.$row->student_id);
        $memberStats = $this->blankStats(count($memberKeys));
        $memberRows = [];
        foreach ($memberKeys as $key => $member) {
            if ($existingMembers->has($key)) {
                $memberStats['updated']++;
            } else {
                $memberStats['inserted']++;
                $memberRows[] = $member + ['created_at' => $now, 'updated_at' => $now];
            }
        }
        if (! $this->dryRun) {
            $this->insertChunks('class_members', $memberRows);
            $this->saveEnrollmentMaps();
        }
        $this->stats['Anggota Kelas'] = $memberStats;
    }

    private function migrateGrades(): void
    {
        $existing = StudentGrade::query()->get()->keyBy(
            fn (StudentGrade $grade): string => $grade->student_id.'|'.$grade->semester_id.'|'.$grade->course_id
        );
        $stats = $this->blankStats(count($this->attemptPlans));
        $rows = [];
        $gradePlans = [];
        $now = now();

        foreach ($this->attemptPlans as $key => $plan) {
            $grade = $this->gradeData($plan);
            if (! $grade) {
                $stats['skipped']++;

                continue;
            }
            $gradePlans[$key] = $grade;
            if ($existing->has($key)) {
                $stats['updated']++;

                continue;
            }
            $stats['inserted']++;
            $rows[] = [
                'student_id' => $plan['student_id'],
                'course_id' => $plan['course_id'],
                'class_id' => $plan['class_id'],
                'semester_id' => $plan['semester_id'],
                'components' => $grade['components'] ? json_encode($grade['components']) : null,
                'final_score' => $grade['final_score'],
                'letter_grade' => $grade['letter_grade'],
                'grade_point' => $grade['grade_point'],
                'graded_by' => null,
                'graded_at' => $grade['graded_at'],
                'note' => 'Migrasi SIAKAD fase 4',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        if (! $this->dryRun) {
            $this->insertChunks('student_grades', $rows);
            $this->saveGradeMaps($gradePlans);
        }
        $this->stats['Nilai'] = $stats;
    }

    /** @return array<string, mixed>|null */
    private function gradeData(array $plan): ?array
    {
        $row = $plan['row'];
        $sourceId = $row['_source_id'];
        $rawScore = trim((string) ($row['nilai_angka'] ?? ''));
        $rawLetter = strtoupper(trim((string) ($row['nilai_huruf'] ?? '')));
        $rawPoint = trim((string) ($row['bobot'] ?? ''));
        if ($rawScore === '' && $rawLetter === '' && $rawPoint === '') {
            $this->report('GRADE_EMPTY', 'student_grade', $sourceId, $row['_nim'], 'Tidak dimasukkan ke transkrip.');

            return null;
        }

        $score = is_numeric($rawScore) && (float) $rawScore >= 0 && (float) $rawScore <= 100 ? (float) $rawScore : null;
        if ($rawScore !== '' && $score === null) {
            $this->report('INVALID_FINAL_SCORE', 'student_grade', $sourceId, $rawScore, 'Nilai angka diabaikan; huruf/bobot tetap dipertahankan.');
        }
        $validLetters = ['A', 'A-', 'B+', 'B', 'B-', 'C+', 'C', 'D', 'E'];
        $letter = in_array($rawLetter, $validLetters, true) ? $rawLetter : null;
        if ($rawLetter !== '' && $letter === null) {
            $this->report('INVALID_LETTER', 'student_grade', $sourceId, $rawLetter, 'Nilai huruf diabaikan.');
        }
        $point = is_numeric($rawPoint) && (float) $rawPoint >= 0 && (float) $rawPoint <= 4 ? (float) $rawPoint : null;
        if ($rawPoint !== '' && $point === null) {
            $this->report('INVALID_GRADE_POINT', 'student_grade', $sourceId, $rawPoint, 'Bobot diabaikan.');
        }
        if ($letter === null && $point !== null) {
            $letter = match (number_format($point, 2, '.', '')) {
                '4.00' => 'A', '3.75' => 'A-', '3.50' => 'B+', '3.00' => 'B',
                '2.75' => 'B-', '2.50' => 'C+', '2.00' => 'C', '1.00' => 'D', '0.00' => 'E',
                default => null,
            };
        }
        if ($point === null && $letter !== null) {
            $point = ['A' => 4.0, 'A-' => 3.75, 'B+' => 3.5, 'B' => 3.0, 'B-' => 2.75, 'C+' => 2.5, 'C' => 2.0, 'D' => 1.0, 'E' => 0.0][$letter];
        }
        $expected = $letter ? ['A' => 4.0, 'A-' => 3.75, 'B+' => 3.5, 'B' => 3.0, 'B-' => 2.75, 'C+' => 2.5, 'C' => 2.0, 'D' => 1.0, 'E' => 0.0][$letter] : null;
        if ($expected !== null && $point !== null && abs($expected - $point) > 0.01) {
            $this->report('LETTER_POINT_CONFLICT', 'student_grade', $sourceId, "{$letter}|{$point}", 'Nilai sumber dipertahankan dan perlu diperiksa manual.');
        }

        return [
            'components' => $this->componentsFor($row),
            'final_score' => $score,
            'letter_grade' => $letter,
            'grade_point' => $point,
            'graded_at' => $this->validDateTime($row['tgl_perubahan'] ?? null),
        ];
    }

    /** @return array<int, array{name:string,weight:float,score:float}> */
    private function componentsFor(array $row): array
    {
        $sourceId = $row['_source_id'];
        $classSourceId = $row['_class_source_id'];
        $scores = $this->componentScores[$sourceId] ?? [];
        $direct = ['1' => 'presensi', '2' => 'mandiri', '3' => 'terstruktur', '4' => 'lain_lain', '5' => 'uts', '6' => 'uas'];
        foreach ($direct as $componentId => $column) {
            if (! isset($scores[$componentId]) && is_numeric($row[$column] ?? null)) {
                $value = (float) $row[$column];
                if ($value >= 0 && $value <= 100) {
                    $scores[$componentId] = $value;
                }
            }
        }
        $components = [];
        foreach ($scores as $componentId => $score) {
            $components[] = [
                'name' => $this->componentNames[$componentId] ?? "Komponen {$componentId}",
                'weight' => $this->componentWeights[$classSourceId][$componentId] ?? 0.0,
                'score' => $score,
            ];
        }

        return $components;
    }

    private function saveEnrollmentMaps(): void
    {
        $krsRecords = Krs::query()->get()->keyBy(fn (Krs $krs): string => $krs->student_id.'|'.$krs->semester_id);
        $details = KrsDetail::query()->get()->keyBy(fn (KrsDetail $detail): string => $detail->krs_id.'|'.$detail->course_id);
        $members = DB::table('class_members')->get()->keyBy(fn ($row): string => $row->class_id.'|'.$row->student_id);
        $maps = [];
        foreach ($this->krsPlans as $key => $plan) {
            if ($krs = $krsRecords->get($key)) {
                $maps[] = $this->mapRow('krs', $key, 'krs', $krs->id);
            }
        }
        foreach ($this->attemptPlans as $plan) {
            $krs = $krsRecords->get($plan['krs_key']);
            $detail = $krs ? $details->get($krs->id.'|'.$plan['course_id']) : null;
            $member = $members->get($plan['class_id'].'|'.$plan['student_id']);
            foreach ($plan['source_ids'] as $sourceId) {
                if ($detail) {
                    $maps[] = $this->mapRow('krs_detail', $sourceId, 'krs_details', $detail->id);
                }
                if ($member) {
                    $maps[] = $this->mapRow('class_member', $sourceId, 'class_members', $member->id);
                }
            }
        }
        $this->upsertMaps($maps);
    }

    /** @param array<string, array<string, mixed>> $gradePlans */
    private function saveGradeMaps(array $gradePlans): void
    {
        $grades = StudentGrade::query()->get()->keyBy(
            fn (StudentGrade $grade): string => $grade->student_id.'|'.$grade->semester_id.'|'.$grade->course_id
        );
        $maps = [];
        foreach ($gradePlans as $key => $gradePlan) {
            $grade = $grades->get($key);
            if (! $grade) {
                continue;
            }
            foreach ($this->attemptPlans[$key]['source_ids'] as $sourceId) {
                $maps[] = $this->mapRow('student_grade', $sourceId, 'student_grades', $grade->id);
            }
        }
        $this->upsertMaps($maps);
    }

    /** @return array<string, mixed> */
    private function mapRow(string $entity, string $sourceId, string $table, int $targetId): array
    {
        return [
            'source_system' => 'siakad', 'entity' => $entity, 'source_id' => $sourceId,
            'target_table' => $table, 'target_id' => $targetId, 'metadata' => null,
            'created_at' => now(), 'updated_at' => now(),
        ];
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function upsertMaps(array $rows): void
    {
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('legacy_migration_maps')->upsert(
                $chunk,
                ['source_system', 'entity', 'source_id'],
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

    /** @return array<int, int|string> */
    private function rowRank(array $row): array
    {
        $hasGrade = trim((string) ($row['nilai_huruf'] ?? '')) !== ''
            || trim((string) ($row['nilai_angka'] ?? '')) !== ''
            || trim((string) ($row['bobot'] ?? '')) !== '';

        return [
            (int) ($row['batal'] ?? 0) === 0 ? 1 : 0,
            $hasGrade ? 1 : 0,
            trim((string) ($row['disetujui'] ?? '')) === '1' ? 1 : 0,
            strtotime((string) ($row['tgl_perubahan'] ?? '')) ?: 0,
            (int) ($row['id_krs_detail'] ?? 0),
        ];
    }

    private function validDateTime(mixed $value): ?string
    {
        $value = trim((string) $value);
        $timestamp = $value === '' ? false : strtotime($value);

        return $timestamp === false ? null : date('Y-m-d H:i:s', $timestamp);
    }

    /** @return array{total:int,inserted:int,updated:int,skipped:int} */
    private function blankStats(int $total): array
    {
        return ['total' => $total, 'inserted' => 0, 'updated' => 0, 'skipped' => 0];
    }

    private function report(string $category, string $entity, string $sourceId, string $reference, string $details): void
    {
        $this->reportRows[] = [$category, $entity, $sourceId, $reference, $details];
    }

    private function writeReport(): string
    {
        $path = $this->option('report') ?: storage_path('app/migration/phase4-migration-report.csv');
        File::ensureDirectoryExists(dirname($path));
        $handle = fopen($path, 'wb');
        fputcsv($handle, ['category', 'entity', 'source_id', 'source_reference', 'details']);
        foreach ($this->reportRows as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);

        return $path;
    }

    private function displaySummary(string $reportPath, int $sourceRows): void
    {
        $this->newLine();
        $this->info("Baris KRS sumber: {$sourceRows}; entitas unik: ".count($this->attemptPlans).'.');
        $this->info('Hasil Migrasi Fase 4:');
        $rows = [];
        foreach ($this->stats as $table => $stats) {
            $rows[] = [$table, $stats['total'], $stats['inserted'], $stats['updated'], $stats['skipped']];
        }
        $this->table(['Tabel', 'Diproses', 'Diinsert', 'Sudah Ada', 'Dilewati'], $rows);
        $this->line('Laporan lengkap: '.$reportPath);
        $this->dryRun
            ? $this->warn('DRY-RUN selesai. Jalankan tanpa --dry-run setelah laporan diperiksa.')
            : $this->info('Migrasi fase 4 selesai. Data ASC yang sudah ada tidak ditimpa.');
    }
}
