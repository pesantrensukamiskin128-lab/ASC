<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Course;
use App\Models\Faculty;
use App\Models\Institution;
use App\Models\StudyProgram;
use Illuminate\Database\Seeder;

class MasterDataSeeder extends Seeder
{
    public function run(): void
    {
        // Institution
        $institution = Institution::firstOrCreate(['code' => 'YAPATA'], [
            'name'              => 'Universitas Al-Jawami',
            'short_name'        => 'Al-Jawami',
            'legal_entity_name' => 'YAPATA Al-Jawami Bandung',
            'address'           => 'Jl. Cisempur No.1, Jatinangor, Sumedang, Jawa Barat',
            'phone'             => '022-7798108',
            'email'             => 'info@jawami.ac.id',
            'website'           => 'https://www.jawami.ac.id',
            'accreditation'     => 'B',
        ]);

        // Faculties
        $fti = Faculty::firstOrCreate(['code' => 'FTI'], [
            'institution_id' => $institution->id,
            'name'           => 'Fakultas Teknologi Informasi',
            'dean_name'      => 'Dr. Ahmad Fauzi, M.T.',
            'status'         => true,
        ]);

        $fekon = Faculty::firstOrCreate(['code' => 'FEKON'], [
            'institution_id' => $institution->id,
            'name'           => 'Fakultas Ekonomi',
            'dean_name'      => 'Dr. Siti Rahayu, M.M.',
            'status'         => true,
        ]);

        $fai = Faculty::firstOrCreate(['code' => 'FAI'], [
            'institution_id' => $institution->id,
            'name'           => 'Fakultas Agama Islam',
            'dean_name'      => 'Dr. H. Abdullah, M.A.',
            'status'         => true,
        ]);

        // Study Programs
        $informatika = StudyProgram::firstOrCreate(['code' => 'TI'], [
            'faculty_id'    => $fti->id,
            'name'          => 'Teknik Informatika',
            'degree'        => 'S.Kom',
            'level'         => 'S1',
            'accreditation' => 'A',
            'status'        => true,
        ]);

        $si = StudyProgram::firstOrCreate(['code' => 'SI'], [
            'faculty_id'    => $fti->id,
            'name'          => 'Sistem Informasi',
            'degree'        => 'S.Kom',
            'level'         => 'S1',
            'accreditation' => 'B',
            'status'        => true,
        ]);

        StudyProgram::firstOrCreate(['code' => 'MAN'], [
            'faculty_id'    => $fekon->id,
            'name'          => 'Manajemen',
            'degree'        => 'S.M.',
            'level'         => 'S1',
            'accreditation' => 'B',
            'status'        => true,
        ]);

        StudyProgram::firstOrCreate(['code' => 'PAI'], [
            'faculty_id'    => $fai->id,
            'name'          => 'Pendidikan Agama Islam',
            'degree'        => 'S.Pd.I',
            'level'         => 'S1',
            'accreditation' => 'B',
            'status'        => true,
        ]);

        // Academic Years
        $ay1 = AcademicYear::firstOrCreate(
            ['name' => 'Tahun Akademik 2025/2026'],
            [
                'start_date' => '2025-08-01',
                'end_date'   => '2026-07-31',
                'is_active'  => true,
            ]
        );

        AcademicYear::firstOrCreate(
            ['name' => 'Tahun Akademik 2024/2025'],
            [
                'start_date' => '2024-08-01',
                'end_date'   => '2025-07-31',
                'is_active'  => false,
            ]
        );

        // Courses — Teknik Informatika
        $courses = [
            ['code' => 'TI101', 'name' => 'Pengantar Teknologi Informasi',    'credits' => 2, 'semester' => 1, 'type' => 'Wajib'],
            ['code' => 'TI102', 'name' => 'Algoritma dan Pemrograman',        'credits' => 4, 'semester' => 1, 'type' => 'Wajib'],
            ['code' => 'TI103', 'name' => 'Matematika Diskrit',               'credits' => 3, 'semester' => 1, 'type' => 'Wajib'],
            ['code' => 'TI104', 'name' => 'Kalkulus',                         'credits' => 3, 'semester' => 1, 'type' => 'Wajib'],
            ['code' => 'TI201', 'name' => 'Struktur Data',                    'credits' => 3, 'semester' => 2, 'type' => 'Wajib'],
            ['code' => 'TI202', 'name' => 'Pemrograman Berorientasi Objek',   'credits' => 4, 'semester' => 2, 'type' => 'Wajib'],
            ['code' => 'TI301', 'name' => 'Basis Data',                       'credits' => 3, 'semester' => 3, 'type' => 'Wajib'],
            ['code' => 'TI302', 'name' => 'Jaringan Komputer',                'credits' => 3, 'semester' => 3, 'type' => 'Wajib'],
            ['code' => 'TI401', 'name' => 'Rekayasa Perangkat Lunak',         'credits' => 3, 'semester' => 4, 'type' => 'Wajib'],
            ['code' => 'TI402', 'name' => 'Pemrograman Web',                  'credits' => 3, 'semester' => 4, 'type' => 'Wajib'],
            ['code' => 'TI403', 'name' => 'Praktikum Basis Data',             'credits' => 1, 'semester' => 4, 'type' => 'Praktikum'],
            ['code' => 'TI501', 'name' => 'Kecerdasan Buatan',                'credits' => 3, 'semester' => 5, 'type' => 'Wajib'],
            ['code' => 'TI502', 'name' => 'Keamanan Jaringan',                'credits' => 3, 'semester' => 5, 'type' => 'Pilihan'],
        ];

        foreach ($courses as $c) {
            Course::firstOrCreate(
                ['code' => $c['code']],
                array_merge($c, ['study_program_id' => $informatika->id, 'status' => true])
            );
        }
    }
}
