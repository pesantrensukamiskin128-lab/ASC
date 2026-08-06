<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Lecturer;
use App\Models\LecturerPosition;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'name'     => 'Super Administrator',
                'email'    => 'superadmin@jawami.ac.id',
                'username' => 'superadmin',
                'password' => Hash::make('password'),
                'role'     => 'SUPER_ADMIN',
            ],
            [
                'name'     => 'Rektor',
                'email'    => 'pimpinan@jawami.ac.id',
                'username' => 'pimpinan',
                'password' => Hash::make('password'),
                'role'     => 'DOSEN',
                'position' => 'KETUA',  // jabatan struktural
            ],
            [
                'name'     => 'Admin Akademik',
                'email'    => 'akademik@jawami.ac.id',
                'username' => 'admin.akademik',
                'password' => Hash::make('password'),
                'role'     => 'ADMIN_AKADEMIK',
            ],
            [
                'name'     => 'Admin PMB',
                'email'    => 'pmb@jawami.ac.id',
                'username' => 'admin.pmb',
                'password' => Hash::make('password'),
                'role'     => 'ADMIN_PMB',
            ],
            [
                'name'     => 'Admin Keuangan',
                'email'    => 'keuangan@jawami.ac.id',
                'username' => 'admin.keuangan',
                'password' => Hash::make('password'),
                'role'     => 'ADMIN_KEUANGAN',
            ],
            [
                'name'     => 'Kaprodi Informatika',
                'email'    => 'kaprodi.if@jawami.ac.id',
                'username' => 'kaprodi.if',
                'password' => Hash::make('password'),
                'role'     => 'DOSEN',
                'position' => 'KAPRODI',  // jabatan struktural
            ],
            [
                'name'     => 'Dosen Demo',
                'email'    => 'dosen@jawami.ac.id',
                'username' => 'dosen.demo',
                'password' => Hash::make('password'),
                'role'     => 'DOSEN',
            ],
            [
                'name'     => 'Dosen Wali Demo',
                'email'    => 'dosen.wali@jawami.ac.id',
                'username' => 'dosen.wali',
                'password' => Hash::make('password'),
                'role'     => 'DOSEN',
                'position' => 'DOSEN_WALI',  // jabatan struktural
            ],
            [
                'name'     => 'Mahasiswa Demo',
                'email'    => 'mahasiswa@jawami.ac.id',
                'username' => '2024001001',
                'password' => Hash::make('password'),
                'role'     => 'MAHASISWA',
            ],
            [
                'name'     => 'Alumni Demo',
                'email'    => 'alumni@jawami.ac.id',
                'username' => 'alumni.demo',
                'password' => Hash::make('password'),
                'role'     => 'ALUMNI',
            ],
            [
                'name'     => 'LPM Demo',
                'email'    => 'lpm@jawami.ac.id',
                'username' => 'lpm.demo',
                'password' => Hash::make('password'),
                'role'     => 'DOSEN',
                'position' => 'KETUA_LPM',  // jabatan struktural
            ],
            [
                'name'     => 'LP2M Demo',
                'email'    => 'lp2m@jawami.ac.id',
                'username' => 'lp2m.demo',
                'password' => Hash::make('password'),
                'role'     => 'LP2M',
            ],
        ];

        foreach ($users as $data) {
            $user = User::where('email', $data['email'])
                ->orWhere('username', $data['username'])
                ->first();

            if (!$user) {
                $user = User::create([
                    'name'      => $data['name'],
                    'email'     => $data['email'],
                    'username'  => $data['username'],
                    'password'  => $data['password'],
                    'is_active' => true,
                ]);
            }

            $user->syncRoles([$data['role']]);

            // Assign jabatan struktural jika ada, dan user punya data dosen
            if (!empty($data['position'])) {
                $lecturer = Lecturer::where('user_id', $user->id)->first();
                if ($lecturer) {
                    LecturerPosition::firstOrCreate(
                        ['lecturer_id' => $lecturer->id, 'position_code' => $data['position'], 'is_active' => true],
                        ['position_name' => LecturerPosition::POSITIONS[$data['position']] ?? $data['position']]
                    );
                }
            }
        }
    }
}
