<?php

namespace App\Console\Commands;

use App\Models\Student;
use App\Services\SqlDumpParser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class MigrateSiakadPhaseSix extends Command
{
    protected $signature = 'siakad:migrate-phase6
        {--source= : Path ke file SQL dump SIAKAD}
        {--dry-run : Simulasi tanpa mengubah database}
        {--table=all : Data: all, fee-types, invoices, payments}
        {--entry-year-from=2018 : Angkatan minimum mahasiswa yang dimigrasikan}
        {--report= : Path laporan CSV fase keenam}';

    protected $description = 'Migrasi fase 6 SIAKAD: jenis biaya, tagihan, dan pembayaran mahasiswa.';

    private bool $dryRun = false;

    private string $sqlPath = '';

    private int $entryYearFrom = 2018;

    /** @var array<string, array{total:int,inserted:int,existing:int,skipped:int}> */
    private array $stats = [];

    /** @var array<int, array<int, string|int|float|null>> */
    private array $reportRows = [];

    /** @var array<string, array{id:int,entry_year:?int}> */
    private array $students = [];

    /** @var array<string, object> */
    private array $semesters = [];

    /** @var array<int, object> */
    private array $semesterRanges = [];

    /** @var array<string, array{source_code:string,code:string,name:string,recurring:bool}> */
    private array $feeTypePlans = [];

    /** @var array<string, array{name:string,payment_code:string}> */
    private array $chargeTypes = [];

    /** @var array<string, array{amount:int,charge_code:string}> */
    private array $chargeDefinitions = [];

    /** @var array<string, array<string, mixed>> */
    private array $invoicePlans = [];

    /** @var array<string, array<string, mixed>> */
    private array $paymentPlans = [];

    private int $excludedByCohort = 0;

    private int $eligibleStudents = 0;

    private int $inferredInvoiceDates = 0;

    private int $unresolvedInvoiceDates = 0;

    private int $overpaidInvoices = 0;

    public function __construct(private readonly SqlDumpParser $parser)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->resetState();
        $this->dryRun = (bool) $this->option('dry-run');
        $this->sqlPath = $this->option('source') ?? base_path('../_referensi/siakadstai_siakad.sql');
        $this->entryYearFrom = (int) $this->option('entry-year-from');
        $table = (string) $this->option('table');

        if (! in_array($table, ['all', 'fee-types', 'invoices', 'payments'], true)) {
            $this->error("Nilai --table tidak valid: {$table}");

            return self::FAILURE;
        }
        if ($this->entryYearFrom < 1900 || $this->entryYearFrom > 2200) {
            $this->error('--entry-year-from harus berupa tahun antara 1900 dan 2200.');

            return self::FAILURE;
        }
        if (! file_exists($this->sqlPath)) {
            $this->error("File tidak ditemukan: {$this->sqlPath}");

            return self::FAILURE;
        }
        foreach (['fee_types', 'invoices', 'invoice_items', 'payments', 'legacy_migration_maps'] as $required) {
            if (! Schema::hasTable($required)) {
                $this->error("Tabel {$required} belum tersedia. Jalankan 'php artisan migrate' terlebih dahulu.");

                return self::FAILURE;
            }
        }
        if ($this->dryRun) {
            $this->warn('MODE DRY-RUN: database tidak akan diubah.');
        }

        $this->info("Membaca SQL dump: {$this->sqlPath}");
        $this->info('Ukuran file: '.round(filesize($this->sqlPath) / 1048576, 1).' MB');
        $this->info("Batas migrasi: mahasiswa angkatan {$this->entryYearFrom} dan setelahnya.");

        $this->loadTargetReferences();
        $this->prepareFeeTypes();
        $this->prepareChargeDefinitions();
        $this->prepareInvoices();
        $this->preparePayments();
        $this->resolveMissingInvoiceDates();
        $this->auditInvoiceBalances();
        $this->inspectPaymentProofs();

        DB::transaction(function () use ($table): void {
            if (in_array($table, ['all', 'fee-types', 'invoices'], true)) {
                $this->migrateFeeTypes();
            }
            if (in_array($table, ['all', 'invoices'], true)) {
                $this->migrateInvoices($table === 'all');
            }
            if (in_array($table, ['all', 'payments'], true)) {
                $this->migratePayments();
            }
        });

        $reportPath = $this->writeReport();
        $this->displaySummary($reportPath);

        return self::SUCCESS;
    }

    private function resetState(): void
    {
        $this->stats = [];
        $this->reportRows = [];
        $this->students = [];
        $this->semesters = [];
        $this->semesterRanges = [];
        $this->feeTypePlans = [];
        $this->chargeTypes = [];
        $this->chargeDefinitions = [];
        $this->invoicePlans = [];
        $this->paymentPlans = [];
        $this->excludedByCohort = 0;
        $this->eligibleStudents = 0;
        $this->inferredInvoiceDates = 0;
        $this->unresolvedInvoiceDates = 0;
        $this->overpaidInvoices = 0;
    }

    private function loadTargetReferences(): void
    {
        foreach (Student::query()->get(['id', 'nim', 'entry_year']) as $student) {
            $nim = trim((string) $student->nim);
            $entryYear = $student->entry_year ? (int) $student->entry_year : $this->yearFromNim($nim);
            $this->students[$nim] = ['id' => (int) $student->id, 'entry_year' => $entryYear];
            $this->eligibleStudents += $entryYear !== null && $entryYear >= $this->entryYearFrom ? 1 : 0;
        }

        foreach (DB::table('semesters')->get(['id', 'name', 'type', 'start_date', 'end_date']) as $semester) {
            $this->semesterRanges[] = $semester;
            if (! preg_match('/(\d{4})\/(\d{4})/', (string) $semester->name, $match)) {
                continue;
            }
            $suffix = match (strtolower((string) $semester->type)) {
                'ganjil' => '1', 'genap' => '2', 'pendek' => '3', default => null,
            };
            if ($suffix) {
                $this->semesters[$match[1].$suffix] = $semester;
            }
        }
    }

    private function prepareFeeTypes(): void
    {
        $this->line("  Parsing tabel 'keu_jenis_pembayaran'...");
        foreach ($this->parser->iterateTable($this->sqlPath, 'keu_jenis_pembayaran') as $row) {
            $sourceCode = trim((string) ($row['kode_pembayaran'] ?? ''));
            if ($sourceCode === '') {
                continue;
            }
            $this->ensureFeeType($sourceCode, trim((string) ($row['nama_pembayaran'] ?? '')) ?: $sourceCode);
        }
        $this->line('  Ditemukan '.count($this->feeTypePlans).' jenis biaya unik.');
    }

    private function prepareChargeDefinitions(): void
    {
        $this->line("  Parsing tabel 'keu_jenis_tagihan' dan 'keu_tagihan'...");
        foreach ($this->parser->iterateTable($this->sqlPath, 'keu_jenis_tagihan') as $row) {
            $code = trim((string) ($row['kode_tagihan'] ?? ''));
            if ($code === '') {
                continue;
            }
            $paymentCode = trim((string) ($row['kode_pembayaran'] ?? '')) ?: $code;
            $name = trim((string) ($row['nama_tagihan'] ?? '')) ?: $code;
            $this->chargeTypes[$code] = ['name' => $name, 'payment_code' => $paymentCode];
            $this->ensureFeeType($paymentCode, $name);
        }

        foreach ($this->parser->iterateTable($this->sqlPath, 'keu_tagihan') as $row) {
            $sourceId = trim((string) ($row['id'] ?? ''));
            $amount = $this->positiveAmount($row['nominal_tagihan'] ?? null);
            if ($sourceId === '' || $amount === null) {
                $this->report('INVALID_CHARGE_DEFINITION', 'keu_tagihan', $sourceId, (string) ($row['nominal_tagihan'] ?? ''), 'Definisi tagihan tidak memiliki nominal positif.');

                continue;
            }
            $this->chargeDefinitions[$sourceId] = [
                'amount' => $amount,
                'charge_code' => trim((string) ($row['kode_tagihan'] ?? '')),
            ];
        }
        $this->line('  Ditemukan '.count($this->chargeDefinitions).' definisi nominal tagihan.');
    }

    private function prepareInvoices(): void
    {
        $this->line("  Parsing tabel 'keu_tagihan_mahasiswa' secara streaming...");
        $sourceRows = 0;
        foreach ($this->parser->iterateTable($this->sqlPath, 'keu_tagihan_mahasiswa') as $row) {
            $sourceRows++;
            $sourceId = trim((string) ($row['id'] ?? ''));
            $nim = trim((string) ($row['nim'] ?? ''));
            $student = $this->students[$nim] ?? null;
            if (! $student) {
                $this->report('MISSING_STUDENT', 'invoice', $sourceId, $nim, 'Mahasiswa tidak ditemukan di ASC.');

                continue;
            }
            if ($student['entry_year'] === null) {
                $this->report('UNKNOWN_ENTRY_YEAR', 'invoice', $sourceId, $nim, 'Angkatan mahasiswa tidak dapat ditentukan.');

                continue;
            }
            if ($student['entry_year'] < $this->entryYearFrom) {
                $this->excludedByCohort++;

                continue;
            }

            $chargeSourceId = trim((string) ($row['id_tagihan_prodi'] ?? ''));
            $definition = $this->chargeDefinitions[$chargeSourceId] ?? null;
            if (! $definition) {
                $this->report('MISSING_CHARGE_DEFINITION', 'invoice', $sourceId, $chargeSourceId, 'Definisi nominal tagihan tidak ditemukan.');

                continue;
            }
            $chargeType = $this->chargeTypes[$definition['charge_code']] ?? [
                'name' => $definition['charge_code'] ?: 'Tagihan SIAKAD',
                'payment_code' => $definition['charge_code'] ?: 'LAINNYA',
            ];
            $this->ensureFeeType($chargeType['payment_code'], $chargeType['name']);
            $period = trim((string) ($row['periode'] ?? ''));
            $semester = $this->semesters[$period] ?? null;
            if (! $semester) {
                $this->report('MISSING_SEMESTER', 'invoice', $sourceId, $period, 'Semester tidak ditemukan; tanggal akan dicocokkan dari pembayaran valid.');
            }

            $this->invoicePlans[$sourceId] = [
                'source_id' => $sourceId,
                'student_id' => $student['id'],
                'nim' => $nim,
                'semester_id' => $semester?->id ? (int) $semester->id : null,
                'invoice_number' => 'MIG-SIAKAD-INV-'.$sourceId,
                'invoice_date' => $semester ? substr((string) $semester->start_date, 0, 10) : null,
                'due_date' => $semester ? substr((string) $semester->end_date, 0, 10) : null,
                'amount' => $definition['amount'],
                'fee_source_code' => $chargeType['payment_code'],
                'description' => $chargeType['name'],
                'payments_total' => 0,
            ];
        }
        $this->line("  Ditemukan {$sourceRows} baris; ".count($this->invoicePlans).' tagihan memenuhi batas angkatan.');
    }

    private function preparePayments(): void
    {
        $seenFingerprints = [];
        $availableInstallments = [];
        $counts = [];
        foreach ([
            'keu_cicilan' => ['id' => 'id_cicilan', 'invoice' => 'id_tagihan_mhs', 'amount' => 'jml_bayar', 'date' => 'tgl_bayar'],
            'keu_bayar_mahasiswa' => ['id' => 'id', 'invoice' => 'id_keu_tagihan_mhs', 'amount' => 'nominal_bayar', 'date' => 'tgl_bayar'],
        ] as $table => $columns) {
            $this->line("  Parsing tabel '{$table}' secara streaming...");
            $counts[$table] = 0;
            foreach ($this->parser->iterateTable($this->sqlPath, $table) as $row) {
                $counts[$table]++;
                $sourceId = trim((string) ($row[$columns['id']] ?? ''));
                $invoiceSourceId = trim((string) ($row[$columns['invoice']] ?? ''));
                $invoice = $this->invoicePlans[$invoiceSourceId] ?? null;
                if (! $invoice) {
                    continue;
                }
                $amount = $this->positiveAmount($row[$columns['amount']] ?? null);
                $date = $this->validDateTime($row[$columns['date']] ?? null);
                if ($amount === null || $date === null) {
                    $this->report('INVALID_PAYMENT', 'payment', $sourceId, $invoiceSourceId, 'Nominal harus positif dan tanggal pembayaran harus valid.');

                    continue;
                }
                $dayFingerprint = $invoiceSourceId.'|'.$amount.'|'.substr($date, 0, 10);
                if ($table === 'keu_bayar_mahasiswa' && ($availableInstallments[$dayFingerprint] ?? []) !== []) {
                    $canonicalKey = array_shift($availableInstallments[$dayFingerprint]);
                    $this->paymentPlans[$canonicalKey]['aliases'][] = [$table, $sourceId];
                    $this->paymentPlans[$canonicalKey]['reference_number'] ??= $this->nullableString($row['no_kwitansi'] ?? null);
                    $this->paymentPlans[$canonicalKey]['bank_code'] ??= $this->nullableString($row['id_bank'] ?? null);
                    $this->report('DUPLICATE_PAYMENT_MERGED', 'payment', $sourceId, $this->paymentPlans[$canonicalKey]['source_id'], 'Pembayaran dan cicilan memiliki tagihan, nominal, dan tanggal yang sama.');

                    continue;
                }
                $fingerprint = $invoiceSourceId.'|'.$amount.'|'.$date;
                if (isset($seenFingerprints[$fingerprint])) {
                    $canonicalKey = $seenFingerprints[$fingerprint];
                    $this->paymentPlans[$canonicalKey]['aliases'][] = [$table, $sourceId];
                    $this->report('DUPLICATE_PAYMENT_MERGED', 'payment', $sourceId, $this->paymentPlans[$canonicalKey]['source_id'], "Duplikat identik dari {$table}.");

                    continue;
                }
                $key = $table.'|'.$sourceId;
                $seenFingerprints[$fingerprint] = $key;
                $this->paymentPlans[$key] = [
                    'source_table' => $table,
                    'source_id' => $sourceId,
                    'aliases' => [],
                    'invoice_source_id' => $invoiceSourceId,
                    'student_id' => $invoice['student_id'],
                    'payment_number' => 'MIG-SIAKAD-'.($table === 'keu_cicilan' ? 'CIC-' : 'BAY-').$sourceId,
                    'amount' => $amount,
                    'payment_date' => $date,
                    'reference_number' => $table === 'keu_bayar_mahasiswa' ? $this->nullableString($row['no_kwitansi'] ?? null) : null,
                    'bank_code' => $table === 'keu_bayar_mahasiswa' ? $this->nullableString($row['id_bank'] ?? null) : null,
                ];
                if ($table === 'keu_cicilan') {
                    $availableInstallments[$dayFingerprint][] = $key;
                }
                $this->invoicePlans[$invoiceSourceId]['payments_total'] += $amount;
            }
        }
        $this->line("  Ditemukan {$counts['keu_cicilan']} cicilan dan {$counts['keu_bayar_mahasiswa']} pembayaran; ".count($this->paymentPlans).' transaksi unik memenuhi batas.');
    }

    private function resolveMissingInvoiceDates(): void
    {
        $earliestPayments = [];
        foreach ($this->paymentPlans as $plan) {
            $invoiceSourceId = $plan['invoice_source_id'];
            $paymentDate = substr($plan['payment_date'], 0, 10);
            if (! isset($earliestPayments[$invoiceSourceId]) || $paymentDate < $earliestPayments[$invoiceSourceId]) {
                $earliestPayments[$invoiceSourceId] = $paymentDate;
            }
        }

        $unresolved = [];
        foreach ($this->invoicePlans as $sourceId => &$plan) {
            if ($plan['invoice_date'] !== null && $plan['due_date'] !== null) {
                continue;
            }

            $paymentDate = $earliestPayments[$sourceId] ?? null;
            if ($paymentDate === null) {
                $unresolved[$sourceId] = true;
                $this->unresolvedInvoiceDates++;
                $this->report('UNRESOLVED_INVOICE_DATE', 'invoice', $sourceId, $plan['nim'], 'Semester dan tanggal pembayaran valid tidak tersedia; tagihan dilewati.');

                continue;
            }

            $semester = $this->semesterForDate($paymentDate);
            $plan['semester_id'] = $semester?->id ? (int) $semester->id : null;
            $plan['invoice_date'] = $semester ? substr((string) $semester->start_date, 0, 10) : $paymentDate;
            $plan['due_date'] = $semester ? substr((string) $semester->end_date, 0, 10) : $paymentDate;
            $this->inferredInvoiceDates++;
            $this->report(
                $semester ? 'INVOICE_DATE_INFERRED_FROM_PAYMENT' : 'INVOICE_DATE_INFERRED_WITHOUT_SEMESTER',
                'invoice',
                $sourceId,
                $paymentDate,
                $semester ? 'Tanggal dan semester dicocokkan dari pembayaran paling awal.' : 'Tanggal memakai pembayaran paling awal; semester target yang sesuai tidak ditemukan.'
            );
        }
        unset($plan);

        if ($unresolved === []) {
            return;
        }

        $this->invoicePlans = array_filter(
            $this->invoicePlans,
            fn (array $plan): bool => ! isset($unresolved[$plan['source_id']])
        );
        $this->paymentPlans = array_filter(
            $this->paymentPlans,
            fn (array $plan): bool => ! isset($unresolved[$plan['invoice_source_id']])
        );
    }

    private function semesterForDate(string $date): ?object
    {
        $matches = [];
        foreach ($this->semesterRanges as $semester) {
            $startDate = substr((string) $semester->start_date, 0, 10);
            $endDate = substr((string) $semester->end_date, 0, 10);
            if ($date >= $startDate && $date <= $endDate) {
                $matches[] = $semester;
            }
        }

        return count($matches) === 1 ? $matches[0] : null;
    }

    private function auditInvoiceBalances(): void
    {
        foreach ($this->invoicePlans as $plan) {
            if ($plan['payments_total'] <= $plan['amount']) {
                continue;
            }

            $this->overpaidInvoices++;
            $this->report(
                'PAYMENT_EXCEEDS_INVOICE',
                'invoice',
                $plan['source_id'],
                $plan['nim'],
                "Total pembayaran {$plan['payments_total']} melebihi nominal tagihan {$plan['amount']}; seluruh transaksi tetap dipertahankan."
            );
        }
    }

    private function inspectPaymentProofs(): void
    {
        $eligible = 0;
        foreach ($this->parser->iterateTable($this->sqlPath, 'keu_bukti_bayar') as $row) {
            $nim = trim((string) ($row['nim'] ?? ''));
            $student = $this->students[$nim] ?? null;
            if (! $student || $student['entry_year'] === null || $student['entry_year'] < $this->entryYearFrom) {
                continue;
            }
            $eligible++;
            if (trim((string) ($row['file'] ?? '')) !== '') {
                $this->report('LEGACY_RECEIPT_NOT_COPIED', 'payment_proof', (string) ($row['id'] ?? ''), $nim, 'Referensi bukti pembayaran dicatat, tetapi berkas fisik tidak tersedia di dump SQL.');
            }
        }
        $this->stats['Bukti Bayar (referensi)'] = ['total' => $eligible, 'inserted' => 0, 'existing' => 0, 'skipped' => $eligible];
    }

    private function migrateFeeTypes(): void
    {
        $existing = DB::table('fee_types')->pluck('id', 'code');
        $rows = [];
        $stats = $this->blankStats(count($this->feeTypePlans));
        foreach ($this->feeTypePlans as $plan) {
            if ($existing->has($plan['code'])) {
                $stats['existing']++;

                continue;
            }
            $stats['inserted']++;
            $rows[] = [
                'code' => $plan['code'], 'name' => $plan['name'],
                'description' => 'Migrasi jenis pembayaran SIAKAD: '.$plan['source_code'],
                'is_active' => true, 'is_mandatory' => true, 'is_recurring' => $plan['recurring'],
                'created_at' => now(), 'updated_at' => now(),
            ];
        }
        if (! $this->dryRun) {
            $this->insertChunks('fee_types', $rows);
        }
        $this->stats['Jenis Biaya'] = $stats;
    }

    private function migrateInvoices(bool $includePayments): void
    {
        $feeTypeIds = DB::table('fee_types')->pluck('id', 'code');
        if ($this->dryRun) {
            foreach ($this->feeTypePlans as $plan) {
                $feeTypeIds->put($plan['code'], -1);
            }
        }
        $existingInvoices = DB::table('invoices')->pluck('id', 'invoice_number');
        $invoiceStats = $this->blankStats(count($this->invoicePlans));
        $itemStats = $this->blankStats(count($this->invoicePlans));
        $rows = [];

        foreach ($this->invoicePlans as $plan) {
            if ($existingInvoices->has($plan['invoice_number'])) {
                $invoiceStats['existing']++;
                $itemStats['existing']++;

                continue;
            }
            $feeCode = $this->feeTypePlans[$plan['fee_source_code']]['code'];
            if (! $feeTypeIds->has($feeCode)) {
                $invoiceStats['skipped']++;
                $itemStats['skipped']++;
                $this->report('MISSING_FEE_TYPE', 'invoice', $plan['source_id'], $feeCode, 'Jenis biaya target tidak tersedia.');

                continue;
            }
            $paid = $includePayments ? $plan['payments_total'] : 0;
            $rows[] = [
                'invoice_number' => $plan['invoice_number'], 'student_id' => $plan['student_id'],
                'semester_id' => $plan['semester_id'], 'invoice_date' => $plan['invoice_date'],
                'due_date' => $plan['due_date'], 'total_amount' => $plan['amount'],
                'discount_amount' => 0, 'scholarship_amount' => 0, 'paid_amount' => $paid,
                'status' => $this->invoiceStatus($plan['amount'], $paid, $plan['due_date']),
                'note' => 'Migrasi SIAKAD fase 6; ID tagihan sumber: '.$plan['source_id'],
                'created_by' => null, 'created_at' => now(), 'updated_at' => now(),
            ];
            $invoiceStats['inserted']++;
            $itemStats['inserted']++;
        }

        if (! $this->dryRun) {
            $this->insertChunks('invoices', $rows);
            $invoiceIds = DB::table('invoices')->whereIn('invoice_number', array_column($rows, 'invoice_number'))->pluck('id', 'invoice_number');
            $items = [];
            foreach ($this->invoicePlans as $plan) {
                $invoiceId = $invoiceIds[$plan['invoice_number']] ?? null;
                if (! $invoiceId) {
                    continue;
                }
                $feeCode = $this->feeTypePlans[$plan['fee_source_code']]['code'];
                $items[] = [
                    'invoice_id' => $invoiceId, 'fee_type_id' => $feeTypeIds[$feeCode],
                    'description' => $plan['description'], 'amount' => $plan['amount'],
                    'created_at' => now(), 'updated_at' => now(),
                ];
            }
            $this->insertChunks('invoice_items', $items);
            $this->saveInvoiceMaps();
        }
        $this->stats['Tagihan'] = $invoiceStats;
        $this->stats['Detail Tagihan'] = $itemStats;
    }

    private function migratePayments(): void
    {
        $invoiceIds = DB::table('invoices')->pluck('id', 'invoice_number');
        $existingPayments = DB::table('payments')->pluck('id', 'payment_number');
        $stats = $this->blankStats(count($this->paymentPlans));
        $rows = [];
        foreach ($this->paymentPlans as $plan) {
            if ($existingPayments->has($plan['payment_number'])) {
                $stats['existing']++;

                continue;
            }
            $invoiceNumber = $this->invoicePlans[$plan['invoice_source_id']]['invoice_number'];
            $invoiceId = $invoiceIds[$invoiceNumber] ?? null;
            if (! $invoiceId && ! $this->dryRun) {
                $stats['skipped']++;
                $this->report('MISSING_TARGET_INVOICE', 'payment', $plan['source_id'], $plan['invoice_source_id'], 'Tagihan target belum tersedia.');

                continue;
            }
            $rows[] = [
                'invoice_id' => $invoiceId ?? -1, 'student_id' => $plan['student_id'],
                'payment_number' => $plan['payment_number'], 'amount' => $plan['amount'],
                'payment_method' => 'LEGACY', 'payment_date' => $plan['payment_date'],
                'reference_number' => $plan['reference_number'], 'bank_name' => $plan['bank_code'],
                'account_number' => null, 'account_name' => null, 'receipt_path' => null,
                'status' => 'VERIFIED', 'verified_by' => null, 'verified_at' => $plan['payment_date'],
                'note' => 'Migrasi SIAKAD fase 6 dari '.$plan['source_table'],
                'rejection_reason' => null, 'created_at' => $plan['payment_date'], 'updated_at' => now(),
            ];
            $stats['inserted']++;
        }
        if (! $this->dryRun) {
            $this->insertChunks('payments', $rows);
            $this->savePaymentMaps();
        }
        $this->stats['Pembayaran'] = $stats;
    }

    private function saveInvoiceMaps(): void
    {
        $ids = DB::table('invoices')->whereIn('invoice_number', array_column($this->invoicePlans, 'invoice_number'))->pluck('id', 'invoice_number');
        $maps = [];
        foreach ($this->invoicePlans as $plan) {
            if ($targetId = $ids[$plan['invoice_number']] ?? null) {
                $maps[] = $this->mapRow('finance_invoice', $plan['source_id'], 'invoices', $targetId, ['entry_year_from' => $this->entryYearFrom]);
            }
        }
        $this->upsertMaps($maps);
    }

    private function savePaymentMaps(): void
    {
        $ids = DB::table('payments')->whereIn('payment_number', array_column($this->paymentPlans, 'payment_number'))->pluck('id', 'payment_number');
        $maps = [];
        foreach ($this->paymentPlans as $plan) {
            $targetId = $ids[$plan['payment_number']] ?? null;
            if (! $targetId) {
                continue;
            }
            $maps[] = $this->mapRow($this->paymentEntity($plan['source_table']), $plan['source_id'], 'payments', $targetId);
            foreach ($plan['aliases'] as [$table, $sourceId]) {
                $maps[] = $this->mapRow($this->paymentEntity($table), $sourceId, 'payments', $targetId, ['merged_into' => $plan['source_id']]);
            }
        }
        $this->upsertMaps($maps);
    }

    private function ensureFeeType(string $sourceCode, string $name): void
    {
        if (isset($this->feeTypePlans[$sourceCode])) {
            return;
        }
        $normalized = strtoupper(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $sourceCode) ?? '', '-')) ?: 'LAINNYA';
        $code = 'SIK-'.substr($normalized, 0, 18).'-'.strtoupper(substr(sha1($sourceCode), 0, 6));
        $this->feeTypePlans[$sourceCode] = [
            'source_code' => $sourceCode, 'code' => substr($code, 0, 30),
            'name' => mb_substr($name, 0, 255),
            'recurring' => str_contains(strtoupper($sourceCode.' '.$name), 'SPP'),
        ];
    }

    private function invoiceStatus(int $total, int $paid, string $dueDate): string
    {
        if ($paid >= $total) {
            return 'PAID';
        }
        if ($paid > 0) {
            return 'PARTIAL';
        }

        return $dueDate < now()->toDateString() ? 'OVERDUE' : 'UNPAID';
    }

    private function positiveAmount(mixed $value): ?int
    {
        $raw = trim((string) $value);

        return is_numeric($raw) && (float) $raw > 0 ? (int) round((float) $raw) : null;
    }

    private function validDateTime(mixed $value): ?string
    {
        $raw = trim((string) $value);
        if ($raw === '' || str_starts_with($raw, '0000-00-00')) {
            return null;
        }
        $timestamp = strtotime($raw);

        return $timestamp === false ? null : date('Y-m-d H:i:s', $timestamp);
    }

    private function yearFromNim(string $nim): ?int
    {
        if (preg_match('/^(19|20)\d{2}/', $nim, $match)) {
            return (int) $match[0];
        }

        return null;
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function paymentEntity(string $table): string
    {
        return $table === 'keu_cicilan' ? 'finance_payment_installment' : 'finance_payment';
    }

    /** @return array{total:int,inserted:int,existing:int,skipped:int} */
    private function blankStats(int $total): array
    {
        return ['total' => $total, 'inserted' => 0, 'existing' => 0, 'skipped' => 0];
    }

    private function report(string $category, string $entity, string $sourceId, string $reference, string $details): void
    {
        $this->reportRows[] = [$category, $entity, $sourceId, $reference, $details];
    }

    /** @return array<string, mixed> */
    private function mapRow(string $entity, string $sourceId, string $table, int $targetId, array $metadata = []): array
    {
        return [
            'source_system' => 'siakad', 'entity' => $entity, 'source_id' => $sourceId,
            'target_table' => $table, 'target_id' => $targetId,
            'metadata' => $metadata ? json_encode($metadata) : null,
            'created_at' => now(), 'updated_at' => now(),
        ];
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function upsertMaps(array $rows): void
    {
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('legacy_migration_maps')->upsert(
                $chunk, ['source_system', 'entity', 'source_id'],
                ['target_table', 'target_id', 'metadata', 'updated_at']
            );
        }
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function insertChunks(string $table, array $rows): void
    {
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table($table)->insert($chunk);
        }
    }

    private function writeReport(): string
    {
        $path = $this->option('report') ?: storage_path('app/migration/phase6-migration-report.csv');
        File::ensureDirectoryExists(dirname($path));
        $handle = fopen($path, 'wb');
        fputcsv($handle, ['category', 'entity', 'source_id', 'source_reference', 'details']);
        foreach ($this->reportRows as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);

        return $path;
    }

    private function displaySummary(string $reportPath): void
    {
        $this->newLine();
        $this->info('Hasil Migrasi Fase 6:');
        $this->line("Batas angkatan: {$this->entryYearFrom} dan setelahnya.");
        $this->line("Mahasiswa ASC yang memenuhi batas: {$this->eligibleStudents}.");
        $this->line("Tagihan sumber yang dilewati karena angkatan: {$this->excludedByCohort}.");
        $this->line("Tanggal tagihan yang dipetakan dari pembayaran: {$this->inferredInvoiceDates}.");
        $this->line("Tagihan tanpa semester dan tanggal valid yang dilewati: {$this->unresolvedInvoiceDates}.");
        $this->line("Tagihan dengan total pembayaran melebihi nominal: {$this->overpaidInvoices}.");
        $rows = [];
        foreach ($this->stats as $table => $stats) {
            $rows[] = [$table, $stats['total'], $stats['inserted'], $stats['existing'], $stats['skipped']];
        }
        $this->table(['Tabel', 'Diproses', 'Diinsert', 'Sudah Ada', 'Dilewati'], $rows);
        $this->line('Laporan lengkap: '.$reportPath);
        $this->dryRun
            ? $this->warn('DRY-RUN selesai. Jalankan tanpa --dry-run setelah laporan diperiksa.')
            : $this->info('Migrasi fase 6 selesai. Data ASC yang sudah ada tidak ditimpa.');
    }
}
