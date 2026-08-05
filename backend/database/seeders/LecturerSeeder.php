<?php

namespace Database\Seeders;

use App\Models\Lecturer;
use App\Models\StudyProgram;
use App\Models\User;
use Illuminate\Database\Seeder;

class LecturerSeeder extends Seeder
{
    public function run(): void
    {
        $prodi = StudyProgram::where('code', 'TI')->first();

        $lecturerUsers = [
            [
                'email'             => 'dosen@jawami.ac.id',
                'nidn'              => '0101019001',
                'nip'               => null,
                'full_name'         => 'Dosen Demo, M.Kom',
                'gender'            => 'L',
                'last_education'    => 'S2',
                'functional_position' => 'Lektor',
                'employment_status' => 'Tetap',
            ],
            [
                'email'             => 'dosen.wali@jawami.ac.id',
                'nidn'              => '0202019002',
                'nip'               => null,
                'full_name'         => 'Dosen Wali Demo, M.T.',
                'gender'            => 'L',
                'last_education'    => 'S2',
                'functional_position' => 'Asisten Ahli',
                'employment_status' => 'Tetap',
            ],
            [
                'email'             => 'kaprodi.if@jawami.ac.id',
                'nidn'              => '0303019003',
                'nip'               => null,
                'full_name'         => 'Kaprodi Informatika, Dr.',
                'gender'            => 'L',
                'last_education'    => 'S3',
                'functional_position' => 'Lektor Kepala',
                'employment_status' => 'Tetap',
            ],
        ];

        foreach ($lecturerUsers as $data) {
            $user = User::where('email', $data['email'])->first();
            if (!$user) continue;

            // Cek field yang ada di tabel lecturers
            $lecturerData = [
                'user_id'              => $user->id,
                'study_program_id'     => $prodi?->id,
                'nidn'                 => $data['nidn'],
                'employment_status'    => $data['employment_status'],
                'status'               => true,
            ];

            // Tambah field opsional jika ada di tabel
            $columns = \Illuminate\Support\Facades\Schema::getColumnListing('lecturers');

            if (in_array('full_name', $columns))   $lecturerData['full_name']   = $data['full_name'];
            if (in_array('name', $columns))         $lecturerData['name']        = $data['full_name'];
            if (in_array('gender', $columns))       $lecturerData['gender']      = $data['gender'];
            if (in_array('last_education', $columns)) $lecturerData['last_education'] = $data['last_education'];
            if (in_array('functional_position', $columns)) $lecturerData['functional_position'] = $data['functional_position'];

            Lecturer::firstOrCreate(
                ['user_id' => $user->id],
                $lecturerData
            );
        }
    }
}
