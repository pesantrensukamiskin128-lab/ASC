<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MigrateSiakadPhaseSixTest extends TestCase
{
    private string $sqlPath;

    private string $reportPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createMinimalSchema();
        DB::table('students')->insert([
            ['id' => 1, 'nim' => '20180001', 'name' => 'Mahasiswa 2018', 'entry_year' => 2018],
            ['id' => 2, 'nim' => '20170001', 'name' => 'Mahasiswa 2017', 'entry_year' => 2017],
        ]);
        DB::table('semesters')->insert([
            [
                'id' => 1, 'name' => 'Ganjil 2018/2019', 'type' => 'Ganjil',
                'start_date' => '2018-08-01', 'end_date' => '2019-01-31',
            ],
            [
                'id' => 2, 'name' => 'Genap 2019/2020', 'type' => 'Genap',
                'start_date' => '2020-02-01', 'end_date' => '2020-07-31',
            ],
        ]);

        $this->sqlPath = tempnam(sys_get_temp_dir(), 'asc-phase6-');
        $this->reportPath = tempnam(sys_get_temp_dir(), 'asc-phase6-report-');
        file_put_contents($this->sqlPath, <<<'SQL'
INSERT INTO `keu_jenis_pembayaran` (`kode_pembayaran`, `nama_pembayaran`) VALUES
('SPP', 'Sumbangan Penyelenggaraan Pendidikan');
INSERT INTO `keu_jenis_tagihan` (`kode_tagihan`, `nama_tagihan`, `kode_pembayaran`, `syarat_krs`, `syarat_uts`, `syarat_uas`) VALUES
('SPP18', 'SPP Semester Ganjil 2018', 'SPP', 'N', 'N', 'N');
INSERT INTO `keu_tagihan` (`id`, `kode_prodi`, `kode_tagihan`, `nominal_tagihan`, `berlaku_angkatan`) VALUES
(10, 'P1', 'SPP18', 1000000, 20181);
INSERT INTO `keu_tagihan_mahasiswa` (`id`, `nim`, `id_tagihan_prodi`, `periode`) VALUES
(101, '20180001', 10, 20181),
(102, '20170001', 10, 20181),
(103, 'UNKNOWN', 10, 20181),
(104, '20180001', 10, 20427),
(105, '20180001', 10, 0);
INSERT INTO `keu_cicilan` (`id_cicilan`, `id_tagihan_mhs`, `jml_bayar`, `tgl_bayar`, `validator`, `lunaskan`) VALUES
(11, 101, 600000, '2018-09-01 10:00:00', 'root', '0');
INSERT INTO `keu_bayar_mahasiswa` (`id`, `id_keu_tagihan_mhs`, `tgl_bayar`, `tgl_validasi`, `created_by`, `nominal_bayar`, `no_kwitansi`, `urutan_bayar_prodi`, `id_bank`, `afirmasi`) VALUES
(21, 101, '2018-09-01 10:00:00', '2018-09-01 10:05:00', 'root', 600000, 'KWT-1', 1, '001', '0'),
(22, 101, '2018-10-01 10:00:00', '2018-10-01 10:05:00', 'root', 400000, 'KWT-2', 2, '001', '0'),
(23, 101, '2018-11-01 10:00:00', '2018-11-01 10:05:00', 'root', 0, 'KWT-3', 3, '001', '0'),
(24, 104, '2020-03-01 10:00:00', '2020-03-01 10:05:00', 'root', 1200000, 'KWT-4', 1, '001', '0');
INSERT INTO `keu_bukti_bayar` (`id`, `nim`, `ket`, `semester`, `file`, `ext`, `norek_pengirim`, `bank`, `bank_tujuan`, `jumlah`, `acc`, `date_created`, `tgl_bayar`) VALUES
(31, '20180001', 'Bukti SPP', 20181, 'bukti.jpg', 'jpg', NULL, NULL, '001', 1000000, '1', '2018-10-01 10:00:00', '2018-10-01');
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

    public function test_dry_run_real_run_and_rerun_respect_entry_year_and_existing_data(): void
    {
        $arguments = [
            '--source' => $this->sqlPath,
            '--table' => 'all',
            '--entry-year-from' => 2018,
            '--report' => $this->reportPath,
        ];

        $this->assertSame(0, Artisan::call('siakad:migrate-phase6', $arguments + ['--dry-run' => true]));
        $this->assertDatabaseCount('fee_types', 0);
        $this->assertDatabaseCount('invoices', 0);
        $this->assertDatabaseCount('payments', 0);
        $dryRunOutput = Artisan::output();
        $this->assertStringContainsString('Tagihan sumber yang dilewati karena angkatan: 1', $dryRunOutput);
        $report = file_get_contents($this->reportPath);
        $this->assertStringContainsString('DUPLICATE_PAYMENT_SOURCE_SKIPPED,payment,11,101', $report);
        $this->assertStringContainsString('LEGACY_RECEIPT_NOT_COPIED', $report);
        $this->assertStringContainsString('MISSING_STUDENT', $report);
        $this->assertStringContainsString('INVOICE_DATE_INFERRED_FROM_PAYMENT,invoice,104,2020-03-01', $report);
        $this->assertStringContainsString('UNRESOLVED_INVOICE_DATE,invoice,105,20180001', $report);
        $this->assertStringContainsString('PAYMENT_EXCEEDS_INVOICE,invoice,104,20180001', $report);

        $this->assertSame(0, Artisan::call('siakad:migrate-phase6', $arguments));
        $this->assertDatabaseCount('fee_types', 1);
        $this->assertDatabaseCount('invoices', 2);
        $this->assertDatabaseCount('invoice_items', 2);
        $this->assertDatabaseCount('payments', 3);
        $this->assertDatabaseHas('invoices', [
            'student_id' => 1, 'total_amount' => 1000000, 'paid_amount' => 1000000, 'status' => 'PAID',
        ]);
        $this->assertDatabaseHas('payments', ['payment_number' => 'MIG-SIAKAD-BAY-21', 'amount' => 600000]);
        $this->assertDatabaseHas('payments', ['payment_number' => 'MIG-SIAKAD-BAY-22', 'amount' => 400000]);
        $this->assertDatabaseHas('invoices', [
            'invoice_number' => 'MIG-SIAKAD-INV-104',
            'semester_id' => 2,
            'invoice_date' => '2020-02-01',
            'due_date' => '2020-07-31',
        ]);
        $this->assertDatabaseMissing('invoices', ['invoice_number' => 'MIG-SIAKAD-INV-105']);
        $this->assertDatabaseCount('legacy_migration_maps', 5);

        DB::table('invoices')->update(['note' => 'Catatan manual ASC']);
        DB::table('payments')->where('payment_number', 'MIG-SIAKAD-BAY-22')->update(['note' => 'Catatan pembayaran manual']);
        $this->assertSame(0, Artisan::call('siakad:migrate-phase6', $arguments));
        $this->assertDatabaseCount('invoices', 2);
        $this->assertDatabaseCount('payments', 3);
        $this->assertDatabaseHas('invoices', ['note' => 'Catatan manual ASC']);
        $this->assertDatabaseHas('payments', ['payment_number' => 'MIG-SIAKAD-BAY-22', 'note' => 'Catatan pembayaran manual']);
    }

    public function test_actual_legacy_dump_can_be_fully_scanned_in_dry_run(): void
    {
        $source = base_path('../_referensi/siakadstai_siakad.sql');
        if (! file_exists($source)) {
            $this->markTestSkipped('Dump SIAKAD lokal tidak tersedia.');
        }

        $this->assertSame(0, Artisan::call('siakad:migrate-phase6', [
            '--source' => $source,
            '--table' => 'all',
            '--entry-year-from' => 2018,
            '--dry-run' => true,
            '--report' => $this->reportPath,
        ]));
        $this->assertDatabaseCount('invoices', 0);
        $this->assertDatabaseCount('payments', 0);
    }

    private function createMinimalSchema(): void
    {
        Schema::create('students', function (Blueprint $table): void {
            $table->id();
            $table->string('nim')->unique();
            $table->string('name');
            $table->year('entry_year')->nullable();
        });
        Schema::create('semesters', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('type');
            $table->date('start_date');
            $table->date('end_date');
        });
        Schema::create('fee_types', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active');
            $table->boolean('is_mandatory');
            $table->boolean('is_recurring');
            $table->timestamps();
        });
        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();
            $table->string('invoice_number')->unique();
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('semester_id')->nullable();
            $table->date('invoice_date');
            $table->date('due_date');
            $table->integer('total_amount');
            $table->integer('discount_amount');
            $table->integer('scholarship_amount');
            $table->integer('paid_amount');
            $table->string('status');
            $table->text('note')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
        Schema::create('invoice_items', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('invoice_id');
            $table->unsignedBigInteger('fee_type_id');
            $table->string('description')->nullable();
            $table->integer('amount');
            $table->timestamps();
        });
        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('invoice_id');
            $table->unsignedBigInteger('student_id');
            $table->string('payment_number')->unique();
            $table->integer('amount');
            $table->string('payment_method');
            $table->timestamp('payment_date');
            $table->string('reference_number')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('account_number')->nullable();
            $table->string('account_name')->nullable();
            $table->string('receipt_path')->nullable();
            $table->string('status');
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->text('note')->nullable();
            $table->text('rejection_reason')->nullable();
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
