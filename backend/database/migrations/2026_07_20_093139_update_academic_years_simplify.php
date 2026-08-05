<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration ini sudah di-merge ke dalam create_academic_years_table.
 * Dibiarkan sebagai no-op agar history migration tidak berubah
 * (menghapus file migration bisa menyebabkan error pada DB yang sudah jalan).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Kolom code, semester, year_start, year_end sudah tidak dibuat
        // sejak create_academic_years_table diupdate — tidak ada yang perlu di-drop.
        // Jika migrasi ini berjalan di DB lama yang masih punya kolom tersebut,
        // hapus kondisional di bawah ini.
        Schema::table('academic_years', function (Blueprint $table) {
            foreach (['code', 'semester', 'year_start', 'year_end'] as $col) {
                if (Schema::hasColumn('academic_years', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        // Pastikan start_date & end_date nullable
        if (Schema::hasTable('academic_years')) {
            Schema::table('academic_years', function (Blueprint $table) {
                $table->date('start_date')->nullable()->change();
                $table->date('end_date')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        Schema::table('academic_years', function (Blueprint $table) {
            if (!Schema::hasColumn('academic_years', 'code')) {
                $table->string('code', 20)->nullable()->unique()->after('id');
            }
            if (!Schema::hasColumn('academic_years', 'semester')) {
                $table->enum('semester', ['Ganjil', 'Genap', 'Pendek'])->nullable()->after('name');
            }
            if (!Schema::hasColumn('academic_years', 'year_start')) {
                $table->year('year_start')->nullable()->after('semester');
            }
            if (!Schema::hasColumn('academic_years', 'year_end')) {
                $table->year('year_end')->nullable()->after('year_start');
            }
        });
    }
};
