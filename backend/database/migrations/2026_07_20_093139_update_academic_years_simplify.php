<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('academic_years', function (Blueprint $table) {
            // Hapus kolom yang tidak diperlukan di skema baru
            // start_date & end_date tetap ada, name tetap ada, is_active tetap ada
            $table->dropColumn(['code', 'semester', 'year_start', 'year_end']);
        });

        // Pastikan start_date & end_date tidak nullable
        Schema::table('academic_years', function (Blueprint $table) {
            $table->date('start_date')->nullable()->change();
            $table->date('end_date')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('academic_years', function (Blueprint $table) {
            $table->string('code', 20)->nullable()->unique()->after('id');
            $table->enum('semester', ['Ganjil', 'Genap', 'Pendek'])->nullable()->after('name');
            $table->year('year_start')->nullable()->after('semester');
            $table->year('year_end')->nullable()->after('year_start');
        });
    }
};
