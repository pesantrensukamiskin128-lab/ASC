<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class UpdateExitStatusesTest extends TestCase
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
            ['id' => 2, 'nim' => '20180002', 'name' => 'Mahasiswa Nonaktif', 'status' => 'Nonaktif', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'nim' => '20180003', 'name' => 'Sudah DO', 'status' => 'DO', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 4, 'nim' => '20180004', 'name' => 'Sudah Lulus', 'status' => 'Lulus', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 5, 'nim' => '20180005', 'name' => 'Nonaktif Terbaru', 'status' => 'Nonaktif', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('semesters')->insert(['id' => 7, 'name' => 'Genap 2022/2023', 'type' => 'Genap']);
        DB::table('student_status_histories')->insert([
            ['student_id' => 1, 'semester_id' => null, 'status' => 'Aktif', 'start_date' => '2018-08-01', 'end_date' => null, 'reason' => null, 'decree_number' => null, 'created_by' => null, 'created_at' => now(), 'updated_at' => now()],
            ['student_id' => 2, 'semester_id' => null, 'status' => 'Nonaktif', 'start_date' => '2021-08-01', 'end_date' => null, 'reason' => null, 'decree_number' => null, 'created_by' => null, 'created_at' => now(), 'updated_at' => now()],
            ['student_id' => 5, 'semester_id' => 7, 'status' => 'Nonaktif', 'start_date' => '2025-02-01', 'end_date' => null, 'reason' => 'Status Nonaktif berdasarkan data aktivitas mahasiswa 2024/2025 Genap (nonactive.xlsx)', 'decree_number' => null, 'created_by' => null, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->sourcePath = tempnam(sys_get_temp_dir(), 'exit-status-').'.xlsx';
        $this->reportPath = tempnam(sys_get_temp_dir(), 'exit-status-report-');
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([
            ['NIM', 'Nama Mahasiswa', 'Program Studi', 'Angkatan', 'Jenis Keluar', 'Status ASC', 'Tanggal Keluar', 'Periode Keluar', 'File Sumber'],
            ['20180001', 'Nama Excel', 'S1 PAI', '2018', 'Putus Studi', 'DO', '01-09-2023', '2023/2024 Ganjil', '2023.xlsx'],
            ['20180002', 'Mahasiswa Nonaktif', 'S1 PAI', '2018', 'Mengajukan pengunduran diri', 'Mengundurkan Diri', '31-08-2023', '2022/2023 Genap', '2023.xlsx'],
            ['20180003', 'Sudah DO', 'S1 PAI', '2018', 'Putus Studi', 'DO', '08-02-2022', '2021/2022 Genap', '2022.xlsx'],
            ['20180004', 'Sudah Lulus', 'S1 PAI', '2018', 'Putus Studi', 'DO', '01-09-2023', '2023/2024 Ganjil', '2023.xlsx'],
            ['20180005', 'Nonaktif Terbaru', 'S1 PAI', '2018', 'Putus Studi', 'DO', '31-08-2021', '2021/2022 Ganjil', '2021.xlsx'],
            ['20189999', 'Tidak Ada', 'S1 PAI', '2018', 'Putus Studi', 'DO', '01-09-2023', '2023/2024 Ganjil', '2023.xlsx'],
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
        $arguments = ['--source' => $this->sourcePath, '--report' => $this->reportPath];

        $this->assertSame(0, Artisan::call('students:update-exit-status', $arguments + ['--dry-run' => true]));
        $this->assertDatabaseHas('students', ['nim' => '20180001', 'status' => 'Aktif']);
        $this->assertDatabaseCount('student_status_histories', 3);
        $report = file_get_contents($this->reportPath);
        $this->assertStringContainsString('NAME_MISMATCH,20180001', $report);
        $this->assertStringContainsString('SEMESTER_NOT_MAPPED,20180001', $report);
        $this->assertStringContainsString('ALREADY_MATCHED,20180003', $report);
        $this->assertStringContainsString('TERMINAL_STATUS_CONFLICT,20180004', $report);
        $this->assertStringContainsString('NONACTIVE_HISTORY_REPLACED,20180005', $report);
        $this->assertStringContainsString('MISSING_STUDENT,20189999', $report);

        $this->assertSame(0, Artisan::call('students:update-exit-status', $arguments));
        $this->assertDatabaseHas('students', ['nim' => '20180001', 'status' => 'DO']);
        $this->assertDatabaseHas('students', ['nim' => '20180002', 'status' => 'Mengundurkan Diri']);
        $this->assertDatabaseHas('students', ['nim' => '20180004', 'status' => 'Lulus']);
        $this->assertDatabaseHas('students', ['nim' => '20180005', 'status' => 'DO']);
        $this->assertDatabaseHas('student_status_histories', ['student_id' => 1, 'semester_id' => null, 'status' => 'DO', 'start_date' => '2023-09-01']);
        $this->assertDatabaseHas('student_status_histories', ['student_id' => 2, 'semester_id' => 7, 'status' => 'Mengundurkan Diri', 'start_date' => '2023-08-31']);
        $this->assertDatabaseHas('student_status_histories', ['student_id' => 1, 'status' => 'Aktif', 'end_date' => '2023-09-01']);
        $this->assertDatabaseHas('student_status_histories', ['student_id' => 2, 'status' => 'Nonaktif', 'end_date' => '2023-08-31']);
        $this->assertDatabaseHas('student_status_histories', ['student_id' => 5, 'status' => 'DO', 'start_date' => '2021-08-31', 'end_date' => null]);
        $this->assertDatabaseMissing('student_status_histories', ['student_id' => 5, 'status' => 'Nonaktif']);

        $this->assertSame(0, Artisan::call('students:update-exit-status', $arguments));
        $this->assertDatabaseCount('student_status_histories', 5);
    }
}
