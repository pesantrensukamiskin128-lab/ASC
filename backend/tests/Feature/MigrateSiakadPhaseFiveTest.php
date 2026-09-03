<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MigrateSiakadPhaseFiveTest extends TestCase
{
    private string $sqlPath;

    private string $reportPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createMinimalSchema();
        DB::table('students')->insert([
            'id' => 1, 'study_program_id' => 1, 'nim' => 'M001', 'name' => 'Mahasiswa Satu',
            'status' => 'Aktif', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('semesters')->insert([
            ['id' => 1, 'academic_year_id' => 1, 'name' => 'Ganjil 2020/2021', 'type' => 'Ganjil', 'start_date' => '2020-09-01', 'end_date' => '2021-01-31'],
            ['id' => 2, 'academic_year_id' => 1, 'name' => 'Genap 2020/2021', 'type' => 'Genap', 'start_date' => '2021-02-01', 'end_date' => '2021-08-31'],
        ]);

        $this->sqlPath = tempnam(sys_get_temp_dir(), 'asc-phase5-');
        $this->reportPath = tempnam(sys_get_temp_dir(), 'asc-phase5-report-');
        file_put_contents($this->sqlPath, <<<'SQL'
INSERT INTO `akm` (`akm_id`, `mhs_nim`, `sem_id`, `id_stat_mhs`, `ip`, `ipk`, `jatah_sks`, `sks_diambil`, `sks_wajib`, `sks_pilihan`, `total_sks`) VALUES
(1, 'M001', '20201', 'A', '3.25', '3.10', 24, 20, 18, 2, 40),
(2, 'M001', '20201', 'A', '3.50', '3.20', 24, 22, 20, 2, 42),
(3, 'UNKNOWN', '20201', 'A', '3.00', '3.00', 24, 20, 20, 0, 20),
(4, 'M001', 'BAD', 'A', '3.00', '3.00', 24, 20, 20, 0, 20);
INSERT INTO `cuti_mahasiswa` (`id_cuti`, `nim`, `jenis_keluar`, `tgl_keluar`, `file_sk`, `keterangan`, `kode_fak`, `kode_jur`, `created_at`, `updated_at`, `last_update`, `tgl_berakhir`) VALUES
(1, 'M001', NULL, NULL, NULL, 'Cuti keluarga', 'FT', '86208', '2021-02-01', NULL, NULL, '2021-08-31');
INSERT INTO `skala_nilai` (`id`, `nilai_huruf`, `nilai_indeks`, `bobot_nilai_min`, `bobot_nilai_maks`, `tgl_mulai_efektif`, `tgl_akhir_efektif`, `kode_jurusan`) VALUES
(1, 'A', '4', '80.00', '100.00', '2018-09-01', '2025-09-09', 'P1'),
(2, 'B', '3', '70.00', '79.99', '2018-09-01', '2025-09-09', 'P1'),
(3, 'C', '2', '60.00', '69.99', '2018-09-01', '2025-09-09', 'P1'),
(4, 'D', '1', '50.00', '59.99', '2018-09-01', '2025-09-09', 'P1'),
(5, 'E', '0', '0.00', '49.99', '2018-09-01', '2025-09-09', 'P1'),
(6, 'A', '4', '80.00', '100.00', '2018-09-01', '2025-09-09', 'P2'),
(7, 'B', '3', '70.00', '79.99', '2018-09-01', '2025-09-09', 'P2'),
(8, 'C', '2', '60.00', '69.99', '2018-09-01', '2025-09-09', 'P2'),
(9, 'D', '1', '50.00', '59.99', '2018-09-01', '2025-09-09', 'P2'),
(10, 'E', '0', '0.00', '49.99', '2018-09-01', '2025-09-09', 'P2');
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

    public function test_dry_run_real_run_and_rerun_preserve_existing_data(): void
    {
        $arguments = ['--source' => $this->sqlPath, '--table' => 'all', '--report' => $this->reportPath];

        $this->assertSame(0, Artisan::call('siakad:migrate-phase5', $arguments + ['--dry-run' => true]));
        $this->assertDatabaseCount('student_semester_summaries', 0);
        $this->assertDatabaseCount('student_status_histories', 0);
        $this->assertDatabaseCount('academic_leaves', 0);
        $this->assertDatabaseCount('grade_schemas', 0);
        $report = file_get_contents($this->reportPath);
        $this->assertStringContainsString('DUPLICATE_AKM_MERGED', $report);
        $this->assertStringContainsString('MISSING_STUDENT', $report);
        $this->assertStringContainsString('MISSING_SEMESTER', $report);
        $this->assertStringContainsString('DUPLICATE_GRADE_SCHEMA_MERGED', $report);

        $this->assertSame(0, Artisan::call('siakad:migrate-phase5', $arguments));
        $this->assertDatabaseCount('student_semester_summaries', 1);
        $this->assertDatabaseHas('student_semester_summaries', [
            'student_id' => 1, 'semester_id' => 1, 'semester_gpa' => 3.50,
            'cumulative_gpa' => 3.20, 'credits_taken' => 22,
        ]);
        $this->assertDatabaseCount('student_status_histories', 1);
        $this->assertDatabaseHas('student_status_histories', ['student_id' => 1, 'semester_id' => 1, 'status' => 'AKTIF']);
        $this->assertDatabaseCount('academic_leaves', 1);
        $this->assertDatabaseHas('academic_leaves', ['student_id' => 1, 'semester_id' => 2, 'status' => 'SELESAI']);
        $this->assertDatabaseCount('grade_schemas', 1);
        $this->assertDatabaseCount('grade_schema_details', 5);
        $this->assertDatabaseCount('legacy_migration_maps', 15);

        DB::table('student_semester_summaries')->update(['semester_gpa' => 3.99]);
        DB::table('grade_schema_details')->where('letter', 'A')->update(['min_score' => 85]);
        $this->assertSame(0, Artisan::call('siakad:migrate-phase5', $arguments));
        $this->assertDatabaseCount('student_semester_summaries', 1);
        $this->assertDatabaseHas('student_semester_summaries', ['semester_gpa' => 3.99]);
        $this->assertDatabaseCount('academic_leaves', 1);
        // Skema yang diedit manual tidak ditimpa; sumber menjadi skema baru yang terpisah.
        $this->assertDatabaseCount('grade_schemas', 2);
        $this->assertDatabaseHas('grade_schema_details', ['letter' => 'A', 'min_score' => 85]);
    }

    private function createMinimalSchema(): void
    {
        Schema::create('students', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('study_program_id');
            $table->string('nim')->unique();
            $table->string('name');
            $table->string('status');
            $table->timestamps();
        });
        Schema::create('semesters', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('academic_year_id');
            $table->string('name');
            $table->string('type');
            $table->date('start_date');
            $table->date('end_date');
        });
        Schema::create('student_semester_summaries', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('semester_id');
            $table->string('status');
            $table->decimal('semester_gpa', 4, 2)->nullable();
            $table->decimal('cumulative_gpa', 4, 2)->nullable();
            $table->unsignedSmallInteger('credit_limit')->nullable();
            $table->unsignedSmallInteger('credits_taken')->nullable();
            $table->unsignedSmallInteger('required_credits')->nullable();
            $table->unsignedSmallInteger('elective_credits')->nullable();
            $table->unsignedSmallInteger('total_credits')->nullable();
            $table->timestamps();
            $table->unique(['student_id', 'semester_id']);
        });
        Schema::create('student_status_histories', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('semester_id')->nullable();
            $table->string('status');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->text('reason')->nullable();
            $table->string('decree_number')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
        Schema::create('academic_leaves', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('semester_id');
            $table->string('type');
            $table->text('reason');
            $table->string('document_path')->nullable();
            $table->string('status');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->integer('leave_semester_count');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('admin_note')->nullable();
            $table->timestamps();
        });
        Schema::create('grade_schemas', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });
        Schema::create('grade_schema_details', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('grade_schema_id');
            $table->decimal('min_score', 5, 2);
            $table->decimal('max_score', 5, 2);
            $table->string('letter');
            $table->decimal('grade_point', 3, 2);
            $table->integer('order');
            $table->timestamps();
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
