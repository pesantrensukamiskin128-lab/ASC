<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. PERGURUAN TINGGI ASAL
        Schema::create('transfer_source_institutions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('code', 50)->nullable();
            $table->string('accreditation', 20)->nullable();
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('province', 100)->nullable();
            $table->string('country', 50)->default('Indonesia');
            $table->timestamps();
        });

        // 2. APLIKASI TRANSFER NILAI
        Schema::create('transfer_credit_applications', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('uuid')->unique();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('source_institution_id')->nullable()->constrained('transfer_source_institutions')->nullOnDelete();
            $table->string('source_study_program')->nullable();
            $table->string('source_degree', 20)->nullable();
            $table->string('source_student_number', 50)->nullable();
            $table->integer('source_total_credits')->nullable();
            $table->decimal('source_gpa', 4, 2)->nullable();
            $table->integer('source_semesters')->nullable();
            $table->enum('transfer_type', ['EXTERNAL', 'INTERNAL', 'RPL'])->default('EXTERNAL');
            $table->date('application_date');
            $table->enum('status', [
                'DRAFT', 'SUBMITTED', 'DOCUMENT_VERIFICATION', 'ACADEMIC_EVALUATION',
                'REVISION', 'APPROVED', 'REJECTED', 'FINALIZED',
            ])->default('DRAFT');
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('admin_note')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'status']);
        });

        // 3. DOKUMEN TRANSFER
        Schema::create('transfer_documents', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('application_id')->constrained('transfer_credit_applications')->cascadeOnDelete();
            $table->string('document_type', 50); // TRANSKRIP_ASAL, SURAT_PINDAH, SILABUS, dll
            $table->string('name');
            $table->string('file_path')->nullable();
            $table->string('file_url')->nullable();
            $table->boolean('is_verified')->default(false);
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('verification_notes')->nullable();
            $table->timestamps();
        });

        // 4. MATA KULIAH ASAL
        Schema::create('transfer_source_courses', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('application_id')->constrained('transfer_credit_applications')->cascadeOnDelete();
            $table->string('course_code', 30)->nullable();
            $table->string('course_name');
            $table->decimal('credits', 4, 1);
            $table->string('grade_letter', 5)->nullable();
            $table->decimal('grade_numeric', 4, 2)->nullable();
            $table->string('semester_taken', 50)->nullable();
            $table->string('year_taken', 20)->nullable();
            $table->timestamps();
        });

        // 5. KONVERSI MATA KULIAH
        Schema::create('transfer_course_conversions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('application_id')->constrained('transfer_credit_applications')->cascadeOnDelete();
            $table->foreignId('source_course_id')->constrained('transfer_source_courses')->cascadeOnDelete();
            $table->foreignId('target_course_id')->constrained('courses');
            $table->decimal('source_credits', 4, 1);
            $table->decimal('target_credits', 4, 1);
            $table->decimal('recognized_credits', 4, 1);
            $table->string('source_grade', 5)->nullable();
            $table->decimal('source_grade_point', 4, 2)->nullable();
            $table->string('converted_grade', 5)->nullable();
            $table->decimal('converted_grade_point', 4, 2)->nullable();
            $table->enum('conversion_type', ['DIRECT', 'PARTIAL', 'COMBINATION', 'ELECTIVE', 'REJECTED'])->default('DIRECT');
            $table->enum('status', ['PENDING', 'APPROVED', 'REJECTED'])->default('PENDING');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 6. EVALUASI AKADEMIK
        Schema::create('transfer_evaluations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('application_id')->constrained('transfer_credit_applications')->cascadeOnDelete();
            $table->foreignId('evaluator_id')->constrained('users');
            $table->date('evaluation_date');
            $table->integer('total_source_credits')->default(0);
            $table->integer('total_recognized_credits')->default(0);
            $table->integer('total_rejected_credits')->default(0);
            $table->text('notes')->nullable();
            $table->enum('recommendation', ['ACCEPT', 'ACCEPT_WITH_CONDITIONS', 'REJECT'])->nullable();
            $table->timestamps();
        });

        // 7. PERSETUJUAN BERJENJANG
        Schema::create('transfer_approvals', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('application_id')->constrained('transfer_credit_applications')->cascadeOnDelete();
            $table->foreignId('approver_id')->constrained('users');
            $table->integer('approval_level')->default(1);
            $table->string('approval_role', 50);
            $table->enum('status', ['PENDING', 'APPROVED', 'REJECTED'])->default('PENDING');
            $table->text('notes')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['application_id', 'approval_level']);
        });

        // 8. NILAI TRANSFER (masuk transkrip)
        Schema::create('transferred_grades', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('application_id')->constrained('transfer_credit_applications')->cascadeOnDelete();
            $table->foreignId('source_course_id')->constrained('transfer_source_courses');
            $table->foreignId('target_course_id')->constrained('courses');
            $table->decimal('recognized_credits', 4, 1);
            $table->string('grade_letter', 5);
            $table->decimal('grade_point', 4, 2);
            $table->string('semester_label', 50)->nullable();
            $table->boolean('is_included_in_gpa')->default(false);
            $table->boolean('is_included_in_transcript')->default(true);
            $table->boolean('is_included_in_total_credits')->default(true);
            $table->timestamps();

            $table->index(['student_id', 'target_course_id']);
        });

        // 9. PENEMPATAN SEMESTER
        Schema::create('student_academic_placements', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('application_id')->constrained('transfer_credit_applications')->cascadeOnDelete();
            $table->integer('recommended_semester');
            $table->integer('approved_semester')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 10. KEBIJAKAN TRANSFER
        Schema::create('transfer_policies', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('study_program_id')->nullable()->constrained('study_programs')->nullOnDelete();
            $table->decimal('minimum_gpa', 4, 2)->default(2.00);
            $table->integer('minimum_credits')->default(20);
            $table->integer('maximum_credits')->default(100);
            $table->decimal('maximum_transfer_percentage', 5, 2)->default(60.00);
            $table->boolean('requires_syllabus')->default(false);
            $table->boolean('requires_official_transcript')->default(true);
            $table->boolean('include_in_gpa')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfer_policies');
        Schema::dropIfExists('student_academic_placements');
        Schema::dropIfExists('transferred_grades');
        Schema::dropIfExists('transfer_approvals');
        Schema::dropIfExists('transfer_evaluations');
        Schema::dropIfExists('transfer_course_conversions');
        Schema::dropIfExists('transfer_source_courses');
        Schema::dropIfExists('transfer_documents');
        Schema::dropIfExists('transfer_credit_applications');
        Schema::dropIfExists('transfer_source_institutions');
    }
};
