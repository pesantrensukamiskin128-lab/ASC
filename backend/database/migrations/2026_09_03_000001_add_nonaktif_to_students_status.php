<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table): void {
            $table->enum('status', [
                'Aktif',
                'Nonaktif',
                'Cuti',
                'Lulus',
                'DO',
                'Mengundurkan Diri',
            ])->default('Aktif')->change();
        });
    }

    public function down(): void
    {
        // Nilai Nonaktif harus dikonversi sebelum enum lama dipulihkan.
        DB::table('students')->where('status', 'Nonaktif')->update(['status' => 'Cuti']);

        Schema::table('students', function (Blueprint $table): void {
            $table->enum('status', [
                'Aktif',
                'Cuti',
                'Lulus',
                'DO',
                'Mengundurkan Diri',
            ])->default('Aktif')->change();
        });
    }
};
