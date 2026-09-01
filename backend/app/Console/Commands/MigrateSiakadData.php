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
    private \PDO $siakad;
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

        if ($this->dryRun) {
            $this->warn('🔍 MODE DRY-RUN: tidak ada data yang diubah.');
        }

        $this->info("📂 Membaca SQL dump: {$source}");

        // Load SQL into temp SQLite or parse directly
        try {
            $this->info('⏳ Parsing SQL dump...');
            $this->siakad = $this->parseSqlDump($source);
        } catch (\Exception $e) {
            $this->error("Gagal parse SQL: " . $e->getMessage());
            return 1;
        }

        $this->info('✅ SQL dump berhasil diparse.');

        DB::transaction(function () use ($table) {
            if ($table === 'all' || $table === 'faculties')      $this->migrateFaculties();
            if ($table === 'all' || $table === 'study_programs') $this->migrateStudyPrograms();
            if ($table === 'all' || $table === 'lecturers')      $this->migrateLecturers();
            if ($table === 'all' || $table === 'students')       $this->migrateStudents();
            if ($table === 'all' || $table === 'semesters')      $this->migrateSemesters();
        });

        $this->newLine();
        $this->info('📊 Hasil Migrasi:');
        $headers = ['Tabel', 'Diproses', 'Diinsert', 'Diupdate', 'Dilewati'];
        $rows = [];
        foreach ($this->stats as $tbl => $s) {
            $rows[] = [$tbl, $s['total'] ?? 0, $s['inserted'] ?? 0, $s['updated'] ?? 0, $s['skipped'] ?? 0];
        }
        $this->table($headers, $rows);

        if ($this->dryRun) {
            $this->warn('DRY-RUN selesai. Tidak ada data yang diubah. Jalankan tanpa --dry-run untuk eksekusi.');
        } else {
            $this->info('✅ Migrasi selesai!');
        }
        return 0;
    }

    private function parseSqlDump(string $path): \PDO
    {
        // Buat SQLite in-memory dari SQL dump
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $sql = file_get_contents($path);

        // Bersihkan MySQL-specific syntax yang tidak kompatibel dengan SQLite
        $sql = preg_replace('/ENGINE=\w+[^;]*/i', '', $sql);
        $sql = preg_replace('/DEFAULT CHARSET=\w+[^;]*/i', '', $sql);
        $sql = preg_replace('/COLLATE[= ]\w+/i', '', $sql);
        $sql = preg_replace('/AUTO_INCREMENT=\d+/i', '', $sql);
        $sql = preg_replace('/\bint\(\d+\)/i', 'integer', $sql);
        $sql = preg_replace('/\bvarchar\(\d+\)/i', 'text', $sql);
        $sql = preg_replace('/\bdecimal\([^)]+\)/i', 'real', $sql);
        $sql = preg_replace('/\btinyint\(\d+\)/i', 'integer', $sql);
        $sql = preg_replace('/\btext\b/i', 'text', $sql);
        $sql = preg_replace('/\blongtext\b/i', 'text', $sql);
        $sql = preg_replace('/\bdatetime\b/i', 'text', $sql);
        $sql = preg_replace('/\btimestamp\b/i', 'text', $sql);
        $sql = preg_replace('/\bdate\b/i', 'text', $sql);
        $sql = preg_replace('/UNSIGNED/i', '', $sql);
        $sql = preg_replace('/NOT NULL DEFAULT \'\'/i', "DEFAULT ''", $sql);
        $sql = preg_replace('/SET @[^;]+;/i', '', $sql);
        $sql = preg_replace('/\/\*![^*]+\*\/;?/i', '', $sql);
        $sql = preg_replace('/DELIMITER \$\$.*?DELIMITER ;/s', '', $sql);
        $sql = preg_replace('/CREATE (DEFINER|ALGORITHM)[^;]+;/s', '', $sql);
        $sql = preg_replace('/CREATE\s+(OR REPLACE\s+)?VIEW[^;]+;/si', '', $sql);
        $sql = preg_replace('/`/i', '"', $sql);
        $sql = preg_replace('/START TRANSACTION;/i', 'BEGIN;', $sql);
        $sql = preg_replace('/LOCK TABLES[^;]+;/i', '', $sql);
        $sql = preg_replace('/UNLOCK TABLES;/i', '', $sql);
        $sql = preg_replace('/KEY "[^"]*"[^,)]+/i', '', $sql);
        $sql = preg_replace('/UNIQUE KEY[^,)]+/i', '', $sql);
        $sql = preg_replace('/PRIMARY KEY\s*\(([^)]+)\)/i', 'PRIMARY KEY ($1)', $sql);
        $sql = preg_replace('/,\s*\)/i', ')', $sql);

        // Split dan jalankan per statement
        $statements = array_filter(array_map('trim', explode(";\n", $sql)));
        $count = 0;
        foreach ($statements as $stmt) {
            if (empty($stmt) || str_starts_with(trim($stmt), '--')) continue;
            try {
                $pdo->exec($stmt);
                $count++;
            } catch (\Exception $e) {
                // Skip errors (incompatible statements)
            }
        }
        $this->line("  → {$count} statements dieksekusi ke SQLite");
        return $pdo;
    }

    private function query(string $sql): array
    {
        try {
            $stmt = $this->siakad->query($sql);
            return $stmt ? $stmt->fetchAll(\PDO::FETCH_ASSOC) : [];
        } catch (\Exception $e) {
            $this->warn("Query error: " . $e->getMessage());
            return [];
        }
    }

    private function migrateFaculties(): void
    {
        $this->info('📚 Migrasi Fakultas...');
        $rows = $this->query('SELECT * FROM "fakultas"');
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
        $this->info('🎓 Migrasi Program Studi...');
        $rows = $this->query('SELECT j.*, f.kode_fak FROM "jurusan" j LEFT JOIN "fakultas" f ON j.fak_kode = f.kode_fak');
        $s = &$this->stats['Program Studi'];
        $s = ['total' => count($rows), 'inserted' => 0, 'updated' => 0, 'skipped' => 0];

        foreach ($rows as $r) {
            $code = trim($r['kode_jur'] ?? '');
            $name = trim($r['nama_jur'] ?? '');
            if (!$code || !$name) { $s['skipped']++; continue; }

            $facultyId = Faculty::where('code', $r['kode_fak'] ?? '')->value('id');
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
        $this->info('👨‍🏫 Migrasi Dosen...');
        $rows = $this->query('SELECT * FROM "dosen" WHERE "aktif" = 1 OR "aktif" = "1"');
        if (empty($rows)) $rows = $this->query('SELECT * FROM "dosen"');
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
        $this->info('👩‍🎓 Migrasi Mahasiswa...');
        $rows = $this->query('SELECT * FROM "mahasiswa"');
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
        $rows = $this->query('SELECT * FROM "periode_semester" ORDER BY "thn_akademik" DESC, "kode_jns_smt" ASC');
        if (empty($rows)) $rows = $this->query('SELECT * FROM "semester" ORDER BY "tahun" DESC');
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
