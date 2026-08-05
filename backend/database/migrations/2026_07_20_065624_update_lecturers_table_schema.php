<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lecturers', function (Blueprint $table) {
            // Rename kolom lama → nama baru
            $table->renameColumn('name', 'full_name');
            $table->renameColumn('functional_position', 'academic_rank');
            $table->renameColumn('last_education', 'academic_degree');
        });

        Schema::table('lecturers', function (Blueprint $table) {
            // Tambah kolom baru (tidak ada di skema lama)
            $table->string('nuptk', 20)->nullable()->unique()->after('nidn');
            $table->string('photo_path')->nullable()->after('phone');
            // birth_place dan address tetap dipertahankan
        });
    }

    public function down(): void
    {
        Schema::table('lecturers', function (Blueprint $table) {
            $table->dropColumn(['nuptk', 'photo_path']);
        });

        Schema::table('lecturers', function (Blueprint $table) {
            $table->renameColumn('full_name', 'name');
            $table->renameColumn('academic_rank', 'functional_position');
            $table->renameColumn('academic_degree', 'last_education');
        });
    }
};
