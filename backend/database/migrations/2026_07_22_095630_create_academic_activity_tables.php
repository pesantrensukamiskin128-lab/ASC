<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // =============================================
        // MODUL PERKULIAHAN
        // =============================================

        // Jurnal Perkuliahan (per pertemuan)
        Schema::create('lecture_journals', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->integer('meeting_number');       // Pertemuan ke-1, 2, dst
            $table->date('meeting_date');
            $table->string('topic');
            $table->text('description')->nullable(); // Deskripsi aktivitas
            $table->text('learning_activity')->nullable(); // Aktivitas pembelajaran (sesuai RPKPS)
            $table->text('materials_note')->nullable();
            $table->enum('status', ['PLANNED', 'COMPLETED', 'CANCELLED'])->default('PLANNED');
            $table->foreignId('lecturer_id')->constrained('lecturers');
            $table->timestamps();

            $table->unique(['class_id', 'meeting_number']);
        });

        // Presensi Mahasiswa
        Schema::create('attendances', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('journal_id')->constrained('lecture_journals')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->enum('status', ['HADIR', 'IZIN', 'SAKIT', 'ALFA'])->default('ALFA');
            $table->string('method')->nullable();    // QR, PIN, GPS, MANUAL, FINGERPRINT
            $table->timestamp('checked_in_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['journal_id', 'student_id']);
        });

        // Materi Kuliah
        Schema::create('lecture_materials', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('journal_id')->nullable()->constrained('lecture_journals')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_url')->nullable();  // Link external (GDrive dll)
            $table->string('file_type')->nullable();
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });

        // Tugas
        Schema::create('assignments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('journal_id')->nullable()->constrained('lecture_journals')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('instructions')->nullable();
            $table->enum('type', ['INDIVIDU', 'KELOMPOK'])->default('INDIVIDU');
            $table->datetime('due_date')->nullable();
            $table->integer('max_score')->default(100);
            $table->boolean('is_published')->default(true);
            $table->boolean('allow_late')->default(false);
            $table->timestamps();
        });

        // Pengumpulan Tugas
        Schema::create('assignment_submissions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('assignment_id')->constrained('assignments')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->text('content')->nullable();      // Teks jawaban
            $table->string('file_url')->nullable();   // Link GDrive tugas
            $table->string('file_path')->nullable();  // Upload langsung
            $table->decimal('score', 5, 2)->nullable();
            $table->text('feedback')->nullable();     // Feedback dosen
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('graded_at')->nullable();
            $table->foreignId('graded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['assignment_id', 'student_id']);
        });

        // Forum Diskusi
        Schema::create('discussions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('content');
            $table->boolean('is_pinned')->default(false);
            $table->boolean('is_locked')->default(false);
            $table->timestamps();
        });

        // Balasan Diskusi
        Schema::create('discussion_replies', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('discussion_id')->constrained('discussions')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('discussion_replies')->cascadeOnDelete();
            $table->text('content');
            $table->timestamps();
        });

        // Pengumuman Kelas
        Schema::create('class_announcements', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('content');
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });

        // =============================================
        // MODUL UJIAN
        // =============================================

        // Ujian (UTS/UAS/Quiz)
        Schema::create('exams', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->string('title');
            $table->enum('type', ['UTS', 'UAS', 'QUIZ', 'TUGAS_BESAR'])->default('UTS');
            $table->text('description')->nullable();
            $table->datetime('start_time')->nullable();
            $table->datetime('end_time')->nullable();
            $table->integer('duration_minutes')->default(90);
            $table->string('token', 10)->nullable();  // Token akses ujian
            $table->boolean('shuffle_questions')->default(true);
            $table->boolean('shuffle_options')->default(true);
            $table->boolean('show_score')->default(false); // Tampilkan skor setelah selesai
            $table->boolean('is_online')->default(true);
            $table->boolean('is_published')->default(false);
            $table->enum('status', ['DRAFT', 'PUBLISHED', 'ONGOING', 'FINISHED'])->default('DRAFT');
            // Ruangan & pengawas (untuk offline)
            $table->foreignId('room_id')->nullable()->constrained('rooms')->nullOnDelete();
            $table->foreignId('supervisor_id')->nullable()->constrained('lecturers')->nullOnDelete();
            $table->timestamps();
        });

        // Soal Ujian
        Schema::create('exam_questions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('exam_id')->constrained('exams')->cascadeOnDelete();
            $table->integer('order')->default(0);
            $table->enum('type', ['PILIHAN_GANDA', 'BENAR_SALAH', 'ESAI', 'STUDI_KASUS', 'MATCHING', 'UPLOAD_FILE']);
            $table->text('question');               // Pertanyaan (HTML/markdown)
            $table->json('options')->nullable();     // Pilihan jawaban [{key, text}]
            $table->string('correct_answer')->nullable(); // Kunci jawaban
            $table->decimal('score', 5, 2)->default(0); // Bobot soal
            $table->text('explanation')->nullable(); // Pembahasan
            $table->timestamps();
        });

        // Jawaban Mahasiswa
        Schema::create('exam_answers', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('exam_id')->constrained('exams')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('exam_questions')->cascadeOnDelete();
            $table->text('answer')->nullable();
            $table->string('file_path')->nullable(); // Untuk tipe UPLOAD_FILE
            $table->boolean('is_correct')->nullable();
            $table->decimal('score', 5, 2)->nullable();
            $table->timestamps();
        });

        // Sesi Ujian (log mahasiswa)
        Schema::create('exam_sessions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('exam_id')->constrained('exams')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->decimal('total_score', 5, 2)->nullable();
            $table->integer('tab_switches')->default(0); // Deteksi perpindahan tab
            $table->json('activity_log')->nullable(); // Log aktivitas
            $table->enum('status', ['IN_PROGRESS', 'SUBMITTED', 'GRADED'])->default('IN_PROGRESS');
            $table->timestamps();

            $table->unique(['exam_id', 'student_id']);
        });

        // =============================================
        // MODUL PENILAIAN
        // =============================================

        // Skema Konversi Nilai (configurable per institusi)
        Schema::create('grade_schemas', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');                  // "Standar", "Khusus", dll
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        // Detail Skema (range → huruf → bobot)
        Schema::create('grade_schema_details', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('grade_schema_id')->constrained('grade_schemas')->cascadeOnDelete();
            $table->decimal('min_score', 5, 2);     // 85.00
            $table->decimal('max_score', 5, 2);     // 100.00
            $table->string('letter', 5);            // A, A-, B+, dll
            $table->decimal('grade_point', 3, 2);   // 4.00, 3.75, dll
            $table->integer('order')->default(0);
            $table->timestamps();
        });

        // Nilai Akhir Mahasiswa per MK per Semester
        Schema::create('student_grades', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->foreignId('class_id')->nullable()->constrained('classes')->nullOnDelete();
            $table->foreignId('semester_id')->constrained('semesters');
            // Komponen nilai
            $table->json('components')->nullable();  // [{name, weight, score}]
            $table->decimal('final_score', 5, 2)->nullable(); // Nilai akhir (0-100)
            $table->string('letter_grade', 5)->nullable();    // A, B+, dll
            $table->decimal('grade_point', 3, 2)->nullable(); // 4.00, 3.50, dll
            $table->foreignId('graded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('graded_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'course_id', 'semester_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_grades');
        Schema::dropIfExists('grade_schema_details');
        Schema::dropIfExists('grade_schemas');
        Schema::dropIfExists('exam_sessions');
        Schema::dropIfExists('exam_answers');
        Schema::dropIfExists('exam_questions');
        Schema::dropIfExists('exams');
        Schema::dropIfExists('class_announcements');
        Schema::dropIfExists('discussion_replies');
        Schema::dropIfExists('discussions');
        Schema::dropIfExists('assignment_submissions');
        Schema::dropIfExists('assignments');
        Schema::dropIfExists('lecture_materials');
        Schema::dropIfExists('attendances');
        Schema::dropIfExists('lecture_journals');
    }
};
