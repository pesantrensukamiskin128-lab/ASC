<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\LegacyMigrationMap;
use App\Models\Student;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MigrateSiakadPhaseFourTest extends TestCase
{
    private string $sqlPath;

    private string $reportPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createMinimalSchema();
        $student = Student::create(['study_program_id' => 1, 'nim' => 'M001', 'name' => 'Mahasiswa Satu', 'status' => 'Aktif']);
        $course1 = Course::create(['study_program_id' => 1, 'code' => 'MK1', 'name' => 'MK 1', 'credits' => 2, 'semester' => 1, 'type' => 'Wajib', 'status' => true]);
        $course2 = Course::create(['study_program_id' => 1, 'code' => 'MK2', 'name' => 'MK 2', 'credits' => 3, 'semester' => 1, 'type' => 'Wajib', 'status' => true]);
        DB::table('classes')->insert([
            ['id' => 10, 'study_program_id' => 1, 'semester_id' => 1, 'course_id' => $course1->id, 'name' => 'A'],
            ['id' => 11, 'study_program_id' => 1, 'semester_id' => 1, 'course_id' => $course2->id, 'name' => 'B'],
        ]);
        foreach ([500 => 10, 501 => 11] as $source => $target) {
            LegacyMigrationMap::create([
                'source_system' => 'siakad', 'entity' => 'class', 'source_id' => (string) $source,
                'target_table' => 'classes', 'target_id' => $target,
            ]);
        }

        $this->assertNotNull($student);
        $this->sqlPath = tempnam(sys_get_temp_dir(), 'asc-phase4-');
        $this->reportPath = tempnam(sys_get_temp_dir(), 'asc-phase4-report-');
        file_put_contents($this->sqlPath, <<<'SQL'
INSERT INTO `krs_detail` (`id_krs_detail`, `kode_mk`, `id_kelas`, `nim`, `id_semester`, `disetujui`, `batal`, `sks`, `presensi`, `mandiri`, `terstruktur`, `lain_lain`, `uts`, `uas`, `bobot`, `nilai_huruf`, `nilai_angka`, `tgl_perubahan`, `pengubah`, `mk_disetarakan`, `use_rule`, `sdh_dinilai`) VALUES
(1, 'MK1', 500, 'M001', 20201, '1', 0, 2, NULL, NULL, NULL, NULL, 80, 90, 3.00, 'B', 75, '2020-10-01 10:00:00', NULL, NULL, '1', '1'),
(2, 'MK1', 500, 'M001', 20201, '1', 0, 2, NULL, NULL, NULL, NULL, 90, 90, 4.00, 'A', 85, '2020-11-01 10:00:00', NULL, NULL, '1', '1'),
(3, 'MK2', 501, 'M001', 20201, '0', 0, 3, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2020-11-02 10:00:00', NULL, NULL, '1', '1'),
(4, 'MK2', 501, 'UNKNOWN', 20201, '1', 0, 3, NULL, NULL, NULL, NULL, NULL, NULL, 3.00, 'B', 150, '2020-11-03 10:00:00', NULL, NULL, '1', '1');
INSERT INTO `krs_penilaian` (`id`, `id_krs_detail`, `id_komponen`, `nilai_angka`, `date_created`, `edit_by`) VALUES
(1, 2, 5, '90', '2020-11-01 10:00:00', NULL),
(2, 2, 6, '90', '2020-11-01 10:00:00', NULL);
INSERT INTO `kelas_penilaian` (`id`, `id_kelas`, `id_komponen`, `nilai`) VALUES
(1, 500, 5, 40),
(2, 500, 6, 60);
INSERT INTO `komponen_nilai` (`id`, `nama_komponen`, `wajib`, `isShow`) VALUES
(5, 'UTS', '1', '1'),
(6, 'UAS', '1', '1');
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

    public function test_dry_run_real_run_and_rerun_preserve_existing_grades(): void
    {
        $arguments = ['--source' => $this->sqlPath, '--table' => 'all', '--report' => $this->reportPath];

        $this->assertSame(0, Artisan::call('siakad:migrate-phase4', $arguments + ['--dry-run' => true]));
        $this->assertDatabaseCount('krs', 0);
        $this->assertDatabaseCount('krs_details', 0);
        $this->assertDatabaseCount('class_members', 0);
        $this->assertDatabaseCount('student_grades', 0);
        $report = file_get_contents($this->reportPath);
        $this->assertStringContainsString('DUPLICATE_ATTEMPT_MERGED', $report);
        $this->assertStringContainsString('MISSING_STUDENT', $report);
        $this->assertStringContainsString('GRADE_EMPTY', $report);

        $this->assertSame(0, Artisan::call('siakad:migrate-phase4', $arguments));
        $this->assertDatabaseCount('krs', 1);
        $this->assertDatabaseCount('krs_details', 2);
        $this->assertDatabaseCount('class_members', 2);
        $this->assertDatabaseCount('student_grades', 1);
        $this->assertDatabaseHas('student_grades', ['final_score' => 85, 'letter_grade' => 'A', 'grade_point' => 4]);
        $this->assertDatabaseHas('krs', ['total_credits' => 5, 'status' => 'SUBMITTED']);

        DB::table('student_grades')->update(['letter_grade' => 'A-', 'grade_point' => 3.75]);
        $this->assertSame(0, Artisan::call('siakad:migrate-phase4', $arguments));
        $this->assertDatabaseCount('krs', 1);
        $this->assertDatabaseCount('krs_details', 2);
        $this->assertDatabaseCount('class_members', 2);
        $this->assertDatabaseCount('student_grades', 1);
        $this->assertDatabaseHas('student_grades', ['letter_grade' => 'A-', 'grade_point' => 3.75]);
    }

    private function createMinimalSchema(): void
    {
        Schema::create('students', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('study_program_id');
            $table->unsignedBigInteger('academic_year_id')->nullable();
            $table->unsignedBigInteger('advisor_id')->nullable();
            $table->string('nim')->unique();
            $table->string('name');
            $table->string('status');
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
        Schema::create('classes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('study_program_id');
            $table->unsignedBigInteger('semester_id');
            $table->unsignedBigInteger('course_id');
            $table->unsignedBigInteger('lecturer_id')->nullable();
            $table->unsignedBigInteger('room_id')->nullable();
            $table->string('name');
            $table->integer('capacity')->default(40);
            $table->integer('academic_level')->default(1);
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });
        Schema::create('krs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('semester_id');
            $table->unsignedBigInteger('advisor_id')->nullable();
            $table->integer('total_credits')->default(0);
            $table->string('status');
            $table->text('advisor_note')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->unique(['student_id', 'semester_id']);
        });
        Schema::create('krs_details', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('krs_id');
            $table->unsignedBigInteger('course_id');
            $table->unsignedBigInteger('class_id')->nullable();
            $table->string('status');
            $table->timestamps();
            $table->unique(['krs_id', 'course_id']);
        });
        Schema::create('class_members', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('class_id');
            $table->unsignedBigInteger('student_id');
            $table->timestamps();
            $table->unique(['class_id', 'student_id']);
        });
        Schema::create('student_grades', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('course_id');
            $table->unsignedBigInteger('class_id')->nullable();
            $table->unsignedBigInteger('semester_id');
            $table->json('components')->nullable();
            $table->decimal('final_score', 5, 2)->nullable();
            $table->string('letter_grade')->nullable();
            $table->decimal('grade_point', 3, 2)->nullable();
            $table->unsignedBigInteger('graded_by')->nullable();
            $table->timestamp('graded_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->unique(['student_id', 'course_id', 'semester_id']);
        });
        Schema::create('legacy_migration_maps', function (Blueprint $table): void {
            $table->id();
            $table->string('source_system');
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
