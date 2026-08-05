<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UpdateDosenPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $dosen = Role::findByName('DOSEN');

        // Hapus master-data.view dari dosen (tidak perlu akses master data)
        $dosen->revokePermissionTo('master-data.view');

        // Pastikan permission RPS dan nilai tetap ada
        $permissions = ['rps.view', 'rps.create', 'rps.edit', 'nilai.view'];
        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }
        $dosen->givePermissionTo($permissions);

        $this->command->info('Permission DOSEN diupdate: master-data.view dihapus, rps.* & nilai.view dipertahankan.');
    }
}
