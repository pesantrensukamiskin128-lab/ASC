<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Roles sesuai struktur organisasi
        $roles = [
            'SUPER_ADMIN',
            'PIMPINAN',
            'ADMIN_AKADEMIK',
            'ADMIN_PMB',
            'ADMIN_KEUANGAN',
            'KAPRODI',
            'DOSEN',
            'DOSEN_WALI',
            'MAHASISWA',
            'ALUMNI',
            'LPM',
            'LP2M',   // Lembaga Penelitian & Pengabdian Masyarakat
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        // Permissions per modul
        $permissions = [
            // User management
            'user.view', 'user.create', 'user.edit', 'user.delete',
            // Master Data
            'master-data.view', 'master-data.create', 'master-data.edit', 'master-data.delete',
            // PMB
            'pmb.view', 'pmb.create', 'pmb.edit', 'pmb.delete',
            // Mahasiswa
            'mahasiswa.view', 'mahasiswa.create', 'mahasiswa.edit', 'mahasiswa.delete',
            // Kurikulum & OBE
            'kurikulum.view', 'kurikulum.create', 'kurikulum.edit', 'kurikulum.delete',
            // RPKPS / RPS
            'rps.view', 'rps.create', 'rps.edit', 'rps.delete', 'rps.approve',
            // KRS & Perwalian
            'krs.view', 'krs.create', 'krs.edit', 'krs.approve',
            // Jadwal & Perkuliahan
            'jadwal.view', 'jadwal.create', 'jadwal.edit', 'jadwal.delete',
            // Presensi
            'presensi.view', 'presensi.create', 'presensi.edit',
            // Penilaian / Nilai
            'nilai.view', 'nilai.create', 'nilai.edit',
            // KHS & Transkrip
            'khs.view', 'khs.generate',
            // Keuangan
            'keuangan.view', 'keuangan.create', 'keuangan.edit', 'keuangan.delete',
            // Bimbingan Akademik
            'bimbingan.view', 'bimbingan.create', 'bimbingan.edit',
            // Skripsi / Tugas Akhir
            'skripsi.view', 'skripsi.create', 'skripsi.edit', 'skripsi.approve',
            // KKN / PPL / Magang
            'kkn.view', 'kkn.create', 'kkn.edit',
            // Cuti Akademik
            'cuti.view', 'cuti.create', 'cuti.edit', 'cuti.approve',
            // Yudisium
            'yudisium.view', 'yudisium.create', 'yudisium.edit',
            // Wisuda
            'wisuda.view', 'wisuda.create', 'wisuda.edit',
            // Alumni
            'alumni.view', 'alumni.edit',
            // Dashboard & Analitik
            'dashboard.view', 'dashboard.analytics',
            // LPM (Lembaga Penjaminan Mutu)
            'lpm.view', 'lpm.audit', 'lpm.report',
            // LP2M (Lembaga Penelitian & Pengabdian Masyarakat)
            'karya.view', 'karya.verify', 'karya.publish',
            'skripsi.publish',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // SUPER_ADMIN — akses penuh
        Role::findByName('SUPER_ADMIN')->givePermissionTo(Permission::all());

        // PIMPINAN — view semua, analytics
        Role::findByName('PIMPINAN')->givePermissionTo([
            'dashboard.view', 'dashboard.analytics',
            'master-data.view', 'mahasiswa.view',
            'kurikulum.view', 'rps.view', 'krs.view',
            'jadwal.view', 'presensi.view', 'nilai.view', 'khs.view',
            'keuangan.view', 'skripsi.view', 'yudisium.view', 'wisuda.view',
            'alumni.view', 'lpm.view', 'lpm.report',
        ]);

        // ADMIN_AKADEMIK — kelola data akademik
        Role::findByName('ADMIN_AKADEMIK')->givePermissionTo([
            'dashboard.view',
            'user.view', 'user.create', 'user.edit',
            'master-data.view', 'master-data.create', 'master-data.edit',
            'mahasiswa.view', 'mahasiswa.create', 'mahasiswa.edit',
            'kurikulum.view', 'kurikulum.create', 'kurikulum.edit',
            'jadwal.view', 'jadwal.create', 'jadwal.edit',
            'presensi.view', 'nilai.view', 'khs.view', 'khs.generate',
            'skripsi.view', 'yudisium.view', 'yudisium.create', 'yudisium.edit',
            'wisuda.view', 'wisuda.create', 'wisuda.edit',
            'cuti.view', 'cuti.edit', 'cuti.approve',
            'kkn.view', 'kkn.edit',
        ]);

        // ADMIN_PMB — kelola penerimaan mahasiswa baru
        Role::findByName('ADMIN_PMB')->givePermissionTo([
            'dashboard.view',
            'pmb.view', 'pmb.create', 'pmb.edit', 'pmb.delete',
            'mahasiswa.view', 'mahasiswa.create',
            'master-data.view',
        ]);

        // ADMIN_KEUANGAN — kelola keuangan
        Role::findByName('ADMIN_KEUANGAN')->givePermissionTo([
            'dashboard.view',
            'keuangan.view', 'keuangan.create', 'keuangan.edit', 'keuangan.delete',
            'mahasiswa.view', 'master-data.view',
        ]);

        // KAPRODI — kelola akademik prodi
        Role::findByName('KAPRODI')->givePermissionTo([
            'dashboard.view',
            'kurikulum.view', 'kurikulum.create', 'kurikulum.edit',
            'rps.view', 'rps.approve',
            'krs.view', 'krs.approve',
            'jadwal.view', 'jadwal.create', 'jadwal.edit',
            'presensi.view', 'nilai.view', 'khs.view',
            'mahasiswa.view', 'master-data.view',
            'skripsi.view', 'skripsi.approve',
            'yudisium.view', 'wisuda.view',
            'lpm.view',
        ]);

        // DOSEN — mengajar & membimbing
        Role::findByName('DOSEN')->givePermissionTo([
            'dashboard.view',
            'rps.view', 'rps.create', 'rps.edit',
            'jadwal.view', 'presensi.view', 'presensi.create', 'presensi.edit',
            'nilai.view', 'nilai.create', 'nilai.edit',
            'bimbingan.view', 'bimbingan.create', 'bimbingan.edit',
            'skripsi.view', 'skripsi.create', 'skripsi.edit',
            'kkn.view',
        ]);

        // DOSEN_WALI — perwalian & bimbingan akademik
        Role::findByName('DOSEN_WALI')->givePermissionTo([
            'dashboard.view',
            'krs.view', 'krs.approve',
            'bimbingan.view', 'bimbingan.create', 'bimbingan.edit',
            'mahasiswa.view', 'nilai.view', 'khs.view',
            'jadwal.view',
        ]);

        // MAHASISWA — akses data diri & akademik sendiri
        Role::findByName('MAHASISWA')->givePermissionTo([
            'dashboard.view',
            'krs.view', 'krs.create',
            'jadwal.view', 'presensi.view',
            'nilai.view', 'khs.view',
            'rps.view',
            'bimbingan.view', 'bimbingan.create',
            'skripsi.view', 'skripsi.create',
            'keuangan.view',
            'kkn.view', 'kkn.create',
            'cuti.view', 'cuti.create',
        ]);

        // ALUMNI — akses data alumni
        Role::findByName('ALUMNI')->givePermissionTo([
            'dashboard.view',
            'alumni.view', 'alumni.edit',
        ]);

        // LPM — audit mutu & pelaporan
        Role::findByName('LPM')->givePermissionTo([
            'dashboard.view', 'dashboard.analytics',
            'lpm.view', 'lpm.audit', 'lpm.report',
            'kurikulum.view', 'rps.view',
            'presensi.view', 'nilai.view', 'khs.view',
            'mahasiswa.view', 'master-data.view',
        ]);

        // LP2M — verifikasi & publikasi karya dosen dan skripsi mahasiswa + kelola KKN
        Role::firstOrCreate(['name' => 'LP2M', 'guard_name' => 'web']);
        Role::findByName('LP2M')->givePermissionTo([
            'dashboard.view',
            // Karya dosen: lihat, verifikasi, publikasi
            'karya.view', 'karya.verify', 'karya.publish',
            // Skripsi: lihat semua, publikasi ke repository
            'skripsi.view', 'skripsi.publish',
            // KKN / PPL / Magang / Praktikum: kelola penuh
            'kkn.view', 'kkn.create', 'kkn.edit',
            // Data pendukung
            'mahasiswa.view', 'master-data.view',
        ]);
    }
}
