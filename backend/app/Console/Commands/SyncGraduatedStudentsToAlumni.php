<?php

namespace App\Console\Commands;

use App\Models\Alumni;
use App\Models\Student;
use App\Services\AlumniSynchronizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class SyncGraduatedStudentsToAlumni extends Command
{
    protected $signature = 'students:sync-alumni
        {--nim= : Sinkronkan hanya satu NIM}
        {--dry-run : Tampilkan hasil tanpa mengubah database}';

    protected $description = 'Menyinkronkan seluruh mahasiswa berstatus Lulus ke data alumni secara idempoten.';

    public function handle(AlumniSynchronizer $synchronizer): int
    {
        if (! Schema::hasTable('students') || ! Schema::hasTable('alumni')) {
            $this->error("Tabel mahasiswa atau alumni belum tersedia. Jalankan 'php artisan migrate' terlebih dahulu.");

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $nim = trim((string) $this->option('nim'));
        $query = Student::query()->where('status', 'Lulus')->when($nim !== '', fn ($q) => $q->where('nim', $nim));
        $stats = ['diproses' => 0, 'dibuat' => 0, 'diperbarui' => 0, 'dilewati' => 0];

        if ($dryRun) {
            $this->warn('MODE DRY-RUN: database tidak akan diubah.');
        }

        $query->orderBy('id')->chunkById(200, function ($students) use ($synchronizer, $dryRun, &$stats): void {
            foreach ($students as $student) {
                $stats['diproses']++;
                if (! $student->study_program_id) {
                    $stats['dilewati']++;
                    $this->warn("Dilewati {$student->nim}: program studi belum terisi.");

                    continue;
                }

                $exists = Alumni::where('student_id', $student->id)->orWhere('nim', $student->nim)->exists();
                $stats[$exists ? 'diperbarui' : 'dibuat']++;
                if (! $dryRun) {
                    $synchronizer->sync($student);
                }
            }
        });

        $this->table(
            ['Diproses', $dryRun ? 'Akan dibuat' : 'Dibuat', $dryRun ? 'Akan diperbarui' : 'Diperbarui', 'Dilewati'],
            [array_values($stats)]
        );
        $this->info($dryRun ? 'DRY-RUN sinkronisasi alumni selesai.' : 'Sinkronisasi alumni selesai.');

        return self::SUCCESS;
    }
}
