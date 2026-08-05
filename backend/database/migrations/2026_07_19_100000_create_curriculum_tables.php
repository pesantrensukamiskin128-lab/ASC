<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Kurikulum per prodi per tahun
        Schema::create('curriculums', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('study_program_id')->constrained('study_programs')->cascadeOnDelete();
            $table->string('code')->unique();
            $table->string('name');
            $table->year('year');
            $table->text('description')->nullable();
            $table->enum('status', ['Draft', 'Aktif', 'Nonaktif'])->default('Draft');
            $table->timestamps();
        });

        // CPL - Capaian Pembelajaran Lulusan
        Schema::create('learning_outcomes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('curriculum_id')->constrained('curriculums')->cascadeOnDelete();
            $table->string('code', 20);   // CPL-01, CPL-02
            $table->enum('category', ['Sikap', 'Pengetahuan', 'Keterampilan Umum', 'Keterampilan Khusus']);
            $table->text('description');
            $table->timestamps();
        });

        // Mata kuliah dalam kurikulum beserta bobot SKS
        Schema::create('curriculum_courses', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('curriculum_id')->constrained('curriculums')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->integer('semester')->default(1);
            $table->boolean('is_required')->default(true);
            $table->timestamps();

            $table->unique(['curriculum_id', 'course_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('curriculum_courses');
        Schema::dropIfExists('learning_outcomes');
        Schema::dropIfExists('curriculums');
    }
};
