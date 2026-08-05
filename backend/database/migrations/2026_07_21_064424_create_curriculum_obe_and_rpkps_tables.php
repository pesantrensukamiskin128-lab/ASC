<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // =============================================
        // KURIKULUM OBE
        // =============================================

        // Profil Lulusan
        Schema::create('graduate_profiles', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('curriculum_id')->constrained('curriculums')->cascadeOnDelete();
            $table->string('code', 20);            // PL-01, PL-02
            $table->string('name');                 // Praktisi hukum, Peneliti, dll
            $table->text('description')->nullable();
            $table->integer('order')->default(0);
            $table->timestamps();
        });

        // CPL sudah ada di tabel learning_outcomes, tapi kita perlu Sub-CPMK dan pemetaan lebih lengkap
        // Tambah kolom order dan support_level ke learning_outcomes
        Schema::table('learning_outcomes', function (Blueprint $table) {
            $table->integer('order')->default(0)->after('description');
        });

        // CPMK - Capaian Pembelajaran Mata Kuliah
        Schema::create('course_learning_outcomes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('curriculum_id')->constrained('curriculums')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->string('code', 20);            // CPMK-01, CPMK-02
            $table->text('description');
            $table->integer('order')->default(0);
            $table->timestamps();
        });

        // Pemetaan CPMK → CPL (many-to-many)
        Schema::create('cpmk_cpl_mappings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('course_learning_outcome_id')->constrained('course_learning_outcomes')->cascadeOnDelete();
            $table->foreignId('learning_outcome_id')->constrained('learning_outcomes')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['course_learning_outcome_id', 'learning_outcome_id'], 'cpmk_cpl_unique');
        });

        // Sub-CPMK
        Schema::create('sub_course_learning_outcomes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('course_learning_outcome_id')->constrained('course_learning_outcomes')->cascadeOnDelete();
            $table->string('code', 20);            // Sub-CPMK 1.1, 1.2
            $table->text('description');
            $table->integer('order')->default(0);
            $table->timestamps();
        });

        // Pemetaan CPL → Mata Kuliah (matriks)
        Schema::create('cpl_course_mappings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('learning_outcome_id')->constrained('learning_outcomes')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->enum('support_level', ['Tinggi', 'Sedang', 'Rendah'])->default('Sedang');
            $table->timestamps();

            $table->unique(['learning_outcome_id', 'course_id']);
        });

        // =============================================
        // RPKPS / RPS DIGITAL
        // =============================================

        // Drop tabel rps lama dan buat ulang dengan struktur lebih lengkap
        Schema::dropIfExists('rps_meetings');
        Schema::dropIfExists('rps');

        // Tabel utama RPKPS
        Schema::create('rpkps', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->foreignId('curriculum_id')->constrained('curriculums')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained('academic_years');
            $table->foreignId('semester_id')->nullable()->constrained('semesters')->nullOnDelete();
            $table->foreignId('lecturer_id')->constrained('lecturers')->cascadeOnDelete();
            $table->foreignId('coordinator_id')->nullable()->constrained('lecturers')->nullOnDelete();

            // Identitas
            $table->string('code')->unique();       // RPKPS-HES-2026-00001

            // Deskripsi
            $table->text('course_description')->nullable();
            $table->text('course_urgency')->nullable();
            $table->text('course_scope')->nullable();
            $table->text('course_position')->nullable(); // Posisi dalam kurikulum
            $table->text('prerequisites')->nullable();

            // Asesmen
            $table->json('assessment_components')->nullable(); // [{name, weight}]

            // Referensi disimpan di tabel terpisah

            // Status & workflow
            $table->enum('status', [
                'DRAFT', 'DIAJUKAN', 'DALAM_PEMERIKSAAN', 'REVISI', 'DISETUJUI', 'DIKUNCI', 'DIARSIPKAN'
            ])->default('DRAFT');

            // Versioning
            $table->integer('version')->default(1);
            $table->foreignId('parent_id')->nullable()->constrained('rpkps')->nullOnDelete(); // Versi sebelumnya

            // Approval
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('revision_note')->nullable();

            // QR & PDF
            $table->string('verification_code')->nullable()->unique(); // Untuk QR
            $table->string('pdf_path')->nullable();

            $table->timestamps();
        });

        // RPKPS → CPL (yang dipilih dosen)
        Schema::create('rpkps_cpls', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('rpkps_id')->constrained('rpkps')->cascadeOnDelete();
            $table->foreignId('learning_outcome_id')->constrained('learning_outcomes')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['rpkps_id', 'learning_outcome_id']);
        });

        // RPKPS → CPMK
        Schema::create('rpkps_cpmks', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('rpkps_id')->constrained('rpkps')->cascadeOnDelete();
            $table->string('code', 20);
            $table->text('description');
            $table->integer('order')->default(0);
            $table->timestamps();
        });

        // RPKPS → Sub-CPMK
        Schema::create('rpkps_sub_cpmks', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('rpkps_cpmk_id')->constrained('rpkps_cpmks')->cascadeOnDelete();
            $table->string('code', 20);
            $table->text('description');
            $table->integer('order')->default(0);
            $table->timestamps();
        });

        // Pemetaan RPKPS CPMK → CPL
        Schema::create('rpkps_cpmk_cpl', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('rpkps_cpmk_id')->constrained('rpkps_cpmks')->cascadeOnDelete();
            $table->foreignId('learning_outcome_id')->constrained('learning_outcomes')->cascadeOnDelete();

            $table->unique(['rpkps_cpmk_id', 'learning_outcome_id'], 'rpkps_cpmk_cpl_unique');
        });

        // Bahan Kajian
        Schema::create('rpkps_learning_materials', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('rpkps_id')->constrained('rpkps')->cascadeOnDelete();
            $table->string('topic');
            $table->text('subtopics')->nullable();
            $table->integer('order')->default(0);
            $table->timestamps();
        });

        // Rencana Pembelajaran Mingguan
        Schema::create('rpkps_weekly_plans', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('rpkps_id')->constrained('rpkps')->cascadeOnDelete();
            $table->integer('week_number');         // 1-16
            $table->text('sub_cpmk')->nullable();   // Sub-CPMK yang dicapai
            $table->text('indicators')->nullable(); // Indikator pencapaian
            $table->text('learning_material')->nullable(); // Bahan kajian
            $table->json('methods')->nullable();    // Metode pembelajaran (array)
            $table->text('lecturer_activity')->nullable();
            $table->text('student_activity')->nullable();
            $table->text('assessment_form')->nullable();  // Bentuk penilaian
            $table->text('assessment_criteria')->nullable();
            $table->text('media')->nullable();      // Media pembelajaran
            $table->string('duration')->nullable(); // 2x50 menit, dll
            $table->integer('weight')->default(0);  // Bobot penilaian (%)
            $table->timestamps();

            $table->unique(['rpkps_id', 'week_number']);
        });

        // Komponen Asesmen
        Schema::create('rpkps_assessments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('rpkps_id')->constrained('rpkps')->cascadeOnDelete();
            $table->string('name');                 // Kehadiran, Tugas, UTS, UAS
            $table->integer('weight');              // Bobot (%)
            $table->text('description')->nullable();
            $table->integer('order')->default(0);
            $table->timestamps();
        });

        // Rubrik Penilaian
        Schema::create('rpkps_rubrics', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('rpkps_id')->constrained('rpkps')->cascadeOnDelete();
            $table->foreignId('rpkps_assessment_id')->nullable()->constrained('rpkps_assessments')->nullOnDelete();
            $table->string('criteria');             // Analisis, Argumentasi, dll
            $table->text('excellent')->nullable();  // Sangat Baik
            $table->text('good')->nullable();       // Baik
            $table->text('fair')->nullable();       // Cukup
            $table->text('poor')->nullable();       // Kurang
            $table->integer('order')->default(0);
            $table->timestamps();
        });

        // Referensi
        Schema::create('rpkps_references', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('rpkps_id')->constrained('rpkps')->cascadeOnDelete();
            $table->enum('type', ['Utama', 'Pendukung'])->default('Utama');
            $table->enum('category', ['Buku', 'Jurnal', 'Peraturan', 'Fatwa', 'Putusan', 'Website', 'Artikel', 'Modul', 'Video', 'E-book', 'Lainnya'])->default('Buku');
            $table->string('title');
            $table->string('author')->nullable();
            $table->string('year', 10)->nullable();
            $table->string('publisher')->nullable();
            $table->string('isbn_doi')->nullable();
            $table->string('url')->nullable();
            $table->integer('order')->default(0);
            $table->timestamps();
        });

        // Approval / Audit Log
        Schema::create('rpkps_approvals', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('rpkps_id')->constrained('rpkps')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('action', ['DIAJUKAN', 'REVISI', 'DISETUJUI', 'DITOLAK', 'DIKUNCI', 'DIBUKA']);
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rpkps_approvals');
        Schema::dropIfExists('rpkps_references');
        Schema::dropIfExists('rpkps_rubrics');
        Schema::dropIfExists('rpkps_assessments');
        Schema::dropIfExists('rpkps_weekly_plans');
        Schema::dropIfExists('rpkps_learning_materials');
        Schema::dropIfExists('rpkps_cpmk_cpl');
        Schema::dropIfExists('rpkps_sub_cpmks');
        Schema::dropIfExists('rpkps_cpmks');
        Schema::dropIfExists('rpkps_cpls');
        Schema::dropIfExists('rpkps');

        // Recreate old rps tables
        Schema::create('rps', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnDelete();
            $table->foreignId('lecturer_id')->constrained('lecturers')->cascadeOnDelete();
            $table->string('code')->unique();
            $table->text('course_description')->nullable();
            $table->text('learning_objectives')->nullable();
            $table->text('references')->nullable();
            $table->json('assessment_scheme')->nullable();
            $table->enum('status', ['Draft', 'Submitted', 'Approved', 'Rejected'])->default('Draft');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_note')->nullable();
            $table->timestamps();
        });
        Schema::create('rps_meetings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('rps_id')->constrained('rps')->cascadeOnDelete();
            $table->integer('meeting_number');
            $table->string('topic');
            $table->text('sub_topics')->nullable();
            $table->text('learning_activities')->nullable();
            $table->text('learning_methods')->nullable();
            $table->string('duration')->nullable();
            $table->text('assessment_indicators')->nullable();
            $table->integer('weight')->default(0);
            $table->timestamps();
            $table->unique(['rps_id', 'meeting_number']);
        });

        Schema::dropIfExists('cpl_course_mappings');
        Schema::dropIfExists('sub_course_learning_outcomes');
        Schema::dropIfExists('cpmk_cpl_mappings');
        Schema::dropIfExists('course_learning_outcomes');
        Schema::dropIfExists('graduate_profiles');

        Schema::table('learning_outcomes', function (Blueprint $table) {
            $table->dropColumn('order');
        });
    }
};
