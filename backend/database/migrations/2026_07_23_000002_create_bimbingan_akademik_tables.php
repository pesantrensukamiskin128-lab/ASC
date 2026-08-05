<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // =============================================
        // 1. SESI BIMBINGAN (guidance_sessions)
        // =============================================
        Schema::create('guidance_sessions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('advisor_id')->constrained('lecturers')->cascadeOnDelete();
            $table->foreignId('semester_id')->nullable()->constrained('semesters')->nullOnDelete();
            $table->string('topic');
            $table->text('description')->nullable();
            $table->enum('type', ['KONSULTASI', 'PERWALIAN', 'PERINGATAN', 'BIMBINGAN_TA', 'LAINNYA'])->default('KONSULTASI');
            $table->enum('mode', ['TATAP_MUKA', 'ONLINE', 'CHAT'])->default('TATAP_MUKA');
            $table->date('scheduled_date')->nullable();
            $table->time('scheduled_time')->nullable();
            $table->string('location')->nullable();
            $table->enum('status', ['DIAJUKAN', 'DIJADWALKAN', 'BERLANGSUNG', 'SELESAI', 'DIBATALKAN'])->default('DIAJUKAN');
            $table->foreignId('requested_by')->constrained('users');
            $table->timestamps();

            $table->index(['student_id', 'advisor_id', 'status']);
        });

        // =============================================
        // 2. CATATAN BIMBINGAN (guidance_notes)
        // =============================================
        Schema::create('guidance_notes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('session_id')->constrained('guidance_sessions')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->text('content');
            $table->string('attachment_path')->nullable();
            $table->boolean('is_private')->default(false); // Hanya dosen bisa lihat
            $table->timestamps();
        });

        // =============================================
        // 3. CATATAN AKADEMIK (academic_notes)
        // Catatan dosen wali terhadap mahasiswa (permanen)
        // =============================================
        Schema::create('academic_notes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('advisor_id')->constrained('lecturers');
            $table->foreignId('semester_id')->nullable()->constrained('semesters')->nullOnDelete();
            $table->enum('type', ['UMUM', 'PERINGATAN', 'REKOMENDASI', 'PRESTASI', 'PELANGGARAN'])->default('UMUM');
            $table->text('content');
            $table->boolean('is_important')->default(false);
            $table->timestamps();

            $table->index(['student_id', 'semester_id']);
        });

        // =============================================
        // 4. PERINGATAN AKADEMIK (academic_warnings)
        // =============================================
        Schema::create('academic_warnings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('advisor_id')->nullable()->constrained('lecturers')->nullOnDelete();
            $table->foreignId('semester_id')->nullable()->constrained('semesters')->nullOnDelete();
            $table->enum('level', ['RINGAN', 'SEDANG', 'BERAT'])->default('RINGAN');
            $table->string('reason');
            $table->text('description')->nullable();
            $table->decimal('ipk', 4, 2)->nullable();
            $table->decimal('ips', 4, 2)->nullable();
            $table->boolean('requires_consultation')->default(true);
            $table->boolean('consultation_done')->default(false);
            $table->date('consultation_deadline')->nullable();
            $table->text('resolution_note')->nullable();
            $table->enum('status', ['AKTIF', 'PROSES', 'SELESAI'])->default('AKTIF');
            $table->timestamps();

            $table->index(['student_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_warnings');
        Schema::dropIfExists('academic_notes');
        Schema::dropIfExists('guidance_notes');
        Schema::dropIfExists('guidance_sessions');
    }
};
