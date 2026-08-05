<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. SKRIPSI / TUGAS AKHIR
        Schema::create('theses', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('study_program_id')->constrained('study_programs');
            $table->string('title');
            $table->string('title_english')->nullable();
            $table->text('abstract')->nullable();
            $table->text('abstract_english')->nullable();
            $table->enum('type', ['SKRIPSI', 'TESIS', 'DISERTASI', 'TUGAS_AKHIR'])->default('SKRIPSI');
            $table->enum('status', [
                'PENGAJUAN_JUDUL', 'JUDUL_DISETUJUI', 'JUDUL_DITOLAK',
                'PROPOSAL', 'SEMINAR_PROPOSAL', 'REVISI_PROPOSAL',
                'PENELITIAN', 'PENULISAN',
                'SIDANG', 'REVISI_SIDANG', 'LULUS', 'GAGAL',
            ])->default('PENGAJUAN_JUDUL');
            $table->string('research_field')->nullable();
            $table->text('keywords')->nullable();
            $table->date('submission_date')->nullable();
            $table->date('approval_date')->nullable();
            $table->date('defense_date')->nullable();
            $table->date('completion_date')->nullable();
            $table->string('final_document_path')->nullable();
            $table->string('final_document_url')->nullable();
            $table->decimal('final_score', 5, 2)->nullable();
            $table->string('final_grade', 5)->nullable();
            $table->text('admin_note')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'status']);
        });

        // 2. PEMBIMBING
        Schema::create('thesis_supervisors', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('thesis_id')->constrained('theses')->cascadeOnDelete();
            $table->foreignId('lecturer_id')->constrained('lecturers');
            $table->enum('role', ['PEMBIMBING_1', 'PEMBIMBING_2', 'PEMBIMBING_3'])->default('PEMBIMBING_1');
            $table->boolean('is_approved')->default(false);
            $table->date('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['thesis_id', 'lecturer_id']);
        });

        // 3. PENGUJI
        Schema::create('thesis_examiners', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('thesis_id')->constrained('theses')->cascadeOnDelete();
            $table->foreignId('lecturer_id')->constrained('lecturers');
            $table->enum('role', ['KETUA_PENGUJI', 'PENGUJI_1', 'PENGUJI_2', 'SEKRETARIS'])->default('PENGUJI_1');
            $table->timestamps();

            $table->unique(['thesis_id', 'lecturer_id']);
        });

        // 4. BIMBINGAN SKRIPSI
        Schema::create('thesis_guidances', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('thesis_id')->constrained('theses')->cascadeOnDelete();
            $table->foreignId('supervisor_id')->constrained('lecturers');
            $table->date('guidance_date');
            $table->text('topic');
            $table->text('discussion')->nullable();
            $table->text('suggestion')->nullable();
            $table->text('student_note')->nullable();
            $table->string('chapter_reviewed')->nullable();
            $table->integer('progress_percentage')->nullable();
            $table->enum('status', ['DIAJUKAN', 'SELESAI', 'DIBATALKAN'])->default('SELESAI');
            $table->timestamps();
        });

        // 5. SIDANG
        Schema::create('thesis_defenses', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('thesis_id')->constrained('theses')->cascadeOnDelete();
            $table->enum('type', ['SEMINAR_PROPOSAL', 'SIDANG_AKHIR', 'SIDANG_KOMPREHENSIF'])->default('SIDANG_AKHIR');
            $table->date('defense_date');
            $table->time('defense_time')->nullable();
            $table->string('room')->nullable();
            $table->enum('result', ['LULUS', 'LULUS_DENGAN_REVISI', 'TIDAK_LULUS', 'DITUNDA'])->nullable();
            $table->text('notes')->nullable();
            $table->date('revision_deadline')->nullable();
            $table->boolean('revision_completed')->default(false);
            $table->timestamps();
        });

        // 6. NILAI PENGUJI
        Schema::create('thesis_defense_scores', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('defense_id')->constrained('thesis_defenses')->cascadeOnDelete();
            $table->foreignId('examiner_id')->constrained('lecturers');
            $table->string('component'); // Penguasaan Materi, Presentasi, Penulisan, dll
            $table->decimal('score', 5, 2);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 7. LOG PERUBAHAN JUDUL
        Schema::create('thesis_title_histories', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('thesis_id')->constrained('theses')->cascadeOnDelete();
            $table->string('old_title');
            $table->string('new_title');
            $table->text('reason')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('thesis_title_histories');
        Schema::dropIfExists('thesis_defense_scores');
        Schema::dropIfExists('thesis_defenses');
        Schema::dropIfExists('thesis_guidances');
        Schema::dropIfExists('thesis_examiners');
        Schema::dropIfExists('thesis_supervisors');
        Schema::dropIfExists('theses');
    }
};
