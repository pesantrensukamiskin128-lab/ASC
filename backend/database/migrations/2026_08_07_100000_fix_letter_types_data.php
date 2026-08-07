<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Disable FK check dan hapus data lama
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('letter_types')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // Insert kode jenis surat baru
        $types = [
            ['code' => 'A.1', 'name' => 'Surat Rutin Internal'],
            ['code' => 'A.2', 'name' => 'Surat Keterangan'],
            ['code' => 'A.3', 'name' => 'Surat Rekomendasi'],
            ['code' => 'A.4', 'name' => 'Surat Tugas'],
            ['code' => 'A.5', 'name' => 'Surat Peringatan'],
            ['code' => 'A.6', 'name' => 'Surat Edaran'],
            ['code' => 'A.7', 'name' => 'Surat Pengumuman'],
            ['code' => 'SK',  'name' => 'Surat Keputusan'],
            ['code' => 'B.1', 'name' => 'Surat Rutin Eksternal'],
            ['code' => 'B.2', 'name' => 'Surat Keterangan Eksternal'],
            ['code' => 'B.3', 'name' => 'Surat Rekomendasi Eksternal'],
            ['code' => 'B.4', 'name' => 'Surat Tugas Eksternal'],
            ['code' => 'B.5', 'name' => 'Surat Pengumuman Eksternal'],
        ];

        foreach ($types as $t) {
            DB::table('letter_types')->insert(
                array_merge($t, ['is_active' => true, 'created_at' => now(), 'updated_at' => now()])
            );
        }
    }

    public function down(): void
    {
        // no-op
    }
};
