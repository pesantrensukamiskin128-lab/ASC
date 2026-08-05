<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // =============================================
        // 1. JADWAL — Pisahkan dari classes
        // =============================================
        Schema::create('schedules', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->enum('day', ['Senin','Selasa','Rabu','Kamis','Jumat','Sabtu']);
            $table->time('start_time');
            $table->time('end_time');
            $table->foreignId('room_id')->nullable()->constrained('rooms')->nullOnDelete();
            $table->foreignId('lecturer_id')->nullable()->constrained('lecturers')->nullOnDelete();
            $table->string('note')->nullable(); // "Sesi 1", "Praktikum", dll
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Migrasi data jadwal existing dari classes ke schedules
        $classes = \DB::table('classes')
            ->whereNotNull('day')
            ->whereNotNull('start_time')
            ->get();

        foreach ($classes as $c) {
            \DB::table('schedules')->insert([
                'class_id'   => $c->id,
                'day'        => $c->day,
                'start_time' => $c->start_time,
                'end_time'   => $c->end_time,
                'room_id'    => $c->room_id,
                'lecturer_id'=> $c->lecturer_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Hapus kolom jadwal dari classes (tetap simpan room_id dan lecturer_id sebagai default)
        Schema::table('classes', function (Blueprint $table) {
            $table->dropColumn(['day', 'start_time', 'end_time']);
        });

        // =============================================
        // 2. BANK SOAL — Terpisah dari ujian
        // =============================================
        Schema::create('question_banks', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->string('title');                 // "Bank Soal UTS HES 2026"
            $table->text('description')->nullable();
            $table->boolean('is_shared')->default(false); // Bisa dipakai dosen lain?
            $table->timestamps();
        });

        Schema::create('question_bank_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('question_bank_id')->constrained('question_banks')->cascadeOnDelete();
            $table->enum('type', ['PILIHAN_GANDA', 'BENAR_SALAH', 'ESAI', 'STUDI_KASUS', 'MATCHING', 'UPLOAD_FILE']);
            $table->text('question');
            $table->json('options')->nullable();
            $table->string('correct_answer')->nullable();
            $table->decimal('default_score', 5, 2)->default(1);
            $table->text('explanation')->nullable();
            $table->string('difficulty', 20)->nullable(); // MUDAH, SEDANG, SULIT
            $table->json('tags')->nullable();        // Tag topik: ["akad", "mudharabah"]
            $table->timestamps();
        });

        // Tambah FK ke exam_questions → question_bank_items (opsional, untuk reuse)
        Schema::table('exam_questions', function (Blueprint $table) {
            $table->foreignId('bank_item_id')->nullable()->after('exam_id')
                ->constrained('question_bank_items')->nullOnDelete();
        });

        // =============================================
        // 3. NOTIFIKASI & AUDIT
        // =============================================
        Schema::create('notifications', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('message')->nullable();
            $table->string('type', 50)->default('info'); // info, warning, success, error
            $table->string('link')->nullable();       // URL tujuan saat diklik
            $table->string('icon')->nullable();       // icon name
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'is_read']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 50);            // CREATE, UPDATE, DELETE, LOGIN, LOGOUT, etc
            $table->string('model_type')->nullable(); // App\Models\Student
            $table->unsignedBigInteger('model_id')->nullable();
            $table->json('old_values')->nullable();  // Data sebelum perubahan
            $table->json('new_values')->nullable();  // Data setelah perubahan
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();

            $table->index(['model_type', 'model_id']);
            $table->index(['user_id', 'action']);
        });

        Schema::create('configs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('key')->unique();          // app.max_sks, grading.schema_id, dll
            $table->text('value')->nullable();
            $table->string('type', 20)->default('string'); // string, integer, boolean, json
            $table->string('group', 50)->default('general');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // =============================================
        // 4. PMB REFACTOR — Pecah ke tabel terpisah
        // =============================================

        // Tabel applicants (data inti pendaftar — ringan)
        Schema::create('applicants', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('pmb_period_id')->constrained('pmb_periods')->cascadeOnDelete();
            $table->foreignId('pmb_path_id')->nullable()->constrained('pmb_paths')->nullOnDelete();
            $table->string('registration_number')->unique();
            $table->string('full_name');
            $table->enum('gender', ['L', 'P'])->nullable();
            $table->string('birth_place')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('religion', 50)->nullable();
            $table->string('nik', 20)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('province', 100)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('district', 100)->nullable();
            $table->string('village', 100)->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->string('photo_path')->nullable();
            // Status & payment
            $table->boolean('is_paid')->default(false);
            $table->string('payment_proof')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->enum('status', [
                'DRAFT','SUBMITTED','MENUNGGU_VERIFIKASI','TERVERIFIKASI',
                'MENGIKUTI_SELEKSI','LULUS','TIDAK_LULUS','DAFTAR_ULANG','MAHASISWA_BARU',
            ])->default('DRAFT');
            $table->foreignId('accepted_program_id')->nullable()->constrained('study_programs')->nullOnDelete();
            $table->text('admin_note')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });

        // Pilihan prodi (fleksibel, bisa 3, 4, 5...)
        Schema::create('applicant_choices', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('applicant_id')->constrained('applicants')->cascadeOnDelete();
            $table->foreignId('study_program_id')->constrained('study_programs');
            $table->integer('priority')->default(1); // 1 = pilihan pertama
            $table->timestamps();

            $table->unique(['applicant_id', 'priority']);
        });

        // Data keluarga (ayah, ibu, wali — bisa lebih dari 3)
        Schema::create('applicant_family', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('applicant_id')->constrained('applicants')->cascadeOnDelete();
            $table->enum('relation', ['Ayah', 'Ibu', 'Wali']);
            $table->string('name');
            $table->string('nik', 20)->nullable();
            $table->string('occupation', 100)->nullable();
            $table->string('income', 50)->nullable();
            $table->string('education', 50)->nullable();
            $table->string('phone', 20)->nullable();
            $table->text('address')->nullable();
            $table->boolean('is_alive')->default(true);
            $table->timestamps();
        });

        // Riwayat pendidikan pendaftar
        Schema::create('applicant_education', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('applicant_id')->constrained('applicants')->cascadeOnDelete();
            $table->string('school_name');
            $table->string('school_address')->nullable();
            $table->year('graduation_year')->nullable();
            $table->string('diploma_number')->nullable();
            $table->string('major')->nullable();     // Jurusan di SMA/SMK
            $table->decimal('average_score', 5, 2)->nullable(); // Rata-rata rapor
            $table->text('achievement_description')->nullable();
            $table->timestamps();
        });

        // Dokumen pendaftar (multiple, bukan hardcode)
        Schema::create('applicant_documents', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('applicant_id')->constrained('applicants')->cascadeOnDelete();
            $table->string('type', 50);              // FOTO, IJAZAH, KK, KTP, SERTIFIKAT, dll
            $table->string('name');
            $table->string('file_path')->nullable(); // Upload file
            $table->string('file_url')->nullable();  // Link GDrive
            $table->boolean('is_verified')->default(false);
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applicant_documents');
        Schema::dropIfExists('applicant_education');
        Schema::dropIfExists('applicant_family');
        Schema::dropIfExists('applicant_choices');
        Schema::dropIfExists('applicants');
        Schema::dropIfExists('configs');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('notifications');

        Schema::table('exam_questions', function (Blueprint $table) {
            $table->dropForeign(['bank_item_id']);
            $table->dropColumn('bank_item_id');
        });

        Schema::dropIfExists('question_bank_items');
        Schema::dropIfExists('question_banks');
        Schema::dropIfExists('schedules');

        // Restore columns to classes
        Schema::table('classes', function (Blueprint $table) {
            $table->enum('day', ['Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'])->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
        });
    }
};
