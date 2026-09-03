<?php

namespace App\Console\Commands;

use App\Models\Building;
use App\Models\ClassModel;
use App\Models\Course;
use App\Models\Institution;
use App\Models\Lecturer;
use App\Models\LegacyMigrationMap;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\Semester;
use App\Services\SqlDumpParser;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class MigrateSiakadPhaseThree extends Command
{
    protected $signature = 'siakad:migrate-phase3
        {--source= : Path ke file SQL dump SIAKAD}
        {--dry-run : Simulasi tanpa mengubah database}
        {--table=all : Data yang diproses: all, facilities, classes, schedules}
        {--institution-id= : ID institusi tujuan jika ASC memiliki lebih dari satu institusi}
        {--report= : Path laporan CSV fase ketiga}';

    protected $description = 'Migrasi fase 3 SIAKAD: gedung/ruang, kelas, penugasan dosen, dan jadwal.';

    private bool $dryRun = false;

    private string $sqlPath = '';

    /** @var array<string, array{total:int, inserted:int, updated:int, skipped:int}> */
    private array $stats = [];

    /** @var array<int, array<string, mixed>> */
    private array $buildings = [];

    /** @var array<int, array<string, mixed>> */
    private array $rooms = [];

    /** @var array<int, array<string, mixed>> */
    private array $classes = [];

    /** @var array<int, array<string, mixed>> */
    private array $assignments = [];

    /** @var array<int, array<string, mixed>> */
    private array $schedules = [];

    /** @var array<int, array<string, mixed>> */
    private array $sourceLecturers = [];

    /** @var array<string, array<int, array<string, mixed>>> */
    private array $assignmentsByClass = [];

    /** @var array<string, array<int, array<string, mixed>>> */
    private array $schedulesByClass = [];

    /** @var array<string, array<string, Model>> */
    private array $mappedModels = [];

    /** @var array<string, Semester|null> */
    private array $resolvedSemesters = [];

    /** @var array<string, Lecturer|null> */
    private array $resolvedLecturers = [];

    /** @var array<string, bool> */
    private array $validClassPlans = [];

    /** @var array<string, bool> */
    private array $validRoomPlans = [];

    /** @var array<int, array<int, string|int|float|null>> */
    private array $reportRows = [];

    public function __construct(private readonly SqlDumpParser $parser)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->dryRun = (bool) $this->option('dry-run');
        $this->sqlPath = $this->option('source') ?? base_path('../_referensi/siakadstai_siakad.sql');
        $table = (string) $this->option('table');

        if (! in_array($table, ['all', 'facilities', 'classes', 'schedules'], true)) {
            $this->error("Nilai --table tidak valid: {$table}");

            return self::FAILURE;
        }

        if (! file_exists($this->sqlPath)) {
            $this->error("File tidak ditemukan: {$this->sqlPath}");

            return self::FAILURE;
        }

        if ($table !== 'facilities' && LegacyMigrationMap::where('entity', 'course')->doesntExist()) {
            $this->error('Pemetaan mata kuliah fase 2 belum tersedia. Jalankan fase 2 terlebih dahulu.');

            return self::FAILURE;
        }

        $institution = $this->resolveInstitution();
        if (! $institution) {
            return self::FAILURE;
        }

        if ($this->dryRun) {
            $this->warn('MODE DRY-RUN: database tidak akan diubah.');
        }

        $this->info("Membaca SQL dump: {$this->sqlPath}");
        $this->info('Ukuran file: '.round(filesize($this->sqlPath) / 1048576, 1).' MB');
        $this->loadSourceRows($table);

        DB::transaction(function () use ($table, $institution): void {
            if (in_array($table, ['all', 'facilities'], true)) {
                $this->migrateFacilities($institution);
            } else {
                $this->indexExistingFacilityPlans();
            }

            if (in_array($table, ['all', 'classes'], true)) {
                $this->migrateClasses();
            } else {
                $this->indexExistingClassPlans();
            }

            if (in_array($table, ['all', 'schedules'], true)) {
                $this->migrateSchedules();
            }
        });

        $reportPath = $this->writeReport();
        $this->displaySummary($reportPath);

        return self::SUCCESS;
    }

    private function resolveInstitution(): ?Institution
    {
        $requested = $this->option('institution-id');
        if ($requested !== null) {
            $institution = Institution::find((int) $requested);
            if (! $institution) {
                $this->error("Institusi ID {$requested} tidak ditemukan.");
            }

            return $institution;
        }

        if (Institution::count() !== 1) {
            $this->error('ASC memiliki nol atau lebih dari satu institusi. Tentukan --institution-id.');

            return null;
        }

        return Institution::first();
    }

    private function loadSourceRows(string $table): void
    {
        $tables = match ($table) {
            'facilities' => ['gedung_ref', 'ruang_ref'],
            'classes' => ['kelas', 'dosen_kelas', 'dosen'],
            'schedules' => ['jadwal_kuliah'],
            default => ['gedung_ref', 'ruang_ref', 'kelas', 'dosen_kelas', 'dosen', 'jadwal_kuliah'],
        };

        foreach ($tables as $sourceTable) {
            $this->line("  Parsing tabel '{$sourceTable}'...");
            $rows = $this->parser->parseTable($this->sqlPath, $sourceTable);
            $this->line('  Ditemukan '.count($rows).' baris.');
            match ($sourceTable) {
                'gedung_ref' => $this->buildings = $rows,
                'ruang_ref' => $this->rooms = $rows,
                'kelas' => $this->classes = $rows,
                'dosen_kelas' => $this->assignments = $rows,
                'dosen' => $this->sourceLecturers = $rows,
                'jadwal_kuliah' => $this->schedules = $rows,
            };
        }

        foreach ($this->assignments as $assignment) {
            $classId = trim((string) ($assignment['id_kelas'] ?? ''));
            $this->assignmentsByClass[$classId][] = $assignment;
        }
        foreach ($this->assignmentsByClass as &$assignments) {
            usort($assignments, static fn (array $a, array $b): int => (int) ($a['dosen_ke'] ?? 1) <=> (int) ($b['dosen_ke'] ?? 1));
        }
        unset($assignments);

        foreach ($this->schedules as $schedule) {
            $classId = trim((string) ($schedule['kelas_id'] ?? ''));
            $this->schedulesByClass[$classId][] = $schedule;
        }
    }

    private function migrateFacilities(Institution $institution): void
    {
        $buildingStats = $this->blankStats(count($this->buildings));
        $sourceBuildings = [];
        foreach ($this->buildings as $row) {
            $sourceId = trim((string) ($row['gedung_id'] ?? ''));
            if ($sourceId === '') {
                $buildingStats['skipped']++;
                $this->report('SKIPPED', 'building', '', '', 'ID gedung sumber kosong.');

                continue;
            }
            $sourceBuildings[$sourceId] = true;
            $this->upsertBuilding($row, $institution, $buildingStats);
        }

        $missingBuildingIds = [];
        foreach ($this->rooms as $row) {
            $buildingId = trim((string) ($row['gedung_id'] ?? ''));
            if ($buildingId !== '' && ! isset($sourceBuildings[$buildingId])) {
                $missingBuildingIds[$buildingId] = true;
            }
        }
        foreach (array_keys($missingBuildingIds) as $sourceId) {
            $buildingStats['total']++;
            $this->upsertBuilding([
                'gedung_id' => $sourceId,
                'kode_gedung' => "LEGACY-{$sourceId}",
                'nm_gedung' => "Gedung SIAKAD {$sourceId} (referensi hilang)",
                'is_aktif' => 'N',
            ], $institution, $buildingStats, true);
        }
        $this->stats['Gedung'] = $buildingStats;

        $roomStats = $this->blankStats(count($this->rooms));
        $reservedCodes = [];
        foreach ($this->rooms as $row) {
            $sourceId = trim((string) ($row['ruang_id'] ?? ''));
            $buildingSourceId = trim((string) ($row['gedung_id'] ?? ''));
            $building = $this->mappedModel('building', $buildingSourceId, Building::class);
            if ($sourceId === '' || (! $building && ! $this->dryRun)) {
                $roomStats['skipped']++;
                $this->report('SKIPPED', 'room', $sourceId, (string) ($row['kode_ruang'] ?? ''), 'ID ruang atau pemetaan gedung tidak tersedia.');

                continue;
            }

            $mapped = $this->mappedModel('room', $sourceId, Room::class);
            $baseCode = $this->safeCode((string) ($row['kode_ruang'] ?? ''), "ROOM-{$sourceId}");
            $code = $mapped?->code ?? $this->availableCode($baseCode, 'rooms', $sourceId, $reservedCodes);
            $existing = $mapped ?? Room::where('code', $code)->first();
            $data = [
                'building_id' => $building?->id,
                'code' => $code,
                'name' => $this->nonEmpty($row['nm_ruang'] ?? null, "Ruang SIAKAD {$sourceId}"),
                'floor' => 1,
                'capacity' => $this->roomCapacity($row),
                'type' => $this->roomType($row),
                'facilities' => $this->roomFacilities($row),
                'status' => strtoupper(trim((string) ($row['is_aktif'] ?? 'Y'))) !== 'N',
            ];
            $existing ? $roomStats['updated']++ : $roomStats['inserted']++;
            $this->validRoomPlans[$sourceId] = true;

            if (! $this->dryRun) {
                if ($existing) {
                    $this->fillEmpty($existing, $data);
                } else {
                    $existing = Room::create($data);
                }
                $this->saveMap('room', $sourceId, 'rooms', $existing->id, ['source_code' => $baseCode, 'target_code' => $existing->code]);
            }

            if ($code !== $baseCode) {
                $this->report('DUPLICATE_CODE_ALIASED', 'room', $sourceId, $baseCode, "Kode target: {$code}");
            }
        }
        $this->stats['Ruang'] = $roomStats;
    }

    private function upsertBuilding(array $row, Institution $institution, array &$stats, bool $synthetic = false): void
    {
        $sourceId = trim((string) $row['gedung_id']);
        $mapped = $this->mappedModel('building', $sourceId, Building::class);
        $baseCode = $this->safeCode((string) ($row['kode_gedung'] ?? ''), "BLDG-{$sourceId}");
        $code = $mapped?->code ?? $this->availableCode($baseCode, 'buildings', $sourceId);
        $existing = $mapped ?? Building::where('code', $code)->first();
        $data = [
            'institution_id' => $institution->id,
            'code' => $code,
            'name' => $this->nonEmpty($row['nm_gedung'] ?? null, "Gedung SIAKAD {$sourceId}"),
            'floors' => 1,
            'address' => null,
            'status' => ! $synthetic && strtoupper(trim((string) ($row['is_aktif'] ?? 'Y'))) !== 'N',
        ];
        $existing ? $stats['updated']++ : $stats['inserted']++;
        if (! $this->dryRun) {
            if ($existing) {
                $this->fillEmpty($existing, $data);
            } else {
                $existing = Building::create($data);
            }
            $this->saveMap('building', $sourceId, 'buildings', $existing->id, ['synthetic' => $synthetic, 'source_code' => $baseCode]);
        }
        if ($synthetic) {
            $this->report('SYNTHETIC_PARENT', 'building', $sourceId, $baseCode, 'Dibuat karena ruang sumber merujuk gedung yang tidak ada di gedung_ref.');
        }
    }

    private function migrateClasses(): void
    {
        $classStats = $this->blankStats(count($this->classes));
        $assignmentStats = $this->blankStats(count($this->assignments));
        $reservedNames = [];

        foreach ($this->classes as $row) {
            $sourceId = trim((string) ($row['kelas_id'] ?? ''));
            $course = $this->mappedModel('course', trim((string) ($row['id_matkul'] ?? '')), Course::class);
            $semester = $this->resolveSemester((string) ($row['sem_id'] ?? ''));
            if ($sourceId === '' || ! $course || ! $semester) {
                $classStats['skipped']++;
                $reason = ! $course ? 'Mata kuliah belum terpetakan dari fase 2.' : 'Semester sumber tidak ditemukan di ASC.';
                $this->report('SKIPPED', 'class', $sourceId, (string) ($row['id_matkul'] ?? ''), $reason);

                foreach ($this->assignmentsByClass[$sourceId] ?? [] as $assignment) {
                    $assignmentStats['skipped']++;
                }

                continue;
            }

            $resolvedAssignments = [];
            foreach ($this->assignmentsByClass[$sourceId] ?? [] as $index => $assignment) {
                $lecturer = $this->resolveLecturer((string) ($assignment['id_dosen'] ?? ''));
                if (! $lecturer) {
                    $assignmentStats['skipped']++;
                    $this->report('UNRESOLVED_LECTURER', 'class_lecturer', $sourceId.'|'.$index, (string) ($assignment['id_dosen'] ?? ''), 'Dosen penugasan tidak ditemukan di ASC.');
                } else {
                    $resolvedAssignments[] = [$assignment, $lecturer];
                }
            }

            $mapped = $this->mappedModel('class', $sourceId, ClassModel::class);
            $baseName = mb_substr($this->nonEmpty($row['kls_nama'] ?? null, "Kelas SIAKAD {$sourceId}"), 0, 50);
            $identity = $semester->id.'|'.$course->id.'|'.$baseName;
            $name = $mapped?->name ?? $this->availableClassName($baseName, $semester->id, $course->id, $sourceId, $reservedNames);
            $existing = $mapped ?? ClassModel::where([
                'semester_id' => $semester->id,
                'course_id' => $course->id,
                'name' => $name,
            ])->first();
            $primaryLecturer = $resolvedAssignments[0][1] ?? null;
            $room = $this->classRoomFromSchedule($sourceId);
            $data = [
                'study_program_id' => $course->study_program_id,
                'semester_id' => $semester->id,
                'course_id' => $course->id,
                'lecturer_id' => $primaryLecturer?->id,
                'room_id' => $room?->id,
                'name' => $name,
                'capacity' => max(1, (int) ($row['peserta_max'] ?? 40)),
                'academic_level' => max(1, (int) $course->semester),
                'is_active' => (bool) $semester->is_active && strtoupper(trim((string) ($row['is_open'] ?? 'Y'))) === 'Y',
            ];
            $existing ? $classStats['updated']++ : $classStats['inserted']++;
            $this->validClassPlans[$sourceId] = true;

            if (! $this->dryRun) {
                if ($existing) {
                    $this->fillEmpty($existing, $data);
                } else {
                    $existing = ClassModel::create($data);
                }
                $this->saveMap('class', $sourceId, 'classes', $existing->id, ['source_identity' => $identity, 'target_name' => $existing->name]);
            }

            if ($name !== $baseName) {
                $this->report('DUPLICATE_CLASS_ALIASED', 'class', $sourceId, $baseName, "Nama target: {$name}");
            }
            if ($resolvedAssignments === []) {
                $this->report('CLASS_WITHOUT_LECTURER', 'class', $sourceId, $name, 'Kelas dipertahankan tanpa dosen utama.');
            }

            foreach ($resolvedAssignments as $index => [$assignment, $lecturer]) {
                $assignmentSourceId = $sourceId.'|'.trim((string) ($assignment['id_dosen'] ?? '')).'|'.($index + 1);
                $pivotExists = $existing && DB::table('class_lecturers')->where(['class_id' => $existing->id, 'lecturer_id' => $lecturer->id])->exists();
                $pivotExists ? $assignmentStats['updated']++ : $assignmentStats['inserted']++;
                if (! $this->dryRun && $existing) {
                    DB::table('class_lecturers')->updateOrInsert(
                        ['class_id' => $existing->id, 'lecturer_id' => $lecturer->id],
                        [
                            'teaching_order' => max(1, (int) ($assignment['dosen_ke'] ?? $index + 1)),
                            'planned_meetings' => $this->nullablePositiveInteger($assignment['jml_tm_renc'] ?? null),
                            'actual_meetings' => $this->nullablePositiveInteger($assignment['jml_tm_real'] ?? null),
                            'can_input_grades' => strtoupper(trim((string) ($assignment['dapat_input'] ?? 'Y'))) !== 'N',
                            'teaching_credits' => $this->nullableNumeric($assignment['sks_ajar'] ?? null),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    );
                    $pivotId = (int) DB::table('class_lecturers')->where(['class_id' => $existing->id, 'lecturer_id' => $lecturer->id])->value('id');
                    $this->saveMap('class_lecturer', $assignmentSourceId, 'class_lecturers', $pivotId, ['class_source_id' => $sourceId]);
                }
            }
        }

        $this->stats['Kelas'] = $classStats;
        $this->stats['Penugasan Dosen'] = $assignmentStats;
    }

    private function migrateSchedules(): void
    {
        $stats = $this->blankStats(count($this->schedules));
        foreach ($this->schedules as $row) {
            $sourceId = trim((string) ($row['jadwal_id'] ?? ''));
            $classSourceId = trim((string) ($row['kelas_id'] ?? ''));
            $roomSourceId = trim((string) ($row['ruang_id'] ?? ''));
            $class = $this->mappedModel('class', $classSourceId, ClassModel::class);
            $room = $roomSourceId === '' ? null : $this->mappedModel('room', $roomSourceId, Room::class);
            $day = $this->normalizeDay($row['hari'] ?? null);
            $start = $this->normalizeTime($row['jam_mulai'] ?? null);
            $end = $this->normalizeTime($row['jam_selesai'] ?? null);
            $validClass = $class || ($this->dryRun && isset($this->validClassPlans[$classSourceId]));
            $validRoom = $roomSourceId === '' || $room || ($this->dryRun && isset($this->validRoomPlans[$roomSourceId]));

            if ($sourceId === '' || ! $validClass || ! $validRoom || ! $day || ! $start || ! $end || $start >= $end) {
                $stats['skipped']++;
                $reason = ! $validClass ? 'Kelas belum terpetakan.'
                    : (! $validRoom ? 'Ruang belum terpetakan.' : 'Hari atau rentang jam tidak valid/kosong.');
                $this->report('SKIPPED', 'schedule', $sourceId, $classSourceId, $reason);

                continue;
            }

            $mapped = $this->mappedModel('schedule', $sourceId, Schedule::class);
            $existing = $mapped;
            if (! $mapped && $class) {
                $existing = Schedule::where([
                    'class_id' => $class->id,
                    'day' => $day,
                    'start_time' => $start,
                    'end_time' => $end,
                ])->first();
            }
            $existing ? $stats['updated']++ : $stats['inserted']++;

            if (! $this->dryRun && $class) {
                $data = [
                    'class_id' => $class->id,
                    'day' => $day,
                    'start_time' => $start,
                    'end_time' => $end,
                    'room_id' => $room?->id,
                    'lecturer_id' => $class->lecturer_id,
                    'note' => 'Migrasi SIAKAD fase 3',
                    'is_active' => (bool) $class->is_active,
                ];
                if ($existing) {
                    $this->fillEmpty($existing, $data);
                } else {
                    $existing = Schedule::create($data);
                }
                $this->saveMap('schedule', $sourceId, 'schedules', $existing->id, ['class_source_id' => $classSourceId]);
            }
        }
        $this->stats['Jadwal'] = $stats;
    }

    private function indexExistingFacilityPlans(): void
    {
        foreach (LegacyMigrationMap::where('source_system', 'siakad')->where('entity', 'room')->get() as $map) {
            if (Room::whereKey($map->target_id)->exists()) {
                $this->validRoomPlans[$map->source_id] = true;
            }
        }
    }

    private function indexExistingClassPlans(): void
    {
        foreach (LegacyMigrationMap::where('source_system', 'siakad')->where('entity', 'class')->get() as $map) {
            if (ClassModel::whereKey($map->target_id)->exists()) {
                $this->validClassPlans[$map->source_id] = true;
            }
        }
    }

    private function classRoomFromSchedule(string $classSourceId): ?Room
    {
        foreach ($this->schedulesByClass[$classSourceId] ?? [] as $row) {
            return $this->mappedModel('room', trim((string) ($row['ruang_id'] ?? '')), Room::class);
        }

        return null;
    }

    private function resolveSemester(string $code): ?Semester
    {
        $code = trim($code);
        if (array_key_exists($code, $this->resolvedSemesters)) {
            return $this->resolvedSemesters[$code];
        }
        if (! preg_match('/^(\d{4})([123])$/', $code, $match)) {
            return $this->resolvedSemesters[$code] = null;
        }
        $type = match ($match[2]) {
            '1' => 'Ganjil',
            '2' => 'Genap',
            default => 'Pendek',
        };
        $name = $type.' '.$match[1].'/'.((int) $match[1] + 1);

        return $this->resolvedSemesters[$code] = Semester::where('name', $name)
            ->orWhere('name', "Semester {$name}")
            ->first();
    }

    private function resolveLecturer(string $reference): ?Lecturer
    {
        $reference = trim($reference);
        if ($reference === '') {
            return null;
        }
        if (array_key_exists($reference, $this->resolvedLecturers)) {
            return $this->resolvedLecturers[$reference];
        }
        $mapped = $this->mappedModel('lecturer', $reference, Lecturer::class);
        if ($mapped) {
            return $this->resolvedLecturers[$reference] = $mapped;
        }

        $source = null;
        foreach ($this->sourceLecturers as $row) {
            if (in_array($reference, array_map('strval', [
                $row['id_dosen'] ?? '', $row['nidn'] ?? '', $row['nip'] ?? '', $row['email'] ?? '',
            ]), true)) {
                $source = $row;
                break;
            }
        }
        $candidates = array_values(array_filter(array_unique([
            $reference,
            trim((string) ($source['nidn'] ?? '')),
            trim((string) ($source['nip'] ?? '')),
            trim((string) ($source['email'] ?? '')),
        ]), static fn (string $value): bool => $value !== '' && $value !== '0'));

        return $this->resolvedLecturers[$reference] = Lecturer::where(function ($query) use ($candidates): void {
            foreach ($candidates as $candidate) {
                $query->orWhere('nidn', $candidate)->orWhere('nip', $candidate)->orWhere('email', $candidate);
            }
        })->first();
    }

    /** @param class-string<Model> $modelClass */
    private function mappedModel(string $entity, string $sourceId, string $modelClass): ?Model
    {
        if ($sourceId === '') {
            return null;
        }
        if (isset($this->mappedModels[$entity][$sourceId])) {
            return $this->mappedModels[$entity][$sourceId];
        }
        $map = LegacyMigrationMap::where([
            'source_system' => 'siakad', 'entity' => $entity, 'source_id' => $sourceId,
        ])->first();

        $model = $map ? $modelClass::find($map->target_id) : null;
        if ($model) {
            $this->mappedModels[$entity][$sourceId] = $model;
        }

        return $model;
    }

    private function saveMap(string $entity, string $sourceId, string $targetTable, int $targetId, array $metadata = []): void
    {
        LegacyMigrationMap::updateOrCreate(
            ['source_system' => 'siakad', 'entity' => $entity, 'source_id' => $sourceId],
            ['target_table' => $targetTable, 'target_id' => $targetId, 'metadata' => $metadata]
        );

        $modelClass = match ($targetTable) {
            'buildings' => Building::class,
            'rooms' => Room::class,
            'classes' => ClassModel::class,
            'schedules' => Schedule::class,
            default => null,
        };
        if ($modelClass && ($model = $modelClass::find($targetId))) {
            $this->mappedModels[$entity][$sourceId] = $model;
        }
    }

    private function fillEmpty(Model $model, array $data): void
    {
        $fillable = [];
        foreach ($data as $key => $value) {
            if (($model->getAttribute($key) === null || $model->getAttribute($key) === '') && $value !== null && $value !== '') {
                $fillable[$key] = $value;
            }
        }
        if ($fillable !== []) {
            $model->update($fillable);
        }
    }

    private function availableCode(string $base, string $table, string $sourceId, array &$reserved = []): string
    {
        $key = mb_strtolower($base);
        if (! isset($reserved[$key]) && ! DB::table($table)->where('code', $base)->exists()) {
            $reserved[$key] = true;

            return $base;
        }
        $suffix = '-'.strtoupper(substr(sha1($table.'|'.$sourceId), 0, 6));
        $code = mb_substr($base, 0, 20 - strlen($suffix)).$suffix;
        $reserved[mb_strtolower($code)] = true;

        return $code;
    }

    private function availableClassName(string $base, int $semesterId, int $courseId, string $sourceId, array &$reserved): string
    {
        $key = mb_strtolower("{$semesterId}|{$courseId}|{$base}");
        if (! isset($reserved[$key]) && ! ClassModel::where(['semester_id' => $semesterId, 'course_id' => $courseId, 'name' => $base])->exists()) {
            $reserved[$key] = true;

            return $base;
        }
        $suffix = '-'.strtoupper(substr(sha1($sourceId), 0, 6));
        $name = mb_substr($base, 0, 50 - strlen($suffix)).$suffix;
        $reserved[mb_strtolower("{$semesterId}|{$courseId}|{$name}")] = true;

        return $name;
    }

    private function safeCode(string $value, string $fallback): string
    {
        $value = trim($value) ?: $fallback;
        if (mb_strlen($value) <= 20) {
            return $value;
        }
        $suffix = '-'.strtoupper(substr(sha1($value), 0, 6));

        return mb_substr($value, 0, 20 - strlen($suffix)).$suffix;
    }

    private function roomCapacity(array $row): int
    {
        $capacity = (int) ($row['kapasitas'] ?? 0);
        if ($capacity <= 0 && preg_match('/KAP\s*(\d+)/i', (string) ($row['ket'] ?? ''), $match)) {
            $capacity = (int) $match[1];
        }

        return $capacity > 0 ? $capacity : 40;
    }

    private function roomType(array $row): string
    {
        $text = mb_strtolower(implode(' ', [(string) ($row['nm_ruang'] ?? ''), (string) ($row['ket'] ?? '')]));

        return match (true) {
            str_contains($text, 'lab') => 'Lab',
            str_contains($text, 'aula'), str_contains($text, 'auditorium') => 'Aula',
            str_contains($text, 'sidang'), str_contains($text, 'seminar') => 'Seminar',
            str_contains($text, 'tu'), str_contains($text, 'dosen'), str_contains($text, 'operator'), str_contains($text, 'rektor') => 'Kantor',
            str_contains($text, 'lapang'), str_contains($text, 'perpustakaan') => 'Lainnya',
            default => 'Kelas',
        };
    }

    private function roomFacilities(array $row): ?array
    {
        $values = array_values(array_filter([
            $this->nullableString($row['fasilitas'] ?? null),
            $this->nullableString($row['ket'] ?? null),
        ]));

        return $values === [] ? null : $values;
    }

    private function normalizeDay(mixed $value): ?string
    {
        $value = mb_strtolower(trim((string) $value));
        $value = str_replace(["'", '’'], '', $value);

        return match ($value) {
            'senin' => 'Senin', 'selasa' => 'Selasa', 'rabu' => 'Rabu', 'kamis' => 'Kamis',
            'jumat' => 'Jumat', 'sabtu' => 'Sabtu', 'minggu', 'ahad' => 'Minggu',
            default => null,
        };
    }

    private function normalizeTime(mixed $value): ?string
    {
        $value = trim((string) $value);
        if (! preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $value, $match)) {
            return null;
        }
        $hour = (int) $match[1];
        $minute = (int) $match[2];
        $second = (int) ($match[3] ?? 0);
        if ($hour > 23 || $minute > 59 || $second > 59) {
            return null;
        }

        return sprintf('%02d:%02d:%02d', $hour, $minute, $second);
    }

    private function nullablePositiveInteger(mixed $value): ?int
    {
        return is_numeric($value) && (int) $value >= 0 ? (int) $value : null;
    }

    private function nullableNumeric(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function nonEmpty(mixed $value, string $fallback): string
    {
        return $this->nullableString($value) ?? $fallback;
    }

    /** @return array{total:int, inserted:int, updated:int, skipped:int} */
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
        $path = $this->option('report') ?: storage_path('app/migration/phase3-migration-report.csv');
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
        $this->info('Hasil Migrasi Fase 3:');
        $rows = [];
        foreach ($this->stats as $table => $stats) {
            $rows[] = [$table, $stats['total'], $stats['inserted'], $stats['updated'], $stats['skipped']];
        }
        $this->table(['Tabel', 'Diproses', 'Diinsert', 'Diupdate', 'Dilewati'], $rows);
        $this->line('Laporan lengkap: '.$reportPath);
        $this->dryRun
            ? $this->warn('DRY-RUN selesai. Jalankan tanpa --dry-run untuk eksekusi nyata.')
            : $this->info('Migrasi fase 3 selesai. Data ASC yang sudah terisi tidak ditimpa.');
    }
}
