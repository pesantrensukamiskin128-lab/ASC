<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Periode hibah
        Schema::create('penelitian_periods', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');                                 // e.g. "Hibah Penelitian 2026"
            $table->enum('type', ['penelitian', 'pengabdian']);
            $table->year('year');
            $table->date('open_date')->nullable();
            $table->date('close_date')->nullable();
            $table->boolean('is_active')->default(false);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // Proposal penelitian / pengabdian
        Schema::create('penelitians', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('period_id')->nullable()->constrained('penelitian_periods')->nullOnDelete();
            $table->foreignId('ketua_id')->constrained('lecturers')->cascadeOnDelete(); // Dosen ketua
            $table->foreignId('study_program_id')->nullable()->constrained('study_programs')->nullOnDelete();

            $table->enum('type', ['penelitian', 'pengabdian'])->default('penelitian');
            $table->string('title');
            $table->text('abstract')->nullable();
            $table->string('keywords')->nullable();

            // Links dokumen (Google Drive)
            $table->string('proposal_link')->nullable();
            $table->string('proposal_revision_link')->nullable();
            $table->string('laporan_kemajuan_link')->nullable();
            $table->string('laporan_kemajuan_revision_link')->nullable();
            $table->string('laporan_akhir_link')->nullable();
            $table->string('paper_link')->nullable();
            $table->string('lpj_link')->nullable();
            $table->string('lpj_revision_link')->nullable();
            $table->string('bibliography')->nullable();     // daftar pustaka

            // Upload langsung (laporan final pasca revisi seminar)
            $table->string('laporan_final_path')->nullable(); // PDF file
            $table->string('paper_final_path')->nullable();   // PDF file
            $table->string('cover_image_path')->nullable();

            // Kontrak
            $table->string('contract_number')->nullable();
            $table->decimal('total_dana', 15, 2)->nullable();
            $table->string('contract_link')->nullable();
            $table->date('contract_date')->nullable();

            // Seminar
            $table->date('seminar_date')->nullable();

            // Publikasi
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('repository_url')->nullable();

            // Status alur
            $table->enum('status', [
                'draft',
                'review_kaprodi',
                'submitted',            // ditolak kaprodi → bisa ajukan ulang
                'seleksi_reviewer',
                'tidak_lolos',
                'kontrak',
                'pelaksanaan_1',
                'monev',
                'revisi_kemajuan',
                'pelaksanaan_2',
                'seminar',
                'revisi_seminar',
                'lpj',
                'revisi_lpj',
                'selesai',
            ])->default('draft');

            // Catatan admin / kaprodi
            $table->text('kaprodi_note')->nullable();
            $table->text('lp2m_note')->nullable();

            // Audit
            $table->foreignId('kaprodi_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('kaprodi_reviewed_at')->nullable();
            $table->foreignId('lp2m_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('lp2m_reviewed_at')->nullable();
            $table->timestamp('submitted_at')->nullable();

            $table->timestamps();
            $table->index(['ketua_id', 'status']);
            $table->index(['period_id', 'type']);
        });

        // Anggota tim (dosen + mahasiswa)
        Schema::create('penelitian_members', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('penelitian_id')->constrained('penelitians')->cascadeOnDelete();
            $table->enum('member_type', ['dosen', 'mahasiswa']);
            $table->foreignId('lecturer_id')->nullable()->constrained('lecturers')->cascadeOnDelete();
            $table->foreignId('student_id')->nullable()->constrained('students')->cascadeOnDelete();
            $table->string('role')->default('anggota'); // ketua / anggota
            $table->timestamps();

            $table->unique(['penelitian_id', 'lecturer_id'], 'uniq_penelitian_lecturer');
            $table->unique(['penelitian_id', 'student_id'], 'uniq_penelitian_student');
        });

        // Reviewer yang ditugaskan LP2M
        Schema::create('penelitian_reviewers', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('penelitian_id')->constrained('penelitians')->cascadeOnDelete();
            $table->foreignId('lecturer_id')->constrained('lecturers')->cascadeOnDelete();
            $table->enum('stage', ['seleksi', 'monev'])->default('seleksi');

            // Penilaian
            $table->unsignedTinyInteger('score_orisinalitas')->nullable();  // 0–25
            $table->unsignedTinyInteger('score_metodologi')->nullable();    // 0–25
            $table->unsignedTinyInteger('score_manfaat')->nullable();       // 0–25
            $table->unsignedTinyInteger('score_kelayakan')->nullable();     // 0–25
            $table->unsignedSmallInteger('score_total')->nullable();        // 0–100
            $table->text('catatan')->nullable();
            $table->enum('rekomendasi', ['lolos', 'tidak_lolos', 'revisi'])->nullable();
            $table->timestamp('reviewed_at')->nullable();

            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['penelitian_id', 'lecturer_id', 'stage']);
        });

        // Pencairan dana (3 tahap: 50%, 30%, 20%)
        Schema::create('penelitian_fundings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('penelitian_id')->constrained('penelitians')->cascadeOnDelete();
            $table->unsignedTinyInteger('stage');                 // 1, 2, 3
            $table->decimal('amount', 15, 2);
            $table->text('keterangan')->nullable();
            $table->enum('status', ['alokasi', 'cair'])->default('alokasi');
            $table->string('bukti_transfer_path')->nullable();
            $table->foreignId('allocated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('allocated_at')->nullable();
            $table->foreignId('disbursed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('disbursed_at')->nullable();
            $table->timestamps();

            $table->unique(['penelitian_id', 'stage']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('penelitian_fundings');
        Schema::dropIfExists('penelitian_reviewers');
        Schema::dropIfExists('penelitian_members');
        Schema::dropIfExists('penelitians');
        Schema::dropIfExists('penelitian_periods');
    }
};
