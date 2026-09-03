<?php

namespace App\Console\Commands;

use App\Models\Course;
use App\Models\Curriculum;
use App\Models\CurriculumCourse;
use App\Models\LegacyMigrationMap;
use App\Models\StudyProgram;
use App\Services\SqlDumpParser;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class MigrateSiakadPhaseTwo extends Command
{
    protected $signature = 'siakad:migrate-phase2
        {--source= : Path ke file SQL dump SIAKAD}
        {--dry-run : Simulasi tanpa mengubah database}
        {--table=all : Data yang diproses: all, curriculums, courses}
        {--report= : Path laporan CSV duplikasi mata kuliah}';

    protected $description = 'Migrasi fase 2 SIAKAD: kurikulum, mata kuliah, dan relasi kurikulum-mata kuliah.';

    private bool $dryRun = false;

    private string $sqlPath = '';

    /** @var array<string, array{total:int, inserted:int, updated:int, skipped:int}> */
    private array $stats = [];

    /** @var array<int, array<string, mixed>> */
    private array $curriculumRows = [];

    /** @var array<string, array<string, mixed>> */
    private array $curriculumPlans = [];

    /** @var array<int, array<string, mixed>> */
    private array $courseRows = [];

    /** @var array<int, array<string, mixed>> */
    private array $coursePlans = [];

    /** @var array<int, array<int, string|int|bool|null>> */
    private array $reportRows = [];

    private int $duplicateCodeGroups = 0;

    private int $conflictingCodeGroups = 0;

    public function __construct(private readonly SqlDumpParser $parser)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->dryRun = (bool) $this->option('dry-run');
        $this->sqlPath = $this->option('source') ?? base_path('../_referensi/siakadstai_siakad.sql');
        $table = (string) $this->option('table');

        if (! in_array($table, ['all', 'curriculums', 'courses'], true)) {
            $this->error("Nilai --table tidak valid: {$table}");

            return self::FAILURE;
        }

        if (! file_exists($this->sqlPath)) {
            $this->error("File tidak ditemukan: {$this->sqlPath}");

            return self::FAILURE;
        }

        if ($table === 'courses' && Curriculum::query()->doesntExist()) {
            $this->error('Kurikulum ASC belum tersedia. Jalankan --table=curriculums terlebih dahulu atau gunakan --table=all.');

            return self::FAILURE;
        }

        if ($this->dryRun) {
            $this->warn('MODE DRY-RUN: database tidak akan diubah.');
        }

        $this->info("Membaca SQL dump: {$this->sqlPath}");
        $this->info('Ukuran file: '.round(filesize($this->sqlPath) / 1048576, 1).' MB');

        $this->loadSourceRows();
        $this->buildCurriculumPlans();

        if ($table === 'all' || $table === 'courses') {
            $this->buildCoursePlans();
        }

        DB::transaction(function () use ($table): void {
            if ($table === 'all' || $table === 'curriculums') {
                $this->migrateCurriculums();
            }

            if ($table === 'all' || $table === 'courses') {
                $this->migrateCoursesAndRelations();
            }
        });

        $reportPath = $this->writeDuplicateReport();
        $this->displaySummary($reportPath);

        return self::SUCCESS;
    }

    private function loadSourceRows(): void
    {
        $this->line("  Parsing tabel 'kurikulum'...");
        $this->curriculumRows = $this->parser->parseTable($this->sqlPath, 'kurikulum');
        $this->line('  Ditemukan '.count($this->curriculumRows).' kurikulum.');

        if ((string) $this->option('table') !== 'curriculums') {
            $this->line("  Parsing tabel 'matkul'...");
            $this->courseRows = $this->parser->parseTable($this->sqlPath, 'matkul');
            $this->line('  Ditemukan '.count($this->courseRows).' baris mata kuliah.');
        }
    }

    private function buildCurriculumPlans(): void
    {
        foreach ($this->curriculumRows as $row) {
            $sourceId = trim((string) ($row['kur_id'] ?? ''));
            $programCode = trim((string) ($row['kode_jur'] ?? ''));
            $program = $programCode === '' ? null : StudyProgram::where('code', $programCode)->first();
            $year = $this->curriculumYear($row);
            $name = trim((string) ($row['nama_kurikulum'] ?? ''));

            $this->curriculumPlans[$sourceId] = [
                'source_id' => $sourceId,
                'source' => $row,
                'program' => $program,
                'code' => "SIAKAD-KUR-{$sourceId}",
                'name' => $name !== '' ? $name : "Kurikulum SIAKAD {$sourceId}",
                'year' => $year,
                'valid' => $sourceId !== '' && $program !== null && $year !== null,
            ];
        }
    }

    private function buildCoursePlans(): void
    {
        /** @var array<string, array<int, array<string, mixed>>> $byCode */
        $byCode = [];

        foreach ($this->courseRows as $row) {
            $sourceId = trim((string) ($row['id_matkul'] ?? ''));
            $sourceCode = trim((string) ($row['kode_mk'] ?? ''));
            $curriculumId = trim((string) ($row['kur_id'] ?? ''));
            $curriculumPlan = $this->curriculumPlans[$curriculumId] ?? null;
            $program = $curriculumPlan['program'] ?? null;
            $name = trim((string) ($row['nama_mk'] ?? ''));

            $prepared = [
                'source_id' => $sourceId,
                'source_code' => $sourceCode,
                'curriculum_source_id' => $curriculumId,
                'program' => $program,
                'program_code' => $program?->code,
                'name' => $name,
                'identity' => ($program?->code ?? '').'|'.$this->normalizeText($name),
                'credits' => $this->courseCredits($row),
                'semester' => max(1, (int) ($row['semester'] ?? 1)),
                'type' => $this->courseType($row),
                'is_required' => $this->isRequiredCourse($row),
                'source' => $row,
                'valid' => $sourceId !== ''
                    && $sourceCode !== ''
                    && $name !== ''
                    && $curriculumPlan !== null
                    && $curriculumPlan['valid']
                    && $program !== null,
            ];

            if ($sourceCode === '') {
                $planKey = 'invalid|'.$sourceId;
                $this->coursePlans[$planKey] = [
                    'key' => $planKey,
                    'source_code' => '',
                    'planned_code' => '',
                    'canonical' => $prepared,
                    'rows' => [$prepared],
                    'conflict' => false,
                    'duplicate' => false,
                ];

                continue;
            }

            $byCode[$sourceCode][] = $prepared;
        }

        ksort($byCode, SORT_NATURAL | SORT_FLAG_CASE);

        foreach ($byCode as $sourceCode => $rows) {
            $clusters = [];
            foreach ($rows as $row) {
                $clusters[$row['identity']][] = $row;
            }

            if (count($rows) > 1) {
                $this->duplicateCodeGroups++;
            }
            if (count($clusters) > 1) {
                $this->conflictingCodeGroups++;
            }

            ksort($clusters, SORT_NATURAL | SORT_FLAG_CASE);
            $canonicalIdentity = $this->canonicalIdentity($sourceCode, $clusters);

            if ($canonicalIdentity === '') {
                $firstRow = $this->canonicalCourseRow(reset($clusters));
                $this->reportRows[] = [
                    'ASC_EXISTING_CONFLICT', $sourceCode, $firstRow['source_id'],
                    $firstRow['curriculum_source_id'], $firstRow['program_code'], $firstRow['name'],
                    $firstRow['credits'], $firstRow['semester'],
                    $this->aliasCourseCode($sourceCode, $firstRow['identity']),
                    'Kode asli sudah dipakai data ASC dengan nama/Prodi berbeda; semua data sumber memakai kode turunan.',
                ];
            }

            foreach ($clusters as $identity => $clusterRows) {
                $canonical = $this->canonicalCourseRow($clusterRows);
                $plannedCode = $identity === $canonicalIdentity
                    ? $sourceCode
                    : $this->aliasCourseCode($sourceCode, $identity);

                $planKey = $sourceCode.'|'.$identity;
                $plan = [
                    'key' => $planKey,
                    'source_code' => $sourceCode,
                    'planned_code' => $plannedCode,
                    'canonical' => $canonical,
                    'rows' => $clusterRows,
                    'conflict' => count($clusters) > 1,
                    'duplicate' => count($rows) > 1,
                ];
                $this->coursePlans[$planKey] = $plan;

                if (count($rows) > 1) {
                    foreach ($clusterRows as $row) {
                        $this->reportRows[] = [
                            count($clusters) > 1 ? 'CONFLICT_SPLIT' : 'DUPLICATE_MERGED',
                            $sourceCode,
                            $row['source_id'],
                            $row['curriculum_source_id'],
                            $row['program_code'],
                            $row['name'],
                            $row['credits'],
                            $row['semester'],
                            $plannedCode,
                            count($clusters) > 1
                                ? 'Kode turunan dibuat karena nama/Prodi berbeda.'
                                : 'Digabung sebagai satu mata kuliah dan tetap ditautkan ke kurikulum sumber.',
                        ];
                    }
                }
            }
        }
    }

    private function migrateCurriculums(): void
    {
        $stats = ['total' => count($this->curriculumPlans), 'inserted' => 0, 'updated' => 0, 'skipped' => 0];

        foreach ($this->curriculumPlans as $plan) {
            if (! $plan['valid']) {
                $stats['skipped']++;
                $this->warn("  Lewati kurikulum {$plan['source_id']}: ID, Prodi, atau tahun tidak valid.");

                continue;
            }

            $existing = $this->mappedModel('curriculum', $plan['source_id'], Curriculum::class)
                ?? Curriculum::where('code', $plan['code'])->first();
            $data = [
                'study_program_id' => $plan['program']->id,
                'code' => $plan['code'],
                'name' => $plan['name'],
                'year' => $plan['year'],
                'description' => $this->curriculumDescription($plan['source']),
                'status' => 'Nonaktif',
            ];

            if ($existing) {
                $stats['updated']++;
                if (! $this->dryRun) {
                    $fillable = $this->onlyEmptyFields($existing, $data);
                    if ($fillable !== []) {
                        $existing->update($fillable);
                    }
                }
            } else {
                $stats['inserted']++;
                if (! $this->dryRun) {
                    $existing = Curriculum::create($data);
                }
            }

            if (! $this->dryRun && $existing) {
                $this->saveMap('curriculum', $plan['source_id'], 'curriculums', $existing->id, [
                    'source_code' => $plan['source']['nama_kurikulum'] ?? null,
                ]);
            }
        }

        $this->stats['Kurikulum'] = $stats;
    }

    private function migrateCoursesAndRelations(): void
    {
        $courseStats = ['total' => count($this->coursePlans), 'inserted' => 0, 'updated' => 0, 'skipped' => 0];
        $relationPlans = [];
        $resolvedCourses = [];

        foreach ($this->coursePlans as $planKey => $plan) {
            $row = $plan['canonical'];
            if (! $row['valid']) {
                $courseStats['skipped']++;
                $this->warn("  Lewati mata kuliah {$row['source_id']}: kode, nama, kurikulum, atau Prodi tidak valid.");

                continue;
            }

            // Pemetaan legacy selalu dipercaya. Pengguna boleh memperbaiki nama,
            // Prodi, atau kode secara manual setelah migrasi tanpa membuat
            // duplikat baru saat perintah dijalankan ulang.
            $mappedCourse = $this->mappedCourseForPlan($plan);
            $existing = $mappedCourse
                ?? Course::where('code', $plan['planned_code'])->first();

            if (! $mappedCourse && $existing && ! $this->sameCourseIdentity($existing, $row)) {
                $newCode = $this->aliasCourseCode($plan['source_code'], $row['identity'].'|existing-conflict');
                $this->reportRows[] = [
                    'ASC_EXISTING_CONFLICT', $plan['source_code'], $row['source_id'],
                    $row['curriculum_source_id'], $row['program_code'], $row['name'],
                    $row['credits'], $row['semester'], $newCode,
                    "Kode {$plan['planned_code']} sudah dipakai data ASC yang berbeda; kode turunan digunakan.",
                ];
                $plan['planned_code'] = $newCode;
                $existing = Course::where('code', $newCode)->first();
            }

            $data = [
                'study_program_id' => $row['program']->id,
                'code' => $plan['planned_code'],
                'name' => $row['name'],
                'credits' => $row['credits'],
                'semester' => $row['semester'],
                'type' => $row['type'],
                'status' => true,
            ];

            if ($existing) {
                $courseStats['updated']++;
                if (! $this->dryRun) {
                    $fillable = $this->onlyEmptyFields($existing, $data);
                    if ($fillable !== []) {
                        $existing->update($fillable);
                    }
                }
            } else {
                $courseStats['inserted']++;
                if (! $this->dryRun) {
                    $existing = Course::create($data);
                }
            }

            $resolvedCourses[$planKey] = $existing;

            foreach ($plan['rows'] as $sourceRow) {
                if (! $this->dryRun && $existing) {
                    $this->saveMap('course', $sourceRow['source_id'], 'courses', $existing->id, [
                        'source_code' => $plan['source_code'],
                        'target_code' => $plan['planned_code'],
                    ]);
                }

                $relationKey = $sourceRow['curriculum_source_id'].'|'.$planKey;
                $relationPlans[$relationKey] = [
                    'curriculum_source_id' => $sourceRow['curriculum_source_id'],
                    'course_plan_key' => $planKey,
                    'semester' => $sourceRow['semester'],
                    'is_required' => $sourceRow['is_required'],
                ];
            }
        }

        $relationStats = ['total' => count($relationPlans), 'inserted' => 0, 'updated' => 0, 'skipped' => 0];
        foreach ($relationPlans as $relation) {
            $curriculum = $this->resolvedCurriculum($relation['curriculum_source_id']);
            $course = $resolvedCourses[$relation['course_plan_key']] ?? null;

            if (! $curriculum || (! $course && ! $this->dryRun)) {
                if ($this->dryRun && $this->curriculumPlans[$relation['curriculum_source_id']]['valid']) {
                    $relationStats['inserted']++;
                } else {
                    $relationStats['skipped']++;
                }

                continue;
            }

            $existing = $course
                ? CurriculumCourse::where('curriculum_id', $curriculum->id)
                    ->where('course_id', $course->id)
                    ->first()
                : null;
            $data = [
                'curriculum_id' => $curriculum->id,
                'course_id' => $course?->id,
                'semester' => $relation['semester'],
                'is_required' => $relation['is_required'],
            ];

            if ($existing) {
                $relationStats['updated']++;
                if (! $this->dryRun) {
                    $fillable = $this->onlyEmptyFields($existing, $data);
                    if ($fillable !== []) {
                        $existing->update($fillable);
                    }
                }
            } else {
                $relationStats['inserted']++;
                if (! $this->dryRun) {
                    CurriculumCourse::create($data);
                }
            }
        }

        $this->stats['Mata Kuliah ASC'] = $courseStats;
        $this->stats['Relasi Kurikulum-MK'] = $relationStats;
    }

    private function resolvedCurriculum(string $sourceId): ?Curriculum
    {
        $plan = $this->curriculumPlans[$sourceId] ?? null;
        if (! $plan || ! $plan['valid']) {
            return null;
        }

        return $this->mappedModel('curriculum', $sourceId, Curriculum::class)
            ?? Curriculum::where('code', $plan['code'])->first();
    }

    private function mappedCourseForPlan(array $plan): ?Course
    {
        foreach ($plan['rows'] as $row) {
            $mapped = $this->mappedModel('course', $row['source_id'], Course::class);
            if ($mapped) {
                return $mapped;
            }
        }

        return null;
    }

    /** @param class-string<Model> $modelClass */
    private function mappedModel(string $entity, string $sourceId, string $modelClass): ?Model
    {
        $map = LegacyMigrationMap::where([
            'source_system' => 'siakad',
            'entity' => $entity,
            'source_id' => $sourceId,
        ])->first();

        return $map ? $modelClass::find($map->target_id) : null;
    }

    private function saveMap(string $entity, string $sourceId, string $targetTable, int $targetId, array $metadata): void
    {
        LegacyMigrationMap::updateOrCreate(
            ['source_system' => 'siakad', 'entity' => $entity, 'source_id' => $sourceId],
            ['target_table' => $targetTable, 'target_id' => $targetId, 'metadata' => $metadata]
        );
    }

    /** @param array<string, array<int, array<string, mixed>>> $clusters */
    private function canonicalIdentity(string $sourceCode, array $clusters): string
    {
        $existing = Course::where('code', $sourceCode)->first();
        if ($existing) {
            foreach ($clusters as $identity => $rows) {
                if ($this->sameCourseIdentity($existing, $this->canonicalCourseRow($rows))) {
                    return $identity;
                }
            }

            return '';
        }

        return (string) array_key_first($clusters);
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function canonicalCourseRow(array $rows): array
    {
        usort($rows, function (array $left, array $right): int {
            $leftYear = $this->curriculumPlans[$left['curriculum_source_id']]['year'] ?? 0;
            $rightYear = $this->curriculumPlans[$right['curriculum_source_id']]['year'] ?? 0;

            return [$rightYear, (int) $right['source_id']] <=> [$leftYear, (int) $left['source_id']];
        });

        return $rows[0];
    }

    private function sameCourseIdentity(Course $course, array $source): bool
    {
        return (int) $course->study_program_id === (int) $source['program']?->id
            && $this->normalizeText($course->name) === $this->normalizeText($source['name']);
    }

    private function aliasCourseCode(string $sourceCode, string $identity): string
    {
        $suffix = '-'.strtoupper(substr(sha1($sourceCode.'|'.$identity), 0, 8));
        $prefix = substr($sourceCode, 0, max(1, 20 - strlen($suffix)));

        return $prefix.$suffix;
    }

    private function curriculumYear(array $row): ?int
    {
        $year = (int) ($row['tahun_mulai_berlaku'] ?? 0);
        if ($year >= 1900 && $year <= 2200) {
            return $year;
        }

        $semesterCode = trim((string) ($row['sem_id'] ?? ''));
        if (preg_match('/^(\d{4})[123]$/', $semesterCode, $match)) {
            return (int) $match[1];
        }

        $date = trim((string) ($row['tgl_sk_rektor'] ?? ''));
        if (preg_match('/^(\d{4})-/', $date, $match)) {
            return (int) $match[1];
        }

        return null;
    }

    private function curriculumDescription(array $row): ?string
    {
        $parts = [];
        if ($value = $this->nullableString($row['ket'] ?? null)) {
            $parts[] = $value;
        }
        if ($value = $this->nullableString($row['no_sk_rektor'] ?? null)) {
            $parts[] = "SK: {$value}";
        }
        if ($value = $this->nullableString($row['tgl_sk_rektor'] ?? null)) {
            $parts[] = "Tanggal SK: {$value}";
        }

        return $parts === [] ? null : implode(' | ', $parts);
    }

    private function courseCredits(array $row): int
    {
        return (int) ($row['sks_tm'] ?? 0)
            + (int) ($row['sks_prak'] ?? 0)
            + (int) ($row['sks_prak_lap'] ?? 0)
            + (int) ($row['sks_sim'] ?? 0);
    }

    private function courseType(array $row): string
    {
        $lecture = (int) ($row['sks_tm'] ?? 0);
        $practice = (int) ($row['sks_prak'] ?? 0)
            + (int) ($row['sks_prak_lap'] ?? 0)
            + (int) ($row['sks_sim'] ?? 0);
        if ($lecture === 0 && $practice > 0) {
            return 'Praktikum';
        }

        return in_array(strtoupper(trim((string) ($row['id_tipe_matkul'] ?? ''))), ['B', 'D'], true)
            ? 'Pilihan'
            : 'Wajib';
    }

    private function isRequiredCourse(array $row): bool
    {
        $required = trim((string) ($row['a_wajib'] ?? ''));
        if ($required !== '') {
            return $required === '1';
        }

        return ! in_array(strtoupper(trim((string) ($row['id_tipe_matkul'] ?? ''))), ['B', 'D'], true);
    }

    private function normalizeText(mixed $value): string
    {
        $normalized = preg_replace('/\s+/u', ' ', trim((string) $value)) ?? '';

        return mb_strtolower($normalized, 'UTF-8');
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /** @return array<string, mixed> */
    private function onlyEmptyFields(Model $model, array $data): array
    {
        return array_filter(
            $data,
            fn (mixed $value, string $key): bool => ($model->getAttribute($key) === null || $model->getAttribute($key) === '')
                && $value !== null
                && $value !== '',
            ARRAY_FILTER_USE_BOTH
        );
    }

    private function writeDuplicateReport(): string
    {
        $path = (string) ($this->option('report') ?: storage_path('app/migration/phase2-course-duplicates.csv'));
        File::ensureDirectoryExists(dirname($path));
        $handle = fopen($path, 'wb');
        if ($handle === false) {
            throw new \RuntimeException("Tidak dapat menulis laporan: {$path}");
        }

        fputcsv($handle, [
            'category', 'source_code', 'source_id', 'curriculum_id', 'study_program_code',
            'course_name', 'credits', 'semester', 'planned_target_code', 'resolution',
        ]);
        foreach ($this->reportRows as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);

        return $path;
    }

    private function displaySummary(string $reportPath): void
    {
        $this->newLine();
        $this->info('Analisis Duplikasi Mata Kuliah:');
        $this->table(
            ['Baris sumber', 'Kode unik', 'Kelompok duplikat', 'Kelompok konflik', 'Entitas ASC direncanakan'],
            [[
                count($this->courseRows),
                count(array_unique(array_map(fn (array $row): string => trim((string) ($row['kode_mk'] ?? '')), $this->courseRows))),
                $this->duplicateCodeGroups,
                $this->conflictingCodeGroups,
                count($this->coursePlans),
            ]]
        );

        $this->info('Hasil Migrasi Fase 2:');
        $rows = [];
        foreach ($this->stats as $table => $stats) {
            $rows[] = [$table, $stats['total'], $stats['inserted'], $stats['updated'], $stats['skipped']];
        }
        $this->table(['Tabel', 'Diproses', 'Diinsert', 'Diupdate', 'Dilewati'], $rows);
        $this->info("Laporan duplikasi lengkap: {$reportPath}");

        if ($this->dryRun) {
            $this->warn('DRY-RUN selesai. Database tidak diubah. Periksa laporan sebelum eksekusi nyata.');
        } else {
            $this->info('Migrasi fase 2 selesai. Data ASC yang sudah terisi tidak ditimpa.');
        }
    }
}
