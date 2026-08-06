<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    public function up(): void
    {
        // Tambah role baru
        Role::firstOrCreate(['name' => 'ADMIN_UMUM', 'guard_name' => 'web']);    // Admin Administrasi Umum
        Role::firstOrCreate(['name' => 'KEPALA_TU', 'guard_name' => 'web']);     // Kepala Tata Usaha

        // Tambah permissions untuk modul persuratan & agenda
        $permissions = [
            // Surat Keluar
            'surat-keluar.view', 'surat-keluar.create', 'surat-keluar.edit',
            'surat-keluar.review', 'surat-keluar.sign', 'surat-keluar.send',
            // Surat Masuk
            'surat-masuk.view', 'surat-masuk.create',
            // Disposisi
            'disposisi.view', 'disposisi.create', 'disposisi.receive',
            // Agenda & Presensi
            'agenda.view', 'agenda.create', 'agenda.edit',
            'presensi.view', 'presensi.create',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        // Assign permissions ke role
        $superAdmin = Role::findByName('SUPER_ADMIN');
        $superAdmin->givePermissionTo($permissions);

        Role::findByName('ADMIN_UMUM')->givePermissionTo([
            'surat-keluar.view', 'surat-keluar.create', 'surat-keluar.edit', 'surat-keluar.send',
            'surat-masuk.view', 'surat-masuk.create',
            'disposisi.view', 'disposisi.create',
            'agenda.view', 'agenda.create', 'agenda.edit',
            'presensi.view', 'presensi.create',
        ]);

        Role::findByName('KEPALA_TU')->givePermissionTo([
            'surat-keluar.view', 'surat-keluar.review',
            'surat-masuk.view', 'surat-masuk.create',
            'disposisi.view', 'disposisi.create',
            'agenda.view',
            'presensi.view',
        ]);

        // Dosen bisa menerima surat & disposisi dan melihat agenda
        Role::findByName('DOSEN')->givePermissionTo([
            'surat-masuk.view',
            'disposisi.view', 'disposisi.receive',
            'agenda.view',
            'presensi.view', 'presensi.create',
        ]);

        // Mahasiswa bisa lihat agenda dan presensi
        Role::findByName('MAHASISWA')->givePermissionTo([
            'agenda.view',
            'presensi.view', 'presensi.create',
        ]);

        // Tambah permission tanda tangan ke jabatan struktural
        $signerPositions = [
            'KETUA', 'REKTOR', 'WK1', 'WR1', 'WK2', 'WR2', 'WK3', 'WR3',
            'DEKAN', 'WADEK1', 'WADEK2', 'WADEK3', 'KAPRODI',
        ];
        foreach ($signerPositions as $code) {
            DB::table('position_permissions')->insertOrIgnore([
                'position_code'   => $code,
                'permission_name' => 'surat-keluar.sign',
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);
            DB::table('position_permissions')->insertOrIgnore([
                'position_code'   => $code,
                'permission_name' => 'surat-keluar.view',
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);
            DB::table('position_permissions')->insertOrIgnore([
                'position_code'   => $code,
                'permission_name' => 'disposisi.create',
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);
        }
    }

    public function down(): void
    {
        $permissions = [
            'surat-keluar.view', 'surat-keluar.create', 'surat-keluar.edit',
            'surat-keluar.review', 'surat-keluar.sign', 'surat-keluar.send',
            'surat-masuk.view', 'surat-masuk.create',
            'disposisi.view', 'disposisi.create', 'disposisi.receive',
            'agenda.view', 'agenda.create', 'agenda.edit',
            'presensi.view', 'presensi.create',
        ];

        foreach ($permissions as $perm) {
            Permission::where('name', $perm)->delete();
        }

        Role::where('name', 'ADMIN_UMUM')->delete();
        Role::where('name', 'KEPALA_TU')->delete();

        DB::table('position_permissions')
            ->whereIn('permission_name', ['surat-keluar.sign', 'surat-keluar.view', 'disposisi.create'])
            ->delete();
    }
};
