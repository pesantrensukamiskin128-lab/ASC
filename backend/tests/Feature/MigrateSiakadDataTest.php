<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Faculty;
use App\Models\Institution;
use App\Models\Lecturer;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudyProgram;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MigrateSiakadDataTest extends TestCase
{
    private string $sqlPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createMinimalSchema();
        Institution::create(['code' => 'TEST', 'name' => 'Test Campus']);
        Role::create(['name' => 'DOSEN', 'guard_name' => 'web']);
        Role::create(['name' => 'MAHASISWA', 'guard_name' => 'web']);

        $this->sqlPath = tempnam(sys_get_temp_dir(), 'asc-migration-');
        file_put_contents($this->sqlPath, <<<'SQL'
INSERT INTO `fakultas` (`kode_fak`, `nama_resmi`, `nama_singkat`, `dekan`) VALUES
('FAI', 'Fakultas Agama Islam', 'FAI', 'Dr. Dekan');
INSERT INTO `jenjang` (`idjenjang`, `jenjang`) VALUES
(4, 'S1');
INSERT INTO `jurusan` (`kode_jur`, `id_jenjang`, `fak_kode`, `status`, `nama_jur`) VALUES
('PAI', 4, 'FAI', 1, 'Pendidikan Agama Islam');
INSERT INTO `semester` (`sem_id`, `kode_jur`, `id_semester`, `is_aktif`, `tgl_mulai`, `tgl_selesai`, `tgl_mulai_krs`, `tgl_selesai_krs`) VALUES
(1, 'PAI', 20251, 1, '2025-09-01', '2026-01-31', '2025-08-01', '2025-08-31'),
(2, 'PBA', 20251, 1, '2025-09-01', '2026-01-31', '2025-08-01', '2025-08-31');
INSERT INTO `dosen` (`id_dosen`, `kode_jur`, `nip`, `nidn`, `nama_dosen`, `email`, `jk`, `aktif`) VALUES
(10, 'PAI', '198001', '040001', 'Dosen Wali', 'wali@example.test', 'L', 1);
INSERT INTO `agama` (`id_agama`, `nm_agama`) VALUES
(1, 'Islam');
INSERT INTO `mahasiswa` (`nim`, `nama`, `jur_kode`, `mulai_smt`, `jk`, `email`, `stat_pd`, `dosen_pemb`, `id_agama`, `nik`, `nisn`, `jln`, `rt`, `rw`, `ds_kel`, `kode_pos`) VALUES
('2025001', 'Mahasiswa Uji', 'PAI', 20251, 'P', 'student@example.test', 'N', 10, 1, '123456', '987654', 'Jalan Uji', '01', '02', 'Desa Uji', '40100');
SQL);
    }

    protected function tearDown(): void
    {
        if (isset($this->sqlPath) && file_exists($this->sqlPath)) {
            unlink($this->sqlPath);
        }

        parent::tearDown();
    }

    private function createMinimalSchema(): void
    {
        Schema::create('institutions', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->timestamps();
        });
        Schema::create('faculties', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('institution_id');
            $table->string('code')->unique();
            $table->string('name');
            $table->string('dean_name')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
        });
        Schema::create('study_programs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('faculty_id');
            $table->string('code')->unique();
            $table->string('name');
            $table->string('degree')->nullable();
            $table->string('level')->nullable();
            $table->string('accreditation')->nullable();
            $table->unsignedBigInteger('head_lecturer_id')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
        });
        Schema::create('academic_years', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });
        Schema::create('semesters', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('academic_year_id');
            $table->string('name');
            $table->string('type');
            $table->date('start_date');
            $table->date('end_date');
            $table->date('krs_start')->nullable();
            $table->date('krs_end')->nullable();
            $table->date('exam_mid_start')->nullable();
            $table->date('exam_mid_end')->nullable();
            $table->date('exam_final_start')->nullable();
            $table->date('exam_final_end')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('username')->nullable()->unique();
            $table->string('password');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
        });
        Schema::create('lecturers', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('study_program_id')->nullable();
            $table->string('nidn')->nullable()->unique();
            $table->string('nuptk')->nullable();
            $table->string('nip')->nullable();
            $table->string('degree_front')->nullable();
            $table->string('degree_back')->nullable();
            $table->string('full_name');
            $table->string('gender')->nullable();
            $table->string('birth_place')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('photo_path')->nullable();
            $table->text('address')->nullable();
            $table->string('academic_rank')->nullable();
            $table->string('employment_status')->default('Tetap');
            $table->boolean('status')->default(true);
            $table->timestamps();
        });
        Schema::create('students', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('study_program_id');
            $table->unsignedBigInteger('academic_year_id')->nullable();
            $table->unsignedBigInteger('advisor_id')->nullable();
            $table->string('nim')->unique();
            $table->string('name');
            $table->string('gender')->nullable();
            $table->string('birth_place')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->integer('entry_year')->nullable();
            $table->string('status')->default('Aktif');
            $table->integer('current_semester')->default(1);
            $table->timestamps();
        });
        Schema::create('student_profiles', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('student_id')->unique();
            $table->string('religion')->nullable();
            $table->string('nik')->nullable();
            $table->string('nisn')->nullable();
            $table->string('blood_type')->nullable();
            $table->string('marital_status')->nullable();
            $table->string('nationality')->default('Indonesia');
            $table->string('photo_path')->nullable();
            $table->timestamps();
        });
        Schema::create('student_addresses', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->string('type');
            $table->text('address')->nullable();
            $table->string('village')->nullable();
            $table->string('district')->nullable();
            $table->string('city')->nullable();
            $table->string('province')->nullable();
            $table->string('postal_code')->nullable();
            $table->timestamps();
            $table->unique(['student_id', 'type']);
        });
        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });
        Schema::create('model_has_roles', function (Blueprint $table): void {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->primary(['role_id', 'model_id', 'model_type']);
        });
    }

    public function test_dry_run_does_not_write_and_real_run_is_idempotent(): void
    {
        $arguments = [
            '--source' => $this->sqlPath,
            '--table' => 'all',
        ];

        $this->assertSame(0, Artisan::call('siakad:migrate', $arguments + ['--dry-run' => true]));
        $this->assertDatabaseCount('faculties', 0);
        $this->assertDatabaseCount('academic_years', 0);
        $this->assertDatabaseCount('students', 0);

        $this->assertSame(0, Artisan::call('siakad:migrate', $arguments));
        $this->assertDatabaseCount('faculties', 1);
        $this->assertDatabaseCount('study_programs', 1);
        $this->assertDatabaseCount('academic_years', 1);
        $this->assertDatabaseCount('semesters', 1);
        $this->assertDatabaseCount('lecturers', 1);
        $this->assertDatabaseCount('students', 1);

        $faculty = Faculty::firstOrFail();
        $program = StudyProgram::firstOrFail();
        $academicYear = AcademicYear::firstOrFail();
        $semester = Semester::firstOrFail();
        $lecturer = Lecturer::firstOrFail();
        $student = Student::with(['profile', 'domicileAddress', 'user'])->firstOrFail();

        $this->assertSame('Dr. Dekan', $faculty->dean_name);
        $this->assertSame('S1', $program->level);
        $this->assertSame('Tahun Akademik 2025/2026', $academicYear->name);
        $this->assertSame('Ganjil 2025/2026', $semester->name);
        $this->assertSame($lecturer->id, $student->advisor_id);
        $this->assertSame($academicYear->id, $student->academic_year_id);
        $this->assertSame('Nonaktif', $student->status);
        $this->assertSame('Islam', $student->profile->religion);
        $this->assertSame('Desa Uji', $student->domicileAddress->village);
        $this->assertTrue($student->user->hasRole('MAHASISWA'));

        $this->assertSame(0, Artisan::call('siakad:migrate', $arguments));
        $this->assertDatabaseCount('faculties', 1);
        $this->assertDatabaseCount('study_programs', 1);
        $this->assertDatabaseCount('academic_years', 1);
        $this->assertDatabaseCount('semesters', 1);
        $this->assertDatabaseCount('lecturers', 1);
        $this->assertDatabaseCount('students', 1);
    }
}
