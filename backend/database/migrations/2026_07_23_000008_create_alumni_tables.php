<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. DATA ALUMNI
        Schema::create('alumni', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->foreignId('study_program_id')->constrained('study_programs');
            $table->string('nim', 20);
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone', 20)->nullable();
            $table->integer('entry_year');
            $table->integer('graduation_year');
            $table->string('graduation_date')->nullable();
            $table->decimal('gpa', 4, 2)->nullable();
            $table->string('thesis_title')->nullable();
            $table->string('predicate', 50)->nullable();
            $table->string('photo_path')->nullable();
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('province', 100)->nullable();
            $table->boolean('is_active')->default(true); // Masih bisa dihubungi
            $table->timestamps();

            $table->index(['study_program_id', 'graduation_year']);
        });

        // 2. RIWAYAT PEKERJAAN
        Schema::create('alumni_employments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('alumni_id')->constrained('alumni')->cascadeOnDelete();
            $table->string('company_name');
            $table->string('position');
            $table->string('industry', 100)->nullable();
            $table->string('city', 100)->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->boolean('is_current')->default(false);
            $table->decimal('salary_range', 14, 0)->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // 3. TRACER STUDY
        Schema::create('tracer_studies', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('alumni_id')->constrained('alumni')->cascadeOnDelete();
            $table->foreignId('period_id')->nullable()->constrained('academic_years')->nullOnDelete();
            $table->enum('employment_status', ['BEKERJA', 'WIRAUSAHA', 'MELANJUTKAN_STUDI', 'BELUM_BEKERJA', 'LAINNYA'])->nullable();
            $table->integer('months_to_first_job')->nullable();
            $table->string('first_job_relevance', 50)->nullable(); // SANGAT_RELEVAN, RELEVAN, KURANG_RELEVAN, TIDAK_RELEVAN
            $table->decimal('first_salary', 14, 0)->nullable();
            $table->decimal('current_salary', 14, 0)->nullable();
            $table->text('competency_feedback')->nullable();
            $table->text('curriculum_feedback')->nullable();
            $table->text('suggestion')->nullable();
            $table->integer('satisfaction_score')->nullable(); // 1-5
            $table->boolean('is_completed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        // 4. PENDIDIKAN LANJUT
        Schema::create('alumni_further_studies', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('alumni_id')->constrained('alumni')->cascadeOnDelete();
            $table->string('institution');
            $table->string('program');
            $table->string('degree', 20); // S2, S3, Profesi
            $table->integer('entry_year')->nullable();
            $table->integer('graduation_year')->nullable();
            $table->boolean('is_current')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alumni_further_studies');
        Schema::dropIfExists('tracer_studies');
        Schema::dropIfExists('alumni_employments');
        Schema::dropIfExists('alumni');
    }
};
