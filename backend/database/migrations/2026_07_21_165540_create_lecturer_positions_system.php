<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        // Tabel jabatan struktural yang bisa dipegang dosen
        Schema::create('lecturer_positions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('lecturer_id')->constrained('lecturers')->cascadeOnDelete();
            $table->string('position_code', 30);  // KETUA, WK1, WK2, WK3, REKTOR, dll
            $table->string('position_name');       // Nama lengkap jabatan
            // Scope jabatan (untuk Kaprodi/Sekprodi → prodi mana, Dekan → fakultas mana)
            $table->string('scope_type')->nullable();  // study_program, faculty, institution
            $table->unsignedBigInteger('scope_id')->nullable();  // ID prodi/fakultas
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('decree_number')->nullable();  // Nomor SK
            $table->timestamps();

            $table->index(['lecturer_id', 'is_active']);
            $table->index(['position_code', 'is_active']);
        });

        // Tabel mapping jabatan → permissions
        Schema::create('position_permissions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('position_code', 30);
            $table->string('permission_name');
            $table->timestamps();

            $table->unique(['position_code', 'permission_name']);
        });

        // Isi mapping jabatan → permissions
        $this->seedPositionPermissions();

        // Hapus role lama yang sudah digantikan oleh jabatan
        $rolesToRemove = ['PIMPINAN', 'KAPRODI', 'DOSEN_WALI', 'LPM'];
        foreach ($rolesToRemove as $roleName) {
            $role = Role::where('name', $roleName)->first();
            if ($role) {
                // Pindahkan user yang punya role ini ke DOSEN
                DB::table('model_has_roles')
                    ->where('role_id', $role->id)
                    ->update(['role_id' => Role::where('name', 'DOSEN')->value('id')]);
                $role->delete();
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('position_permissions');
        Schema::dropIfExists('lecturer_positions');

        // Recreate removed roles
        foreach (['PIMPINAN', 'KAPRODI', 'DOSEN_WALI', 'LPM'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }

    private function seedPositionPermissions(): void
    {
        $mappings = [
            // Pimpinan PT (Ketua/Rektor) - Dashboard strategis
            'KETUA' => ['dashboard.view', 'dashboard.analytics', 'master-data.view', 'mahasiswa.view', 'kurikulum.view', 'rps.view', 'krs.view', 'jadwal.view', 'presensi.view', 'nilai.view', 'khs.view', 'keuangan.view', 'skripsi.view', 'yudisium.view', 'wisuda.view', 'alumni.view', 'lpm.view', 'lpm.report'],
            'REKTOR' => ['dashboard.view', 'dashboard.analytics', 'master-data.view', 'mahasiswa.view', 'kurikulum.view', 'rps.view', 'krs.view', 'jadwal.view', 'presensi.view', 'nilai.view', 'khs.view', 'keuangan.view', 'skripsi.view', 'yudisium.view', 'wisuda.view', 'alumni.view', 'lpm.view', 'lpm.report'],

            // Wakil Ketua/Rektor I - Bidang Akademik
            'WK1' => ['dashboard.view', 'dashboard.analytics', 'kurikulum.view', 'kurikulum.create', 'kurikulum.edit', 'rps.view', 'rps.approve', 'krs.view', 'krs.approve', 'jadwal.view', 'jadwal.create', 'jadwal.edit', 'presensi.view', 'nilai.view', 'khs.view', 'khs.generate', 'mahasiswa.view', 'skripsi.view', 'skripsi.approve', 'yudisium.view', 'yudisium.create'],
            'WR1' => ['dashboard.view', 'dashboard.analytics', 'kurikulum.view', 'kurikulum.create', 'kurikulum.edit', 'rps.view', 'rps.approve', 'krs.view', 'krs.approve', 'jadwal.view', 'jadwal.create', 'jadwal.edit', 'presensi.view', 'nilai.view', 'khs.view', 'khs.generate', 'mahasiswa.view', 'skripsi.view', 'skripsi.approve', 'yudisium.view', 'yudisium.create'],

            // Wakil Ketua/Rektor II - Bidang Administrasi & Keuangan
            'WK2' => ['dashboard.view', 'dashboard.analytics', 'keuangan.view', 'keuangan.create', 'keuangan.edit', 'keuangan.delete', 'mahasiswa.view', 'master-data.view'],
            'WR2' => ['dashboard.view', 'dashboard.analytics', 'keuangan.view', 'keuangan.create', 'keuangan.edit', 'keuangan.delete', 'mahasiswa.view', 'master-data.view'],

            // Wakil Ketua/Rektor III - Bidang Kemahasiswaan & Alumni
            'WK3' => ['dashboard.view', 'dashboard.analytics', 'mahasiswa.view', 'alumni.view', 'alumni.edit', 'kkn.view', 'kkn.create', 'kkn.edit', 'wisuda.view'],
            'WR3' => ['dashboard.view', 'dashboard.analytics', 'mahasiswa.view', 'alumni.view', 'alumni.edit', 'kkn.view', 'kkn.create', 'kkn.edit', 'wisuda.view'],

            // Dekan
            'DEKAN' => ['dashboard.view', 'dashboard.analytics', 'kurikulum.view', 'rps.view', 'rps.approve', 'krs.view', 'jadwal.view', 'presensi.view', 'nilai.view', 'khs.view', 'mahasiswa.view', 'master-data.view', 'skripsi.view', 'yudisium.view'],
            'WADEK1' => ['dashboard.view', 'kurikulum.view', 'kurikulum.edit', 'rps.view', 'rps.approve', 'jadwal.view', 'jadwal.edit', 'presensi.view', 'nilai.view', 'khs.view', 'mahasiswa.view'],
            'WADEK2' => ['dashboard.view', 'keuangan.view', 'mahasiswa.view', 'master-data.view'],
            'WADEK3' => ['dashboard.view', 'mahasiswa.view', 'alumni.view', 'kkn.view'],

            // Ketua & Sekretaris Program Studi (akses data prodi sendiri)
            'KAPRODI' => ['dashboard.view', 'kurikulum.view', 'kurikulum.create', 'kurikulum.edit', 'rps.view', 'rps.approve', 'krs.view', 'krs.approve', 'jadwal.view', 'jadwal.create', 'jadwal.edit', 'presensi.view', 'nilai.view', 'khs.view', 'mahasiswa.view', 'master-data.view', 'skripsi.view', 'skripsi.approve', 'yudisium.view', 'wisuda.view', 'lpm.view'],
            'SEKPRODI' => ['dashboard.view', 'kurikulum.view', 'kurikulum.edit', 'rps.view', 'jadwal.view', 'jadwal.create', 'jadwal.edit', 'presensi.view', 'nilai.view', 'khs.view', 'mahasiswa.view', 'master-data.view'],

            // Dosen Wali (akses perwalian)
            'DOSEN_WALI' => ['dashboard.view', 'krs.view', 'krs.approve', 'bimbingan.view', 'bimbingan.create', 'bimbingan.edit', 'mahasiswa.view', 'nilai.view', 'khs.view', 'jadwal.view'],

            // LPM
            'KETUA_LPM' => ['dashboard.view', 'dashboard.analytics', 'lpm.view', 'lpm.audit', 'lpm.report', 'kurikulum.view', 'rps.view', 'presensi.view', 'nilai.view', 'khs.view', 'mahasiswa.view', 'master-data.view'],
            'SEKRETARIS_LPM' => ['dashboard.view', 'lpm.view', 'lpm.audit', 'lpm.report', 'kurikulum.view', 'rps.view', 'presensi.view', 'nilai.view', 'khs.view', 'mahasiswa.view', 'master-data.view'],

            // LP2M
            'KETUA_LP2M' => ['dashboard.view', 'dashboard.analytics', 'mahasiswa.view', 'kkn.view', 'kkn.create', 'kkn.edit'],
            'SEKRETARIS_LP2M' => ['dashboard.view', 'mahasiswa.view', 'kkn.view', 'kkn.create'],
        ];

        foreach ($mappings as $code => $permissions) {
            foreach ($permissions as $perm) {
                DB::table('position_permissions')->insert([
                    'position_code'   => $code,
                    'permission_name' => $perm,
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ]);
            }
        }
    }
};
