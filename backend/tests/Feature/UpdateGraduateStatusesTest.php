<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class UpdateGraduateStatusesTest extends TestCase
{
    private string $sourcePath;

    private string $reportPath;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('students', function (Blueprint $table): void {
            $table->id();
            $table->string('nim')->unique();
            $table->string('name');
            $table->string('status');
            $table->timestamps();
        });
        Schema::create('semesters', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('type');
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

        DB::table('students')->insert([
            ['id' => 1, 'nim' => '20180001', 'name' => 'Nama ASC', 'status' => 'Aktif', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'nim' => '20180002', 'name' => 'Sudah Lulus', 'status' => 'Lulus', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'nim' => '20180003', 'name' => 'Status DO', 'status' => 'DO', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('semesters')->insert([
            'id' => 7, 'name' => 'Genap 2022/2023', 'type' => 'Genap',
        ]);
        DB::table('student_status_histories')->insert([
            'student_id' => 1, 'semester_id' => null, 'status' => 'Aktif',
            'start_date' => '2018-08-01', 'end_date' => null, 'reason' => null,
            'decree_number' => null, 'created_by' => null, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->sourcePath = tempnam(sys_get_temp_dir(), 'graduates-').'.xlsx';
        $this->reportPath = tempnam(sys_get_temp_dir(), 'graduates-report-');
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([
            ['NIM', 'Nama Mahasiswa', 'Program Studi', 'Angkatan', 'Jenis Keluar', 'Tanggal Keluar', 'Periode Keluar', 'Tanggal SK', 'Nomor SK', 'IPK', 'No Ijazah'],
            ['20180001', 'Nama dari Excel', 'S1 PAI', '2018', 'Lulus', '11-04-2023', '2022/2023 Genap', '2023-05-08', '114/SK/2023', '3.60', '001'],
            ['20180002', 'Sudah Lulus', 'S1 PAI', '2018', 'Lulus', '11-04-2023', '2022/2023 Genap', null, null, '3.50', '002'],
            ['20180003', 'Status DO', 'S1 PAI', '2018', 'Lulus', '11-04-2023', '2022/2023 Genap', null, null, '3.40', '003'],
            ['20189999', 'Tidak Ada', 'S1 PAI', '2018', 'Lulus', '11-04-2023', '2022/2023 Genap', null, null, '3.30', '004'],
        ]);
        (new Xlsx($spreadsheet))->save($this->sourcePath);
        $spreadsheet->disconnectWorksheets();
    }

    protected function tearDown(): void
    {
        foreach ([$this->sourcePath ?? null, $this->reportPath ?? null] as $path) {
            if ($path && file_exists($path)) {
                unlink($path);
            }
        }
        parent::tearDown();
    }

    public function test_dry_run_real_run_and_rerun_are_safe(): void
    {
        $arguments = [
            '--source' => $this->sourcePath,
            '--report' => $this->reportPath,
        ];

        $this->assertSame(0, Artisan::call('students:update-graduation-status', $arguments + ['--dry-run' => true]));
        $this->assertDatabaseHas('students', ['nim' => '20180001', 'status' => 'Aktif']);
        $this->assertDatabaseCount('student_status_histories', 1);
        $report = file_get_contents($this->reportPath);
        $this->assertStringContainsString('NAME_MISMATCH,20180001', $report);
        $this->assertStringContainsString('ALREADY_GRADUATED,20180002', $report);
        $this->assertStringContainsString('STATUS_CONFLICT,20180003', $report);
        $this->assertStringContainsString('MISSING_STUDENT,20189999', $report);

        $this->assertSame(0, Artisan::call('students:update-graduation-status', $arguments));
        $this->assertDatabaseHas('students', ['nim' => '20180001', 'status' => 'Lulus']);
        $this->assertDatabaseHas('students', ['nim' => '20180003', 'status' => 'DO']);
        $this->assertDatabaseHas('student_status_histories', [
            'student_id' => 1,
            'semester_id' => 7,
            'status' => 'Lulus',
            'start_date' => '2023-04-11',
            'decree_number' => '114/SK/2023',
        ]);
        $this->assertDatabaseHas('student_status_histories', [
            'student_id' => 1,
            'status' => 'Aktif',
            'end_date' => '2023-04-11',
        ]);

        $this->assertSame(0, Artisan::call('students:update-graduation-status', $arguments));
        $this->assertDatabaseCount('student_status_histories', 2);
    }
}
