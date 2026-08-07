<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Hapus semua letter_types lama
        DB::table('letter_types')->truncate();

        // Insert kode baru
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
        DB::table('letter_types')->truncate();

        $types = [
            ['code' => 'A',  'name' => 'Surat Rutin Internal'],
            ['code' => 'B',  'name' => 'Surat Rutin Eksternal'],
            ['code' => 'C',  'name' => 'Surat Keterangan'],
            ['code' => 'D',  'name' => 'Surat Rekomendasi'],
            ['code' => 'E',  'name' => 'Surat Tugas'],
            ['code' => 'F',  'name' => 'Surat Mandat'],
            ['code' => 'G',  'name' => 'Surat Peringatan'],
            ['code' => 'H',  'name' => 'Surat Edaran'],
            ['code' => 'I',  'name' => 'Surat Pengumuman'],
            ['code' => 'SK', 'name' => 'Surat Keputusan'],
        ];

        foreach ($types as $t) {
            DB::table('letter_types')->insert(
                array_merge($t, ['is_active' => true, 'created_at' => now(), 'updated_at' => now()])
            );
        }
    }
};
