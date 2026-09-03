<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Curriculum;
use App\Models\StudyProgram;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MigrateSiakadPhaseTwoTest extends TestCase
{
    private string $sqlPath;

    private string $reportPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createMinimalSchema();
        StudyProgram::create(['faculty_id' => 1, 'code' => 'P1', 'name' => 'Program Satu']);
        StudyProgram::create(['faculty_id' => 1, 'code' => 'P2', 'name' => 'Program Dua']);

        $this->sqlPath = tempnam(sys_get_temp_dir(), 'asc-phase2-');
        $this->reportPath = tempnam(sys_get_temp_dir(), 'asc-phase2-report-');
        file_put_contents($this->sqlPath, <<<'SQL'
INSERT INTO `kurikulum` (`kur_id`, `kode_jur`, `sem_id`, `nama_kurikulum`, `tahun_mulai_berlaku`, `no_sk_rektor`, `tgl_sk_rektor`, `ket`) VALUES
(1, 'P1', 20201, 'Kurikulum P1 2020', NULL, 'SK-1', '2020-09-01', NULL),
(2, 'P1', 20211, 'Kurikulum P1 2021', NULL, NULL, NULL, NULL),
(3, 'P2', 20211, 'Kurikulum P2 2021', NULL, NULL, NULL, NULL);
INSERT INTO `matkul` (`id_matkul`, `kur_id`, `id_tipe_matkul`, `kode_mk`, `semester`, `nama_mk`, `sks_tm`, `sks_prak`, `sks_prak_lap`, `sks_sim`, `a_wajib`) VALUES
(10, 1, 'A', 'MK-SAMA', 1, 'Mata Kuliah Sama', 2, 0, 0, 0, 1),
(11, 2, 'A', 'MK-SAMA', 2, 'Mata Kuliah Sama', 2, 0, 0, 0, 1),
(12, 1, 'A', 'MK-KONFLIK', 1, 'Nama Program Satu', 2, 0, 0, 0, 1),
(13, 3, 'B', 'MK-KONFLIK', 1, 'Nama Program Dua', 3, 0, 0, 0, 0),
(14, 1, 'A', 'MK-UNIK', 3, 'Mata Kuliah Unik', 2, 1, 0, 0, 1);
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

    public function test_dry_run_reports_duplicates_and_real_run_is_idempotent(): void
    {
        $arguments = [
            '--source' => $this->sqlPath,
            '--table' => 'all',
            '--report' => $this->reportPath,
        ];

        $this->assertSame(0, Artisan::call('siakad:migrate-phase2', $arguments + ['--dry-run' => true]));
        $this->assertDatabaseCount('curriculums', 0);
        $this->assertDatabaseCount('courses', 0);
        $this->assertDatabaseCount('curriculum_courses', 0);
        $this->assertDatabaseCount('legacy_migration_maps', 0);

        $report = file_get_contents($this->reportPath);
        $this->assertStringContainsString('DUPLICATE_MERGED', $report);
        $this->assertStringContainsString('CONFLICT_SPLIT', $report);
        $this->assertStringContainsString('MK-KONFLIK', $report);

        $this->assertSame(0, Artisan::call('siakad:migrate-phase2', $arguments));
        $this->assertDatabaseCount('curriculums', 3);
        $this->assertDatabaseCount('courses', 4);
        $this->assertDatabaseCount('curriculum_courses', 5);
        $this->assertDatabaseCount('legacy_migration_maps', 8);

        $this->assertSame(1, Course::where('code', 'MK-SAMA')->count());
        $this->assertSame(2, Course::where('name', 'like', 'Nama Program%')->count());
        $this->assertSame('Nonaktif', Curriculum::where('code', 'SIAKAD-KUR-1')->value('status'));
        $this->assertSame(3, Course::where('code', 'MK-UNIK')->value('credits'));

        $manualCourse = Course::where('code', 'MK-SAMA')->firstOrFail();
        $manualCourse->update(['code' => 'MK-MANUAL', 'name' => 'Nama Manual']);

        $this->assertSame(0, Artisan::call('siakad:migrate-phase2', $arguments));
        $this->assertDatabaseCount('curriculums', 3);
        $this->assertDatabaseCount('courses', 4);
        $this->assertDatabaseCount('curriculum_courses', 5);
        $this->assertDatabaseCount('legacy_migration_maps', 8);
        $this->assertDatabaseHas('courses', ['id' => $manualCourse->id, 'code' => 'MK-MANUAL', 'name' => 'Nama Manual']);
    }

    private function createMinimalSchema(): void
    {
        Schema::create('study_programs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('faculty_id');
            $table->string('code')->unique();
            $table->string('name');
            $table->string('level')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
        });
        Schema::create('curriculums', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('study_program_id');
            $table->string('code')->unique();
            $table->string('name');
            $table->integer('year');
            $table->text('description')->nullable();
            $table->string('status')->default('Draft');
            $table->timestamps();
        });
        Schema::create('courses', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('study_program_id');
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->integer('credits')->default(2);
            $table->integer('semester')->default(1);
            $table->string('type')->default('Wajib');
            $table->boolean('status')->default(true);
            $table->timestamps();
        });
        Schema::create('curriculum_courses', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('curriculum_id');
            $table->unsignedBigInteger('course_id');
            $table->integer('semester')->default(1);
            $table->boolean('is_required')->default(true);
            $table->timestamps();
            $table->unique(['curriculum_id', 'course_id']);
        });
        Schema::create('legacy_migration_maps', function (Blueprint $table): void {
            $table->id();
            $table->string('source_system', 32)->default('siakad');
            $table->string('entity', 64);
            $table->string('source_id', 100);
            $table->string('target_table', 64);
            $table->unsignedBigInteger('target_id');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['source_system', 'entity', 'source_id']);
        });
    }
}
