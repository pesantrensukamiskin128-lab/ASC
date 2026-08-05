<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // =============================================
        // 1. Drop tabel lama (class_offerings, krs, krs_details)
        // =============================================
        Schema::dropIfExists('krs_details');
        Schema::dropIfExists('krs');
        Schema::dropIfExists('class_offerings');

        // =============================================
        // 2. Buat tabel classes (pengganti class_offerings)
        // =============================================
        Schema::create('classes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('study_program_id')->constrained('study_programs');
            $table->foreignId('semester_id')->constrained('semesters');
            $table->foreignId('course_id')->constrained('courses');
            $table->foreignId('lecturer_id')->constrained('lecturers');
            $table->foreignId('room_id')->nullable()->constrained('rooms')->nullOnDelete();
            $table->string('name', 50);             // Kelas A, Kelas B, dll
            $table->integer('capacity')->default(40);
            $table->integer('academic_level')->default(1); // Tingkat / angkatan
            $table->enum('day', ['Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'])->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['semester_id', 'course_id', 'name']);
        });

        // 3. Anggota kelas (pembagian mahasiswa ke kelas)
        Schema::create('class_members', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['class_id', 'student_id']);
        });

        // =============================================
        // 4. Buat tabel KRS baru (menggunakan semester_id)
        // =============================================
        Schema::create('krs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('semester_id')->constrained('semesters');
            $table->foreignId('advisor_id')->nullable()->constrained('lecturers')->nullOnDelete();
            $table->integer('total_credits')->default(0);
            $table->enum('status', ['DRAFT', 'SUBMITTED', 'APPROVED', 'REJECTED', 'CANCELLED'])->default('DRAFT');
            $table->text('advisor_note')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'semester_id']);
        });

        // 5. Detail KRS
        Schema::create('krs_details', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('krs_id')->constrained('krs')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained('courses');
            $table->foreignId('class_id')->nullable()->constrained('classes')->nullOnDelete();
            $table->enum('status', ['AKTIF', 'DIBATALKAN'])->default('AKTIF');
            $table->timestamps();

            $table->unique(['krs_id', 'course_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('krs_details');
        Schema::dropIfExists('krs');
        Schema::dropIfExists('class_members');
        Schema::dropIfExists('classes');

        // Recreate old tables
        Schema::create('class_offerings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnDelete();
            $table->foreignId('lecturer_id')->constrained('lecturers')->cascadeOnDelete();
            $table->foreignId('room_id')->nullable()->constrained('rooms')->nullOnDelete();
            $table->string('class_code', 20);
            $table->integer('max_students')->default(40);
            $table->integer('enrolled_count')->default(0);
            $table->enum('day', ['Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'])->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['course_id', 'academic_year_id', 'class_code']);
        });

        Schema::create('krs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnDelete();
            $table->foreignId('advisor_id')->nullable()->constrained('lecturers')->nullOnDelete();
            $table->integer('total_credits')->default(0);
            $table->enum('status', ['Draft', 'Submitted', 'Approved', 'Rejected'])->default('Draft');
            $table->text('advisor_note')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->unique(['student_id', 'academic_year_id']);
        });

        Schema::create('krs_details', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('krs_id')->constrained('krs')->cascadeOnDelete();
            $table->foreignId('class_offering_id')->constrained('class_offerings')->cascadeOnDelete();
            $table->enum('status', ['Aktif', 'Dibatalkan'])->default('Aktif');
            $table->timestamps();
            $table->unique(['krs_id', 'class_offering_id']);
        });
    }
};
