<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        // Update permission mapping KETUA_LP2M agar selengkap role LP2M
        $lp2mPermissions = [
            'dashboard.view', 'dashboard.analytics',
            'karya.view', 'karya.verify', 'karya.publish',
            'skripsi.view', 'skripsi.publish',
            'kkn.view', 'kkn.create', 'kkn.edit',
            'mahasiswa.view', 'master-data.view',
        ];

        $sekretarisPermissions = [
            'dashboard.view',
            'karya.view', 'karya.verify',
            'skripsi.view',
            'kkn.view', 'kkn.create', 'kkn.edit',
            'mahasiswa.view', 'master-data.view',
        ];

        // Hapus mapping lama
        DB::table('position_permissions')->where('position_code', 'KETUA_LP2M')->delete();
        DB::table('position_permissions')->where('position_code', 'SEKRETARIS_LP2M')->delete();

        // Insert mapping baru
        foreach ($lp2mPermissions as $perm) {
            DB::table('position_permissions')->insert([
                'position_code'   => 'KETUA_LP2M',
                'permission_name' => $perm,
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);
        }

        foreach ($sekretarisPermissions as $perm) {
            DB::table('position_permissions')->insert([
                'position_code'   => 'SEKRETARIS_LP2M',
                'permission_name' => $perm,
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);
        }

        // Migrasi user yang punya role LP2M → role DOSEN + jabatan KETUA_LP2M
        $lp2mRole = Role::where('name', 'LP2M')->first();
        if ($lp2mRole) {
            $dosenRole = Role::where('name', 'DOSEN')->first();
            if ($dosenRole) {
                DB::table('model_has_roles')
                    ->where('role_id', $lp2mRole->id)
                    ->update(['role_id' => $dosenRole->id]);
            }
            $lp2mRole->delete();
        }
    }

    public function down(): void
    {
        // Recreate LP2M role
        Role::firstOrCreate(['name' => 'LP2M', 'guard_name' => 'web']);

        // Restore original position permissions
        DB::table('position_permissions')->where('position_code', 'KETUA_LP2M')->delete();
        DB::table('position_permissions')->where('position_code', 'SEKRETARIS_LP2M')->delete();

        foreach (['dashboard.view', 'dashboard.analytics', 'mahasiswa.view', 'kkn.view', 'kkn.create', 'kkn.edit'] as $perm) {
            DB::table('position_permissions')->insert([
                'position_code' => 'KETUA_LP2M', 'permission_name' => $perm,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        foreach (['dashboard.view', 'mahasiswa.view', 'kkn.view', 'kkn.create'] as $perm) {
            DB::table('position_permissions')->insert([
                'position_code' => 'SEKRETARIS_LP2M', 'permission_name' => $perm,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }
};
