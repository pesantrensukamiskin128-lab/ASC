<?php

namespace Database\Seeders;

use App\Models\User;
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
                'role'     => 'PIMPINAN',
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
                'role'     => 'KAPRODI',
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
                'role'     => 'DOSEN_WALI',
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
                'role'     => 'LPM',
            ],
        ];

        foreach ($users as $data) {
            $user = User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name'      => $data['name'],
                    'username'  => $data['username'],
                    'password'  => $data['password'],
                    'is_active' => true,
                ]
            );
            $user->syncRoles([$data['role']]);
        }
    }
}
