<?php

namespace Database\Seeders;

use App\Models\PmbExamType;
use App\Models\PmbPath;
use App\Models\PmbPeriod;
use App\Models\AcademicYear;
use Illuminate\Database\Seeder;

class PmbSeeder extends Seeder
{
    public function run(): void
    {
        // Jalur seleksi
        $paths = [
            ['code' => 'REGULER',   'name' => 'Jalur Reguler',    'description' => 'Seleksi umum melalui tes akademik dan wawancara'],
            ['code' => 'PRESTASI',  'name' => 'Jalur Prestasi',   'description' => 'Seleksi berdasarkan prestasi akademik dan non-akademik'],
            ['code' => 'BEASISWA',  'name' => 'Jalur Beasiswa',   'description' => 'Seleksi penerima beasiswa berprestasi'],
            ['code' => 'KHUSUS',    'name' => 'Jalur Khusus',     'description' => 'Pendaftaran jalur khusus (santri, hafidz, dll)'],
        ];
        foreach ($paths as $p) {
            PmbPath::firstOrCreate(['code' => $p['code']], $p);
        }

        // Jenis ujian / seleksi
        $examTypes = [
            ['code' => 'ADMINISTRASI',   'name' => 'Seleksi Administrasi',       'weight' => 10, 'passing_grade' => 60],
            ['code' => 'TES_AKADEMIK',   'name' => 'Tes Akademik',              'weight' => 25, 'passing_grade' => 50],
            ['code' => 'TES_DASAR',      'name' => 'Tes Kemampuan Dasar',       'weight' => 15, 'passing_grade' => 50],
            ['code' => 'WAWANCARA',      'name' => 'Tes Wawancara',             'weight' => 15, 'passing_grade' => 60],
            ['code' => 'BACA_QURAN',     'name' => 'Tes Baca Al-Qur\'an',      'weight' => 15, 'passing_grade' => 50],
            ['code' => 'KEISLAMAN',      'name' => 'Tes Keislaman',             'weight' => 10, 'passing_grade' => 50],
            ['code' => 'BAHASA_ARAB',    'name' => 'Tes Bahasa Arab',           'weight' => 5,  'passing_grade' => 40],
            ['code' => 'BAHASA_INGGRIS', 'name' => 'Tes Bahasa Inggris',       'weight' => 5,  'passing_grade' => 40],
        ];
        foreach ($examTypes as $e) {
            PmbExamType::firstOrCreate(['code' => $e['code']], $e);
        }

        // Periode PMB contoh
        $ay = AcademicYear::where('is_active', true)->first();
        if ($ay) {
            PmbPeriod::firstOrCreate(
                ['academic_year_id' => $ay->id, 'name' => 'Gelombang 1'],
                [
                    'registration_start'    => '2026-03-01',
                    'registration_end'      => '2026-05-31',
                    'selection_date'        => '2026-06-10',
                    'announcement_date'     => '2026-06-20',
                    're_registration_start' => '2026-06-21',
                    're_registration_end'   => '2026-07-10',
                    'quota'                 => 200,
                    'registration_fee'      => 250000,
                    'is_active'             => true,
                ]
            );
        }
    }
}
