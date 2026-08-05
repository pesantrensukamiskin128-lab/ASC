<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lecturers', function (Blueprint $table) {
            // Tambah dua kolom pengganti academic_degree
            $table->string('degree_front', 50)->nullable()->after('nip');  // Dr., Prof., H.
            $table->string('degree_back', 100)->nullable()->after('degree_front'); // S.Kom., M.T., Ph.D.
        });

        // Migrasi data lama: pindahkan academic_degree ke degree_back
        DB::statement("UPDATE lecturers SET degree_back = academic_degree WHERE academic_degree IS NOT NULL AND academic_degree != ''");

        Schema::table('lecturers', function (Blueprint $table) {
            $table->dropColumn('academic_degree');
        });
    }

    public function down(): void
    {
        Schema::table('lecturers', function (Blueprint $table) {
            $table->string('academic_degree', 100)->nullable()->after('nip');
        });

        DB::statement("UPDATE lecturers SET academic_degree = CONCAT_WS(' ', NULLIF(degree_front,''), NULLIF(degree_back,'')) WHERE degree_front IS NOT NULL OR degree_back IS NOT NULL");

        Schema::table('lecturers', function (Blueprint $table) {
            $table->dropColumn(['degree_front', 'degree_back']);
        });
    }
};
