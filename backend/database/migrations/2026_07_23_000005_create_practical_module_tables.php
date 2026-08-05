<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. PROGRAM PRAKTIKUM
        Schema::create('practical_programs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->enum('program_type', ['KKN', 'PPL', 'MAGANG', 'PRAKTIKUM', 'PKL']);
            $table->foreignId('semester_id')->constrained('semesters');
            $table->foreignId('study_program_id')->nullable()->constrained('study_programs')->nullOnDelete();
            $table->text('description')->nullable();
            $table->date('registration_start')->nullable();
            $table->date('registration_end')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->integer('min_credits')->nullable(); // Minimum SKS untuk daftar
            $table->integer('credit_value')->default(0); // Bobot SKS program
            $table->boolean('is_active')->default(true);
            $table->foreignId('coordinator_id')->nullable()->constrained('lecturers')->nullOnDelete();
            $table->timestamps();
        });

        // 2. LOKASI PENEMPATAN
        Schema::create('practical_locations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('program_id')->constrained('practical_programs')->cascadeOnDelete();
            $table->string('name');
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('contact_person')->nullable();
            $table->string('contact_phone', 20)->nullable();
            $table->integer('capacity')->nullable();
            $table->foreignId('supervisor_id')->nullable()->constrained('lecturers')->nullOnDelete();
            $table->timestamps();
        });

        // 3. KELOMPOK
        Schema::create('practical_groups', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('program_id')->constrained('practical_programs')->cascadeOnDelete();
            $table->string('name');
            $table->foreignId('location_id')->nullable()->constrained('practical_locations')->nullOnDelete();
            $table->foreignId('supervisor_id')->nullable()->constrained('lecturers')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 4. PESERTA
        Schema::create('practical_participants', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('program_id')->constrained('practical_programs')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('group_id')->nullable()->constrained('practical_groups')->nullOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('practical_locations')->nullOnDelete();
            $table->foreignId('supervisor_id')->nullable()->constrained('lecturers')->nullOnDelete();
            $table->enum('status', ['TERDAFTAR', 'AKTIF', 'SELESAI', 'MENGUNDURKAN_DIRI', 'GAGAL'])->default('TERDAFTAR');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['program_id', 'student_id']);
        });

        // 5. LOGBOOK
        Schema::create('practical_logbooks', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('participant_id')->constrained('practical_participants')->cascadeOnDelete();
            $table->date('activity_date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->text('activity');
            $table->text('result')->nullable();
            $table->text('notes')->nullable();
            $table->string('attachment_path')->nullable();
            $table->enum('status', ['DRAFT', 'SUBMITTED', 'APPROVED', 'REVISION'])->default('DRAFT');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // 6. PRESENSI
        Schema::create('practical_attendances', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('participant_id')->constrained('practical_participants')->cascadeOnDelete();
            $table->date('attendance_date');
            $table->enum('status', ['HADIR', 'IZIN', 'SAKIT', 'ALPHA'])->default('HADIR');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['participant_id', 'attendance_date']);
        });

        // 7. PENILAIAN
        Schema::create('practical_assessments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('participant_id')->constrained('practical_participants')->cascadeOnDelete();
            $table->string('component'); // Laporan, Presentasi, Kinerja, dll
            $table->decimal('score', 5, 2)->nullable();
            $table->decimal('weight', 5, 2)->default(1); // Bobot komponen
            $table->text('notes')->nullable();
            $table->foreignId('assessed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // 8. LAPORAN AKHIR
        Schema::create('practical_reports', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('participant_id')->constrained('practical_participants')->cascadeOnDelete();
            $table->string('title');
            $table->text('abstract')->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_url')->nullable();
            $table->enum('status', ['DRAFT', 'SUBMITTED', 'REVISION', 'APPROVED'])->default('DRAFT');
            $table->text('reviewer_notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('practical_reports');
        Schema::dropIfExists('practical_assessments');
        Schema::dropIfExists('practical_attendances');
        Schema::dropIfExists('practical_logbooks');
        Schema::dropIfExists('practical_participants');
        Schema::dropIfExists('practical_groups');
        Schema::dropIfExists('practical_locations');
        Schema::dropIfExists('practical_programs');
    }
};
