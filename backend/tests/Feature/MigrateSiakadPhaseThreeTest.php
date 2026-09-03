<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Institution;
use App\Models\Lecturer;
use App\Models\LegacyMigrationMap;
use App\Models\Semester;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MigrateSiakadPhaseThreeTest extends TestCase
{
    private string $sqlPath;

    private string $reportPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createMinimalSchema();

        $institution = Institution::create(['code' => 'ASC', 'name' => 'ASC Test']);
        DB::table('academic_years')->insert(['id' => 1, 'name' => 'Tahun Akademik 2020/2021']);
        $semester = Semester::create([
            'academic_year_id' => 1, 'name' => 'Ganjil 2020/2021', 'type' => 'Ganjil',
            'start_date' => '2020-08-01', 'end_date' => '2021-01-31', 'is_active' => false,
        ]);
        $course = Course::create([
            'study_program_id' => 1, 'code' => 'MK1', 'name' => 'Mata Kuliah 1',
            'credits' => 2, 'semester' => 1, 'type' => 'Wajib', 'status' => true,
        ]);
        $lecturer = Lecturer::create([
            'study_program_id' => 1, 'nip' => '99001', 'full_name' => 'Dosen Satu', 'status' => true,
        ]);
        LegacyMigrationMap::create([
            'source_system' => 'siakad', 'entity' => 'course', 'source_id' => '10',
            'target_table' => 'courses', 'target_id' => $course->id,
        ]);

        $this->assertNotNull($institution);
        $this->assertNotNull($semester);
        $this->assertNotNull($lecturer);

        $this->sqlPath = tempnam(sys_get_temp_dir(), 'asc-phase3-');
        $this->reportPath = tempnam(sys_get_temp_dir(), 'asc-phase3-report-');
        file_put_contents($this->sqlPath, <<<'SQL'
INSERT INTO `gedung_ref` (`gedung_id`, `kode_fak`, `kode_gedung`, `nm_gedung`, `is_aktif`) VALUES
(23, NULL, 'GA', 'Gedung A', 'Y');
INSERT INTO `ruang_ref` (`ruang_id`, `gedung_id`, `kode_ruang`, `kode_jur`, `nm_ruang`, `kapasitas`, `fasilitas`, `luas`, `is_aktif`, `ket`) VALUES
(100, '23', 'R1', NULL, 'Ruang Satu', NULL, 'Proyektor', NULL, 'Y', 'KAP 30'),
(101, '28', 'R1', NULL, 'Ruang Duplikat', 20, NULL, NULL, 'Y', NULL);
INSERT INTO `kelas` (`kelas_id`, `sem_id`, `id_matkul`, `id_matkul_setara`, `kls_nama`, `peserta_max`, `peserta_min`, `id_jenis_kelas`, `is_open`, `catatan`) VALUES
(500, 20201, 10, NULL, 'A', 30, 5, 1, 'Y', NULL),
(501, 20201, 10, NULL, 'A', 25, 5, 1, 'Y', NULL);
INSERT INTO `dosen_kelas` (`id_kelas`, `id_dosen`, `dosen_ke`, `jml_tm_renc`, `jml_tm_real`, `id_jns_eval`, `dapat_input`, `sks_ajar`) VALUES
(500, '99001', 1, 16, 14, 1, 'Y', '2');
INSERT INTO `dosen` (`id_dosen`, `kode_jur`, `id_status`, `kode_rumpun`, `id_agama`, `nip`, `nidn`, `nik`, `nama_dosen`, `email`, `gelar_depan`, `gelar_belakang`, `bidang_ilmu`, `jenjang_tertinggi`, `tgl_lahir`, `tmpt_lahir`, `jk`, `alamat`, `no_hp`, `foto`, `aktif`) VALUES
(1, NULL, NULL, NULL, NULL, '99001', '12345', NULL, 'Dosen Satu', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Y');
INSERT INTO `jadwal_kuliah` (`jadwal_id`, `kelas_id`, `hari`, `ruang_id`, `jam_mulai`, `jam_selesai`) VALUES
(700, 500, 'AHAD', 100, '08:00:00', '09:30:00'),
(701, 501, 'Senin', 101, '10:00:00', '11:30:00'),
(702, 500, '', 100, NULL, NULL);
SQL);
    }

    protected function tearDown(): void
    {
        foreach ([$this->sqlPath ?? null, $this->reportPath ?? null] as $path) {
            if ($path && file_exists($path)) {
                unlink($path);
            }
        }
        parent::tearDown();
    }

    public function test_dry_run_real_run_and_rerun_are_safe(): void
    {
        $arguments = [
            '--source' => $this->sqlPath,
            '--table' => 'all',
            '--report' => $this->reportPath,
        ];

        $this->assertSame(0, Artisan::call('siakad:migrate-phase3', $arguments + ['--dry-run' => true]));
        $this->assertDatabaseCount('buildings', 0);
        $this->assertDatabaseCount('rooms', 0);
        $this->assertDatabaseCount('classes', 0);
        $this->assertDatabaseCount('schedules', 0);
        $this->assertDatabaseCount('legacy_migration_maps', 1);
        $report = file_get_contents($this->reportPath);
        $this->assertStringContainsString('SYNTHETIC_PARENT', $report);
        $this->assertStringContainsString('DUPLICATE_CODE_ALIASED', $report);
        $this->assertStringContainsString('DUPLICATE_CLASS_ALIASED', $report);
        $this->assertStringContainsString('CLASS_WITHOUT_LECTURER', $report);

        $this->assertSame(0, Artisan::call('siakad:migrate-phase3', $arguments));
        $this->assertDatabaseCount('buildings', 2);
        $this->assertDatabaseCount('rooms', 2);
        $this->assertDatabaseCount('classes', 2);
        $this->assertDatabaseCount('class_lecturers', 1);
        $this->assertDatabaseCount('schedules', 2);
        $this->assertDatabaseHas('schedules', ['day' => 'Minggu', 'start_time' => '08:00:00']);
        $this->assertDatabaseHas('rooms', ['code' => 'R1', 'capacity' => 30]);
        $this->assertSame(2, DB::table('classes')->distinct()->count('name'));

        $classId = LegacyMigrationMap::where(['entity' => 'class', 'source_id' => '500'])->value('target_id');
        DB::table('classes')->where('id', $classId)->update(['name' => 'Nama Manual']);

        $this->assertSame(0, Artisan::call('siakad:migrate-phase3', $arguments));
        $this->assertDatabaseCount('buildings', 2);
        $this->assertDatabaseCount('rooms', 2);
        $this->assertDatabaseCount('classes', 2);
        $this->assertDatabaseCount('class_lecturers', 1);
        $this->assertDatabaseCount('schedules', 2);
        $this->assertDatabaseHas('classes', ['id' => $classId, 'name' => 'Nama Manual']);
    }

    private function createMinimalSchema(): void
    {
        Schema::create('institutions', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->timestamps();
        });
        Schema::create('academic_years', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
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
        Schema::create('courses', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('study_program_id');
            $table->string('code')->unique();
            $table->string('name');
            $table->integer('credits');
            $table->integer('semester');
            $table->string('type');
            $table->boolean('status');
            $table->timestamps();
        });
        Schema::create('lecturers', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('study_program_id')->nullable();
            $table->string('nidn')->nullable();
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
            $table->string('employment_status')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
        });
        Schema::create('buildings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('institution_id');
            $table->string('code')->unique();
            $table->string('name');
            $table->integer('floors')->default(1);
            $table->text('address')->nullable();
            $table->boolean('status');
            $table->timestamps();
        });
        Schema::create('rooms', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('building_id');
            $table->string('code')->unique();
            $table->string('name');
            $table->integer('floor');
            $table->integer('capacity');
            $table->string('type');
            $table->json('facilities')->nullable();
            $table->boolean('status');
            $table->timestamps();
        });
        Schema::create('classes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('study_program_id');
            $table->unsignedBigInteger('semester_id');
            $table->unsignedBigInteger('course_id');
            $table->unsignedBigInteger('lecturer_id')->nullable();
            $table->unsignedBigInteger('room_id')->nullable();
            $table->string('name');
            $table->integer('capacity');
            $table->integer('academic_level');
            $table->boolean('is_active');
            $table->timestamps();
            $table->unique(['semester_id', 'course_id', 'name']);
        });
        Schema::create('class_lecturers', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('class_id');
            $table->unsignedBigInteger('lecturer_id');
            $table->integer('teaching_order');
            $table->integer('planned_meetings')->nullable();
            $table->integer('actual_meetings')->nullable();
            $table->boolean('can_input_grades');
            $table->decimal('teaching_credits', 5, 2)->nullable();
            $table->timestamps();
            $table->unique(['class_id', 'lecturer_id']);
        });
        Schema::create('schedules', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('class_id');
            $table->string('day');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedBigInteger('room_id')->nullable();
            $table->unsignedBigInteger('lecturer_id')->nullable();
            $table->string('note')->nullable();
            $table->boolean('is_active');
            $table->timestamps();
        });
        Schema::create('legacy_migration_maps', function (Blueprint $table): void {
            $table->id();
            $table->string('source_system')->default('siakad');
            $table->string('entity');
            $table->string('source_id');
            $table->string('target_table');
            $table->unsignedBigInteger('target_id');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['source_system', 'entity', 'source_id']);
        });
    }
}
