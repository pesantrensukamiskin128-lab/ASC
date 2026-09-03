<?php

namespace App\Console\Commands;

use App\Models\AcademicYear;
use App\Models\Faculty;
use App\Models\Institution;
use App\Models\Lecturer;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentAddress;
use App\Models\StudentProfile;
use App\Models\StudyProgram;
use App\Models\User;
use App\Services\SqlDumpParser;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class MigrateSiakadData extends Command
{
    protected $signature = 'siakad:migrate
        {--source= : Path ke file SQL dump SIAKAD}
        {--dry-run : Simulasi saja, tidak ubah data}
        {--institution-id= : ID institusi tujuan (wajib jika database memiliki lebih dari satu institusi)}
        {--table=all : Tabel yang dimigrasi: all, faculties, study_programs, semesters, lecturers, students}';

    protected $description = 'Migrasi data dari SQL dump SIAKAD lama ke ASC. Menggunakan UPSERT — data yang sudah ada tidak dihapus.';

    private bool $dryRun = false;

    private string $sqlPath = '';

    private array $stats = [];

    private ?int $institutionId = null;

    public function __construct(private readonly SqlDumpParser $parser)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->dryRun = $this->option('dry-run');
        $source = $this->option('source') ?? base_path('../_referensi/siakadstai_siakad.sql');
        $table = $this->option('table');

        if (! file_exists($source)) {
            $this->error("File tidak ditemukan: {$source}");

            return 1;
        }

        $this->sqlPath = $source;

        if ($table === 'all' || in_array($table, ['faculties', 'study_programs'], true)) {
            $this->institutionId = $this->resolveInstitutionId();
        }

        if ($this->dryRun) {
            $this->warn('MODE DRY-RUN: tidak ada data yang diubah.');
        }

        $this->info("Membaca SQL dump: {$source}");
        $sizeMb = round(filesize($source) / 1048576, 1);
        $this->info("Ukuran file: {$sizeMb} MB");

        DB::transaction(function () use ($table) {
            if ($table === 'all' || $table === 'faculties') {
                $this->migrateFaculties();
            }
            if ($table === 'all' || $table === 'study_programs') {
                $this->migrateStudyPrograms();
            }
            if ($table === 'all' || $table === 'semesters') {
                $this->migrateSemesters();
            }
            if ($table === 'all' || $table === 'lecturers') {
                $this->migrateLecturers();
            }
            if ($table === 'all' || $table === 'students') {
                $this->migrateStudents();
            }
        });

        $this->newLine();
        $this->info('Hasil Migrasi:');
        $headers = ['Tabel', 'Diproses', 'Diinsert', 'Diupdate', 'Dilewati'];
        $rows = [];
        foreach ($this->stats as $tbl => $s) {
            $rows[] = [$tbl, $s['total'] ?? 0, $s['inserted'] ?? 0, $s['updated'] ?? 0, $s['skipped'] ?? 0];
        }
        $this->table($headers, $rows);

        if ($this->dryRun) {
            $this->warn('DRY-RUN selesai. Jalankan tanpa --dry-run untuk eksekusi nyata.');
        } else {
            $this->info('Migrasi selesai!');
        }

        return 0;
    }

    /**
     * Parse INSERT statements dari SQL dump untuk tabel tertentu.
     * Mengembalikan array of associative arrays.
     */
    private function parseTable(string $tableName): array
    {
        $this->line("  Parsing tabel '{$tableName}' dari SQL dump...");
        $results = $this->parser->parseTable($this->sqlPath, $tableName);
        $this->line('  Ditemukan '.count($results)." baris dari tabel '{$tableName}'");

        return $results;
    }

    private function resolveInstitutionId(): int
    {
        $requestedId = $this->option('institution-id');

        if ($requestedId !== null) {
            $institution = Institution::find($requestedId);

            if (! $institution) {
                throw new \RuntimeException("Institusi dengan ID {$requestedId} tidak ditemukan.");
            }

            return $institution->id;
        }

        $institutionIds = Institution::query()->limit(2)->pluck('id');

        if ($institutionIds->count() !== 1) {
            throw new \RuntimeException(
                'Gunakan --institution-id karena database harus memiliki tepat satu institusi tujuan.'
            );
        }

        return (int) $institutionIds->first();
    }

    private function migrateFaculties(): void
    {
        $this->info('Migrasi Fakultas...');
        $rows = $this->parseTable('fakultas');
        $s = &$this->stats['Fakultas'];
        $s = ['total' => count($rows), 'inserted' => 0, 'updated' => 0, 'skipped' => 0];

        foreach ($rows as $r) {
            $code = trim($r['kode_fak'] ?? '');
            $name = trim($r['nama_resmi'] ?? $r['nama_singkat'] ?? '');
            if (! $code || ! $name) {
                $s['skipped']++;

                continue;
            }

            $existing = Faculty::where('code', $code)->first();
            $data = [
                'institution_id' => $this->institutionId,
                'code' => $code,
                'name' => $name,
                'dean_name' => $this->nullableString($r['dekan'] ?? null),
                'status' => true,
            ];

            if (! $this->dryRun) {
                if ($existing) {
                    // Update hanya field yang kosong di ASC
                    $existing->update($this->onlyEmptyFields($existing, $data));
                    $s['updated']++;
                } else {
                    Faculty::create($data);
                    $s['inserted']++;
                }
            } else {
                $this->line("  [DRY] Fakultas: {$code} - {$name} → ".($existing ? 'UPDATE' : 'INSERT'));
                $existing ? $s['updated']++ : $s['inserted']++;
            }
        }
        $this->line("  ✓ {$s['inserted']} insert, {$s['updated']} update, {$s['skipped']} skip");
    }

    private function migrateStudyPrograms(): void
    {
        $this->info('Migrasi Program Studi...');
        $rows = $this->parseTable('jurusan');
        $levels = collect($this->parseTable('jenjang'))
            ->mapWithKeys(fn (array $row) => [(string) ($row['idjenjang'] ?? '') => $row['jenjang'] ?? null]);
        $s = &$this->stats['Program Studi'];
        $s = ['total' => count($rows), 'inserted' => 0, 'updated' => 0, 'skipped' => 0];

        foreach ($rows as $r) {
            $code = trim($r['kode_jur'] ?? '');
            $name = trim($r['nama_jur'] ?? '');
            if (! $code || ! $name) {
                $s['skipped']++;

                continue;
            }

            $facultyId = Faculty::where('code', trim($r['fak_kode'] ?? ''))->value('id');
            if (! $facultyId) {
                $this->warn("  Lewati prodi {$code}: fakultas sumber tidak ditemukan di ASC.");
                $s['skipped']++;

                continue;
            }

            $existing = StudyProgram::where('code', $code)->first();
            $data = [
                'faculty_id' => $facultyId,
                'code' => $code,
                'name' => $name,
                'level' => $this->nullableString($levels[(string) ($r['id_jenjang'] ?? '')] ?? null),
                'status' => $this->toBoolean($r['status'] ?? null, true),
            ];

            if (! $this->dryRun) {
                if ($existing) {
                    $existing->update($this->onlyEmptyFields($existing, $data));
                    $s['updated']++;
                } else {
                    StudyProgram::create($data);
                    $s['inserted']++;
                }
            } else {
                $this->line("  [DRY] Prodi: {$code} - {$name} → ".($existing ? 'UPDATE' : 'INSERT'));
                $existing ? $s['updated']++ : $s['inserted']++;
            }
        }
        $this->line("  ✓ {$s['inserted']} insert, {$s['updated']} update, {$s['skipped']} skip");
    }

    private function migrateLecturers(): void
    {
        $this->info('Migrasi Dosen...');
        $rows = $this->parseTable('dosen');
        $s = &$this->stats['Dosen'];
        $s = ['total' => count($rows), 'inserted' => 0, 'updated' => 0, 'skipped' => 0];

        foreach ($rows as $r) {
            $nidn = trim($r['nidn'] ?? '');
            $name = trim($r['nama_dosen'] ?? '');
            if (! $name) {
                $s['skipped']++;

                continue;
            }

            $legacyId = trim((string) ($r['id_dosen'] ?? ''));
            $accountId = $nidn ?: (trim($r['nip'] ?? '') ?: "dosen-{$legacyId}");
            $email = $this->normalizeEmail(
                $r['email'] ?? null,
                "dosen-{$accountId}@stai-aljawami.ac.id"
            );
            $prodiCode = trim($r['kode_jur'] ?? '');
            $prodiId = $prodiCode ? StudyProgram::where('code', $prodiCode)->value('id') : null;

            // Find existing by nidn or nip
            $existing = null;
            if ($nidn) {
                $existing = Lecturer::where('nidn', $nidn)->first();
            }
            if (! $existing && ($r['nip'] ?? '')) {
                $existing = Lecturer::where('nip', $r['nip'])->first();
            }
            if (! $existing && $email) {
                $existing = Lecturer::where('email', $email)->first();
            }

            $gender = $this->normalizeGender($r['jk'] ?? null);
            $birthDate = $this->validDate($r['tgl_lahir'] ?? null);

            $lecturerData = array_filter([
                'study_program_id' => $prodiId,
                'nidn' => $nidn ?: null,
                'nip' => $this->nullableString($r['nip'] ?? null),
                'full_name' => $name,
                'degree_front' => $this->nullableString($r['gelar_depan'] ?? null),
                'degree_back' => $this->nullableString($r['gelar_belakang'] ?? null),
                'gender' => $gender,
                'birth_place' => $this->nullableString($r['tmpt_lahir'] ?? null),
                'birth_date' => $birthDate,
                'email' => $email,
                'phone' => $this->nullableString($r['no_hp'] ?? null),
                'address' => $this->nullableString($r['alamat'] ?? null),
                'status' => $this->toBoolean($r['aktif'] ?? null, true),
            ], fn ($v) => $v !== null && $v !== '');

            if (! $this->dryRun) {
                if ($existing) {
                    // Only fill empty fields
                    $fillable = $this->onlyEmptyFields($existing, $lecturerData);
                    if ($fillable) {
                        $existing->update($fillable);
                    }
                    if (! $existing->user_id) {
                        $user = $this->ensureUser($accountId, $name, $email, 'DOSEN');
                        $existing->update(['user_id' => $user->id]);
                    }
                    $s['updated']++;
                } else {
                    $lecturer = Lecturer::create($lecturerData);
                    $user = $this->ensureUser($accountId, $name, $email, 'DOSEN');
                    $lecturer->update(['user_id' => $user->id]);
                    $s['inserted']++;
                }
            } else {
                $this->line("  [DRY] Dosen: {$nidn} - {$name} → ".($existing ? 'UPDATE' : 'INSERT'));
                $existing ? $s['updated']++ : $s['inserted']++;
            }
        }
        $this->line("  ✓ {$s['inserted']} insert, {$s['updated']} update, {$s['skipped']} skip");
    }

    private function migrateStudents(): void
    {
        $this->info('Migrasi Mahasiswa...');
        $rows = $this->parseTable('mahasiswa');
        $sourceLecturers = collect($this->parseTable('dosen'))
            ->keyBy(fn (array $row) => (string) ($row['id_dosen'] ?? ''));
        $religions = collect($this->parseTable('agama'))
            ->mapWithKeys(fn (array $row) => [(string) ($row['id_agama'] ?? '') => $row['nm_agama'] ?? null]);
        $s = &$this->stats['Mahasiswa'];
        $s = ['total' => count($rows), 'inserted' => 0, 'updated' => 0, 'skipped' => 0];

        // Status mapping dari SIAKAD ke ASC
        $statusMap = [
            'A' => 'Aktif', '1' => 'Aktif', 'AKTIF' => 'Aktif',
            'C' => 'Cuti', 'D' => 'DO', 'L' => 'Lulus',
            'K' => 'Mengundurkan Diri', 'N' => 'Nonaktif',
            'G' => 'Aktif', 'X' => 'Aktif',
        ];

        foreach ($rows as $r) {
            $nim = trim($r['nim'] ?? '');
            $name = trim($r['nama'] ?? '');
            if (! $nim || ! $name) {
                $s['skipped']++;

                continue;
            }

            $email = $this->normalizeEmail($r['email'] ?? null, "{$nim}@student.stai-aljawami.ac.id");
            $prodiCode = trim($r['jur_kode'] ?? '');
            $prodiId = $prodiCode ? StudyProgram::where('code', $prodiCode)->value('id') : null;
            if (! $prodiId) {
                $this->warn("  Lewati mahasiswa {$nim}: prodi {$prodiCode} tidak ditemukan di ASC.");
                $s['skipped']++;

                continue;
            }

            // dosen_pemb menyimpan id_dosen lama, bukan NIDN.
            $advisorId = null;
            $advisorReference = trim((string) ($r['dosen_pemb'] ?? ''));
            if ($advisorReference !== '') {
                $sourceAdvisor = $sourceLecturers->get($advisorReference);
                $advisorId = $this->findLecturerId($sourceAdvisor, $advisorReference);
            }

            $gender = $this->normalizeGender($r['jk'] ?? null);
            $birthDate = $this->validDate($r['tgl_lahir'] ?? null);

            // Entry year from mulai_smt (e.g. 20201 → 2020)
            $entryYear = null;
            if (! empty($r['mulai_smt'])) {
                $entryYear = (int) substr((string) $r['mulai_smt'], 0, 4);
            }
            $academicYearId = $entryYear
                ? AcademicYear::whereIn('name', [
                    "Tahun Akademik {$entryYear}/".($entryYear + 1),
                    "{$entryYear}/".($entryYear + 1),
                ])->value('id')
                : null;

            $rawStatus = strtoupper(trim($r['stat_pd'] ?? 'A'));
            $status = $statusMap[$rawStatus] ?? 'Aktif';

            $studentData = array_filter([
                'study_program_id' => $prodiId,
                'academic_year_id' => $academicYearId,
                'advisor_id' => $advisorId,
                'nim' => $nim,
                'name' => $name,
                'gender' => $gender,
                'birth_place' => $this->nullableString($r['tmpt_lahir'] ?? null),
                'birth_date' => $birthDate,
                'email' => $email,
                'phone' => $this->nullableString($r['telepon_seluler'] ?? null),
                'entry_year' => $entryYear,
                'status' => $status,
            ], fn ($v) => $v !== null && $v !== '');

            $existing = Student::where('nim', $nim)->first();

            if (! $this->dryRun) {
                if ($existing) {
                    // Only fill empty fields
                    $fillable = $this->onlyEmptyFields($existing, $studentData);
                    if ($fillable) {
                        $existing->update($fillable);
                    }
                    $student = $existing;
                    $s['updated']++;
                } else {
                    $student = Student::create($studentData);
                    $s['inserted']++;
                }

                if (! $student->user_id) {
                    $user = $this->ensureUser(
                        $nim,
                        $name,
                        $studentData['email'],
                        'MAHASISWA'
                    );
                    $student->update(['user_id' => $user->id]);
                }

                $this->syncStudentDetails($student, $r, $religions->all());
            } else {
                $this->line("  [DRY] Mahasiswa: {$nim} - {$name} → ".($existing ? 'UPDATE' : 'INSERT'));
                $existing ? $s['updated']++ : $s['inserted']++;
            }
        }
        $this->line("  ✓ {$s['inserted']} insert, {$s['updated']} update, {$s['skipped']} skip");
    }

    private function findLecturerId(?array $sourceLecturer, string $fallbackReference): ?int
    {
        if ($sourceLecturer) {
            $nidn = $this->nullableString($sourceLecturer['nidn'] ?? null);
            if ($nidn && ($id = Lecturer::where('nidn', $nidn)->value('id'))) {
                return (int) $id;
            }

            $nip = $this->nullableString($sourceLecturer['nip'] ?? null);
            if ($nip && ($id = Lecturer::where('nip', $nip)->value('id'))) {
                return (int) $id;
            }

            $email = $this->nullableString($sourceLecturer['email'] ?? null);
            if ($email && ($id = Lecturer::where('email', $email)->value('id'))) {
                return (int) $id;
            }
        }

        $id = Lecturer::where('nidn', $fallbackReference)->value('id')
            ?? Lecturer::where('nip', $fallbackReference)->value('id');

        return $id === null ? null : (int) $id;
    }

    /** @param array<string, string|null> $religions */
    private function syncStudentDetails(Student $student, array $source, array $religions): void
    {
        $profileData = array_filter([
            'religion' => $this->nullableString($religions[(string) ($source['id_agama'] ?? '')] ?? null),
            'nik' => $this->nullableString($source['nik'] ?? null),
            'nisn' => $this->nullableString($source['nisn'] ?? null),
        ], fn (mixed $value): bool => $value !== null && $value !== '');

        if ($profileData !== []) {
            $profile = StudentProfile::firstOrNew(['student_id' => $student->id]);
            $profile->fill($profile->exists ? $this->onlyEmptyFields($profile, $profileData) : $profileData);
            $profile->save();
        }

        $street = $this->nullableString($source['jln'] ?? null);
        $rt = $this->nullableString($source['rt'] ?? null);
        $rw = $this->nullableString($source['rw'] ?? null);
        if ($street && ($rt || $rw)) {
            $street .= sprintf(' RT %s/RW %s', $rt ?: '-', $rw ?: '-');
        }

        $addressData = array_filter([
            'address' => $street,
            'village' => $this->nullableString($source['ds_kel'] ?? null),
            'postal_code' => $this->nullableString($source['kode_pos'] ?? null),
        ], fn (mixed $value): bool => $value !== null && $value !== '');

        if ($addressData !== []) {
            $address = StudentAddress::firstOrNew([
                'student_id' => $student->id,
                'type' => 'Domisili',
            ]);
            $address->fill($address->exists ? $this->onlyEmptyFields($address, $addressData) : $addressData);
            $address->save();
        }
    }

    private function ensureUser(string $username, string $name, string $email, string $role): User
    {
        $user = User::where('username', $username)->first();

        if (! $user) {
            $userEmail = $email;
            if (User::where('email', $userEmail)->exists()) {
                $userEmail = 'migration-'.sha1($username).'@stai-aljawami.ac.id';
            }

            $user = User::create([
                'username' => $username,
                'name' => $name,
                'email' => $userEmail,
                'password' => Hash::make($username),
                'is_active' => true,
            ]);
        }

        if (! $user->hasRole($role)) {
            $user->assignRole($role);
        }

        return $user;
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

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function normalizeEmail(mixed $value, string $fallback): string
    {
        $email = $this->nullableString($value);

        return $email && filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : $fallback;
    }

    private function normalizeGender(mixed $value): ?string
    {
        return match (strtoupper(trim((string) $value))) {
            'L', '1' => 'L',
            'P', '2' => 'P',
            default => null,
        };
    }

    private function toBoolean(mixed $value, bool $default): bool
    {
        if ($value === null || trim((string) $value) === '') {
            return $default;
        }

        return in_array(strtoupper(trim((string) $value)), ['1', 'Y', 'YES', 'TRUE', 'A', 'AKTIF'], true);
    }

    private function validDate(mixed $value): ?string
    {
        $date = $this->nullableString($value);
        if (! $date || $date === '0000-00-00') {
            return null;
        }

        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        $errors = \DateTimeImmutable::getLastErrors();

        if (! $parsed || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            return null;
        }

        return $parsed->format('Y-m-d') === $date ? $date : null;
    }

    private function migrateSemesters(): void
    {
        $this->info('Migrasi Tahun Akademik & Semester...');
        $rows = $this->parseTable('semester');

        // Tabel semester lama berisi satu baris per prodi. ASC menyimpan satu
        // semester global, sehingga data dideduplikasi berdasarkan id_semester.
        $periods = [];
        foreach ($rows as $row) {
            $code = trim((string) ($row['id_semester'] ?? ''));
            if ($code === '') {
                continue;
            }

            if (! isset($periods[$code])) {
                $periods[$code] = $row;

                continue;
            }

            if ($this->toBoolean($row['is_aktif'] ?? null, false)) {
                $periods[$code]['is_aktif'] = '1';
            }
        }

        ksort($periods);

        $academicYearStats = &$this->stats['Tahun Akademik'];
        $academicYearStats = ['total' => 0, 'inserted' => 0, 'updated' => 0, 'skipped' => 0];
        $s = &$this->stats['Semester'];
        $s = ['total' => count($periods), 'inserted' => 0, 'updated' => 0, 'skipped' => 0];
        $seenAcademicYears = [];

        foreach ($periods as $code => $r) {
            $period = $this->decodeSemesterCode($code);
            if ($period === null) {
                $this->warn("  Lewati semester dengan kode tidak valid: {$code}");
                $s['skipped']++;

                continue;
            }

            [$yearStart, $typeName] = $period;
            $yearEnd = $yearStart + 1;
            $academicYearName = "Tahun Akademik {$yearStart}/{$yearEnd}";
            $semesterName = "{$typeName} {$yearStart}/{$yearEnd}";

            $academicYear = AcademicYear::whereIn('name', [
                $academicYearName,
                "{$yearStart}/{$yearEnd}",
            ])->first();
            if (! isset($seenAcademicYears[$academicYearName])) {
                $seenAcademicYears[$academicYearName] = true;
                $academicYearStats['total']++;
                $academicYear ? $academicYearStats['updated']++ : $academicYearStats['inserted']++;
            }

            if (! $this->dryRun && ! $academicYear) {
                $academicYear = AcademicYear::create([
                    'name' => $academicYearName,
                    'start_date' => "{$yearStart}-09-01",
                    'end_date' => "{$yearEnd}-08-31",
                    'is_active' => false,
                ]);
            }

            [$defaultStart, $defaultEnd] = $this->defaultSemesterDates($yearStart, $yearEnd, $typeName);
            $startDate = $this->dateWithinAcademicYear($r['tgl_mulai'] ?? null, $yearStart, $yearEnd)
                ?? $defaultStart;
            $endDate = $this->dateWithinAcademicYear($r['tgl_selesai'] ?? null, $yearStart, $yearEnd)
                ?? $defaultEnd;
            if ($endDate < $startDate) {
                [$startDate, $endDate] = [$defaultStart, $defaultEnd];
            }

            $existing = Semester::whereIn('name', [
                $semesterName,
                "Semester {$semesterName}",
            ])->first();
            $semesterData = [
                'academic_year_id' => $academicYear?->id,
                'name' => $semesterName,
                'type' => $typeName,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'krs_start' => $this->dateWithinAcademicYear($r['tgl_mulai_krs'] ?? null, $yearStart, $yearEnd),
                'krs_end' => $this->dateWithinAcademicYear($r['tgl_selesai_krs'] ?? null, $yearStart, $yearEnd),
                'is_active' => $this->toBoolean($r['is_aktif'] ?? null, false),
            ];

            if (! $this->dryRun) {
                if ($existing) {
                    $existing->update($this->onlyEmptyFields($existing, $semesterData));
                    $s['updated']++;
                } else {
                    Semester::create($semesterData);
                    $s['inserted']++;
                }
            } else {
                $this->line("  [DRY] Semester: {$semesterName} → ".($existing ? 'UPDATE' : 'INSERT'));
                $existing ? $s['updated']++ : $s['inserted']++;
            }
        }
        $this->line("  ✓ {$s['inserted']} insert, {$s['updated']} update, {$s['skipped']} skip");
    }

    /** @return array{int, string}|null */
    private function decodeSemesterCode(string $code): ?array
    {
        if (! preg_match('/^(\d{4})([123])$/', $code, $match)) {
            return null;
        }

        $type = match ($match[2]) {
            '1' => 'Ganjil',
            '2' => 'Genap',
            '3' => 'Pendek',
        };

        return [(int) $match[1], $type];
    }

    /** @return array{string, string} */
    private function defaultSemesterDates(int $yearStart, int $yearEnd, string $type): array
    {
        return match ($type) {
            'Ganjil' => ["{$yearStart}-09-01", "{$yearEnd}-01-31"],
            'Genap' => ["{$yearEnd}-02-01", "{$yearEnd}-08-31"],
            'Pendek' => ["{$yearEnd}-07-01", "{$yearEnd}-08-31"],
        };
    }

    private function dateWithinAcademicYear(mixed $value, int $yearStart, int $yearEnd): ?string
    {
        $date = $this->validDate($value);
        if (! $date) {
            return null;
        }

        return $date >= "{$yearStart}-07-01" && $date <= "{$yearEnd}-09-30" ? $date : null;
    }
}
