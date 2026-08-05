<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // =============================================
        // 1. CUTI AKADEMIK (academic_leaves)
        // =============================================
        Schema::create('academic_leaves', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('semester_id')->constrained('semesters');
            $table->enum('type', ['CUTI', 'PERPANJANGAN'])->default('CUTI');
            $table->text('reason');
            $table->string('document_path')->nullable();
            $table->enum('status', [
                'DRAFT', 'DIAJUKAN', 'DOSEN_WALI_APPROVED', 'DOSEN_WALI_REJECTED',
                'KAPRODI_APPROVED', 'KAPRODI_REJECTED',
                'APPROVED', 'REJECTED', 'AKTIF', 'SELESAI', 'DIBATALKAN',
            ])->default('DRAFT');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->integer('leave_semester_count')->default(1); // Berapa semester cuti
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('admin_note')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'status']);
        });

        // =============================================
        // 2. PERSETUJUAN BERJENJANG (academic_leave_approvals)
        // =============================================
        Schema::create('academic_leave_approvals', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('academic_leave_id')->constrained('academic_leaves')->cascadeOnDelete();
            $table->foreignId('approver_id')->constrained('users');
            $table->string('role', 50); // DOSEN_WALI, KAPRODI, ADMIN_AKADEMIK
            $table->integer('order')->default(1); // Urutan approval
            $table->enum('status', ['PENDING', 'APPROVED', 'REJECTED'])->default('PENDING');
            $table->text('notes')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['academic_leave_id', 'role', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_leave_approvals');
        Schema::dropIfExists('academic_leaves');
    }
};
