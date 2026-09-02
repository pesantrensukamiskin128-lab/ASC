<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\Faculty;
use App\Models\StudyProgram;
use App\Models\Lecturer;
use App\Models\Student;
use App\Models\User;
use App\Models\AcademicYear;
use App\Models\Semester;

class MigrateSiakadData extends Command
{
    protected $signature = 'siakad:migrate
        {--source= : Path ke file SQL dump SIAKAD}
        {--dry-run : Simulasi saja, tidak ubah data}
        {--table=all : Tabel yang dimigrasi: all, faculties, study_programs, lecturers, students, semesters, courses}';

    protected $description = 'Migrasi data dari SQL dump SIAKAD lama ke ASC. Menggunakan UPSERT — data yang sudah ada tidak dihapus.';

    private bool $dryRun = false;
    private string $sqlPath = '';
    private array $stats = [];

    public function handle(): int
    {
        $this->dryRun = $this->option('dry-run');
        $source = $this->option('source') ?? base_path('../_referensi/siakadstai_siakad.sql');
        $table = $this->option('table');

        if (!file_exists($source)) {
            $this->error("File tidak ditemukan: {$source}");
            return 1;
        }

        $this->sqlPath = $source;

        if ($this->dryRun) {
            $this->warn('MODE DRY-RUN: tidak ada data yang diubah.');
        }

        $this->info("Membaca SQL dump: {$source}");
        $sizeMb = round(filesize($source) / 1048576, 1);
        $this->info("Ukuran file: {$sizeMb} MB");

        DB::transaction(function () use ($table) {
            if ($table === 'all' || $table === 'faculties')      $this->migrateFaculties();
            if ($table === 'all' || $table === 'study_programs') $this->migrateStudyPrograms();
            if ($table === 'all' || $table === 'lecturers')      $this->migrateLecturers();
            if ($table === 'all' || $table === 'students')       $this->migrateStudents();
            if ($table === 'all' || $table === 'semesters')      $this->migrateSemesters();
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
        $results = [];
        $handle = fopen($this->sqlPath, 'r');
        if (!$handle) return [];

        $columns = [];
        $inTable = false;

        while (($line = fgets($handle)) !== false) {
            $line = rtrim($line);

            // Cari definisi kolom dari CREATE TABLE
            if (!$inTable && preg_match('/^CREATE TABLE `?' . preg_quote($tableName, '/') . '`?\s*\(/i', $line)) {
                $inTable = true;
                continue;
            }

            if ($inTable) {
                // Ambil nama kolom
                if (preg_match('/^\s+`([^`]+)`\s+/i', $line, $m)) {
                    // Skip KEY dan INDEX lines
                    if (!preg_match('/^\s+(PRIMARY|UNIQUE|KEY|INDEX)\s/i', $line)) {
                        $columns[] = $m[1];
                    }
                }
                // Tutup CREATE TABLE
                if (preg_match('/^\)\s*(ENGINE|;)/i', $line) || $line === ');') {
                    $inTable = false;
                }
                continue;
            }

            // Parse INSERT INTO `tableName` VALUES (...)
            if (preg_match('/^INSERT INTO `?' . preg_quote($tableName, '/') . '`?\s*(?:\([^)]+\)\s*)?VALUES\s*/i', $line)) {
                // Ambil nama kolom dari INSERT jika ada
                if (preg_match('/INSERT INTO `[^`]+`\s*\(([^)]+)\)\s*VALUES/i', $line, $colMatch)) {
                    $columns = array_map(fn($c) => trim(trim($c), '`'), explode(',', $colMatch[1]));
                }

                // Ekstrak semua VALUE tuples dari baris ini (bisa multi-row insert)
                $valuesPart = preg_replace('/^INSERT INTO[^V]+VALUES\s*/i', '', $line);
                $tuples = $this->extractTuples($valuesPart);

                foreach ($tuples as $values) {
                    if (count($values) === count($columns)) {
                        $results[] = array_combine($columns, $values);
                    }
                }
            }
        }

        fclose($handle);
        $this->line("  Ditemukan " . count($results) . " baris dari tabel '{$tableName}'");
        return $results;
    }

    /**
     * Ekstrak array of value tuples dari string VALUES (v1,v2),(v3,v4),...
     */
    private function extractTuples(string $valuesStr): array
    {
        $tuples = [];
        $valuesStr = rtrim(trim($valuesStr), ';');

        // Pakai regex untuk ekstrak setiap tuple (...)
        preg_match_all('/\(([^()]*(?:\([^()]*\)[^()]*)*)\)/s', $valuesStr, $matches);

        foreach ($matches[1] as $tuple) {
            $values = [];
            // Parse nilai dengan memperhatikan string quoted
            $pattern = "/(?:'(?:[^'\\\\]|\\\\.)*'|NULL|\\d+(?:\\.\\d+)?|[^,]+)/";
            preg_match_all($pattern, $tuple, $valMatches);

            foreach ($valMatches[0] as $val) {
                $val = trim($val);
                if (strtoupper($val) === 'NULL') {
                    $values[] = null;
                } elseif (str_starts_with($val, "'") && str_ends_with($val, "'")) {
                    $values[] = stripslashes(substr($val, 1, -1));
                } else {
                    $values[] = $val;
                }
            }
            if (!empty($values)) $tuples[] = $values;
        }
        return $tuples;
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
            if (!$code || !$name) { $s['skipped']++; continue; }

            $existing = Faculty::where('code', $code)->first();
            if (!$this->dryRun) {
                if ($existing) {
                    // Update hanya field yang kosong di ASC
                    $existing->update(array_filter([
                        'name' => $existing->name ?: $name,
                        'short_name' => $existing->short_name ?: ($r['nama_singkat'] ?? null),
                    ]));
                    $s['updated']++;
                } else {
                    Faculty::create(['code' => $code, 'name' => $name, 'short_name' => $r['nama_singkat'] ?? null, 'status' => true]);
                    $s['inserted']++;
                }
            } else {
                $this->line("  [DRY] Fakultas: {$code} - {$name} → " . ($existing ? 'UPDATE' : 'INSERT'));
                $existing ? $s['updated']++ : $s['inserted']++;
            }
        }
        $this->line("  ✓ {$s['inserted']} insert, {$s['updated']} update, {$s['skipped']} skip");
    }

    private function migrateStudyPrograms(): void
    {
        $this->info('Migrasi Program Studi...');
        $rows = $this->parseTable('jurusan');
        $fakultasRows = $this->parseTable('fakultas');
        // Build lookup kode_jur -> kode_fak
        $jurFakMap = [];
        foreach ($rows as $r) {
            $jurFakMap[trim($r['kode_jur'] ?? '')] = trim($r['fak_kode'] ?? '');
        }
        $s = &$this->stats['Program Studi'];
        $s = ['total' => count($rows), 'inserted' => 0, 'updated' => 0, 'skipped' => 0];

        foreach ($rows as $r) {
            $code = trim($r['kode_jur'] ?? '');
            $name = trim($r['nama_jur'] ?? '');
            if (!$code || !$name) { $s['skipped']++; continue; }

            $facultyId = Faculty::where('code', $r['fak_kode'] ?? '')->value('id');
            $existing = StudyProgram::where('code', $code)->first();

            if (!$this->dryRun) {
                if ($existing) {
                    $existing->update(array_filter([
                        'name' => $existing->name ?: $name,
                        'faculty_id' => $existing->faculty_id ?: $facultyId,
                    ]));
                    $s['updated']++;
                } else {
                    StudyProgram::create([
                        'code' => $code, 'name' => $name,
                        'faculty_id' => $facultyId,
                        'status' => ($r['status'] ?? '1') == '1',
                    ]);
                    $s['inserted']++;
                }
            } else {
                $this->line("  [DRY] Prodi: {$code} - {$name} → " . ($existing ? 'UPDATE' : 'INSERT'));
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
            if (!$name) { $s['skipped']++; continue; }

            $email = trim($r['email'] ?? '') ?: ($nidn ? "{$nidn}@dosen.stai-aljawami.ac.id" : null);
            $prodiCode = trim($r['kode_jur'] ?? '');
            $prodiId = $prodiCode ? StudyProgram::where('code', $prodiCode)->value('id') : null;

            // Find existing by nidn or nip
            $existing = null;
            if ($nidn) $existing = Lecturer::where('nidn', $nidn)->first();
            if (!$existing && ($r['nip'] ?? '')) $existing = Lecturer::where('nip', $r['nip'])->first();

            $genderMap = ['L' => 'L', 'P' => 'P', 'l' => 'L', 'p' => 'P', '1' => 'L', '2' => 'P'];
            $gender = $genderMap[strtolower(trim($r['jk'] ?? ''))] ?? null;

            $birthDate = null;
            if (!empty($r['tgl_lahir'])) {
                try { $birthDate = \Carbon\Carbon::parse($r['tgl_lahir'])->format('Y-m-d'); } catch (\Exception) {}
            }

            $lecturerData = array_filter([
                'study_program_id' => $prodiId,
                'nidn' => $nidn ?: null,
                'nip' => trim($r['nip'] ?? '') ?: null,
                'full_name' => $name,
                'degree_front' => trim($r['gelar_depan'] ?? '') ?: null,
                'degree_back' => trim($r['gelar_belakang'] ?? '') ?: null,
                'gender' => $gender,
                'birth_place' => trim($r['tmpt_lahir'] ?? '') ?: null,
                'birth_date' => $birthDate,
                'email' => $email,
                'phone' => trim($r['no_hp'] ?? '') ?: null,
                'address' => trim($r['alamat'] ?? '') ?: null,
                'status' => true,
            ], fn($v) => $v !== null && $v !== '');

            if (!$this->dryRun) {
                if ($existing) {
                    // Only fill empty fields
                    $fillable = [];
                    foreach ($lecturerData as $key => $val) {
                        if (empty($existing->{$key})) $fillable[$key] = $val;
                    }
                    if ($fillable) $existing->update($fillable);
                    $s['updated']++;
                } else {
                    $lecturer = Lecturer::create($lecturerData);
                    // Buat akun user
                    $username = $nidn ?: ('dosen-' . $lecturer->id);
                    $user = User::firstOrCreate(
                        ['username' => $username],
                        ['name' => $name, 'email' => $email ?? "{$username}@dosen.stai-aljawami.ac.id", 'password' => Hash::make($username)]
                    );
                    if ($user->wasRecentlyCreated) $user->assignRole('DOSEN');
                    $lecturer->update(['user_id' => $user->id]);
                    $s['inserted']++;
                }
            } else {
                $this->line("  [DRY] Dosen: {$nidn} - {$name} → " . ($existing ? 'UPDATE' : 'INSERT'));
                $existing ? $s['updated']++ : $s['inserted']++;
            }
        }
        $this->line("  ✓ {$s['inserted']} insert, {$s['updated']} update, {$s['skipped']} skip");
    }

    private function migrateStudents(): void
    {
        $this->info('Migrasi Mahasiswa...');
        $rows = $this->parseTable('mahasiswa');
        $s = &$this->stats['Mahasiswa'];
        $s = ['total' => count($rows), 'inserted' => 0, 'updated' => 0, 'skipped' => 0];

        // Status mapping dari SIAKAD ke ASC
        $statusMap = [
            'A' => 'Aktif', '1' => 'Aktif', 'Aktif' => 'Aktif',
            'C' => 'Cuti', 'D' => 'DO', 'L' => 'Lulus',
            'K' => 'Mengundurkan Diri', 'N' => 'Lulus',
        ];

        foreach ($rows as $r) {
            $nim = trim($r['nim'] ?? '');
            $name = trim($r['nama'] ?? '');
            if (!$nim || !$name) { $s['skipped']++; continue; }

            $email = trim($r['email'] ?? '') ?: "{$nim}@student.stai-aljawami.ac.id";
            $prodiCode = trim($r['jur_kode'] ?? '');
            $prodiId = $prodiCode ? StudyProgram::where('code', $prodiCode)->value('id') : null;

            // Advisor by nidn
            $advisorNidn = trim($r['nird'] ?? $r['nidn'] ?? '');
            $advisorId = $advisorNidn ? Lecturer::where('nidn', $advisorNidn)->value('id') : null;

            $genderMap = ['L' => 'L', 'P' => 'P', 'l' => 'L', 'p' => 'P', '1' => 'L', '2' => 'P'];
            $gender = $genderMap[strtolower(trim($r['jk'] ?? ''))] ?? null;

            $birthDate = null;
            if (!empty($r['tgl_lahir'])) {
                try { $birthDate = \Carbon\Carbon::parse($r['tgl_lahir'])->format('Y-m-d'); } catch (\Exception) {}
            }

            // Entry year from mulai_smt (e.g. 20201 → 2020)
            $entryYear = null;
            if (!empty($r['mulai_smt'])) {
                $entryYear = (int) substr((string)$r['mulai_smt'], 0, 4);
            }

            $rawStatus = strtoupper(trim($r['stat_pd'] ?? 'A'));
            $status = $statusMap[$rawStatus] ?? 'Aktif';

            $studentData = array_filter([
                'study_program_id' => $prodiId,
                'advisor_id' => $advisorId,
                'nim' => $nim,
                'name' => $name,
                'gender' => $gender,
                'birth_place' => trim($r['tmpt_lahir'] ?? '') ?: null,
                'birth_date' => $birthDate,
                'email' => $email,
                'phone' => trim($r['telepon_seluler'] ?? '') ?: null,
                'address' => trim($r['jln'] ?? '') ?: null,
                'entry_year' => $entryYear,
                'status' => $status,
            ], fn($v) => $v !== null && $v !== '');

            $existing = Student::where('nim', $nim)->first();

            if (!$this->dryRun) {
                if ($existing) {
                    // Only fill empty fields
                    $fillable = [];
                    foreach ($studentData as $key => $val) {
                        if (empty($existing->{$key})) $fillable[$key] = $val;
                    }
                    if ($fillable) $existing->update($fillable);
                    // Link user if not linked
                    if (!$existing->user_id) {
                        $user = User::where('username', $nim)->first();
                        if ($user) $existing->update(['user_id' => $user->id]);
                    }
                    $s['updated']++;
                } else {
                    $student = Student::create($studentData);
                    $user = User::firstOrCreate(
                        ['username' => $nim],
                        ['name' => $name, 'email' => $email, 'password' => Hash::make($nim)]
                    );
                    if ($user->wasRecentlyCreated) $user->assignRole('MAHASISWA');
                    $student->update(['user_id' => $user->id]);
                    $s['inserted']++;
                }
            } else {
                $this->line("  [DRY] Mahasiswa: {$nim} - {$name} → " . ($existing ? 'UPDATE' : 'INSERT'));
                $existing ? $s['updated']++ : $s['inserted']++;
            }
        }
        $this->line("  ✓ {$s['inserted']} insert, {$s['updated']} update, {$s['skipped']} skip");
    }

    private function migrateSemesters(): void
    {
        $this->info('📅 Migrasi Semester...');
        $rows = $this->parseTable('periode_semester');
        if (empty($rows)) $rows = $this->parseTable('semester');
        $s = &$this->stats['Semester'];
        $s = ['total' => count($rows), 'inserted' => 0, 'updated' => 0, 'skipped' => 0];

        foreach ($rows as $r) {
            // Try to build semester name
            $year = $r['thn_akademik'] ?? $r['tahun'] ?? null;
            $type = $r['kode_jns_smt'] ?? $r['jenis'] ?? null;
            if (!$year) { $s['skipped']++; continue; }

            $typeName = ($type == '1' || $type == 'Ganjil') ? 'Ganjil' : 'Genap';
            $yearParts = explode('/', (string) $year);
            $yearEnd = count($yearParts) > 1 ? $yearParts[1] : (((int) $yearParts[0]) + 1);
            $yearStart = $yearParts[0];
            $semName = "Semester {$typeName} {$yearStart}/{$yearEnd}";

            // Check academic year
            $ayName = "{$yearStart}/{$yearEnd}";
            $ay = AcademicYear::firstOrCreate(['name' => $ayName], ['start_year' => (int)$yearStart, 'end_year' => (int)$yearEnd, 'is_active' => false]);

            $existing = Semester::where('name', $semName)->first();
            $isActive = ($r['status'] ?? $r['aktif'] ?? '0') == '1';

            if (!$this->dryRun) {
                if (!$existing) {
                    Semester::create(['name' => $semName, 'academic_year_id' => $ay->id, 'type' => $typeName, 'is_active' => $isActive]);
                    $s['inserted']++;
                } else {
                    $s['updated']++;
                }
            } else {
                $this->line("  [DRY] Semester: {$semName} → " . ($existing ? 'UPDATE' : 'INSERT'));
                $existing ? $s['updated']++ : $s['inserted']++;
            }
        }
        $this->line("  ✓ {$s['inserted']} insert, {$s['updated']} update, {$s['skipped']} skip");
    }
}


