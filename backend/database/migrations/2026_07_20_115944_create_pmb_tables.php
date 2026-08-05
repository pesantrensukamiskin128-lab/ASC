<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Periode PMB (gelombang pendaftaran)
        Schema::create('pmb_periods', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnDelete();
            $table->string('name');                      // Gelombang 1, 2, dst
            $table->date('registration_start');
            $table->date('registration_end');
            $table->date('selection_date')->nullable();
            $table->date('announcement_date')->nullable();
            $table->date('re_registration_start')->nullable();
            $table->date('re_registration_end')->nullable();
            $table->integer('quota')->default(0);
            $table->decimal('registration_fee', 12, 0)->default(0);
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });

        // Jalur seleksi
        Schema::create('pmb_paths', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('code', 20)->unique();       // REGULER, PRESTASI, BEASISWA, KHUSUS
            $table->string('name');
            $table->text('description')->nullable();
            $table->text('requirements')->nullable();    // persyaratan dalam teks
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Jenis seleksi/ujian
        Schema::create('pmb_exam_types', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('code', 30)->unique();       // TES_AKADEMIK, WAWANCARA, BACA_QURAN dll
            $table->string('name');
            $table->integer('weight')->default(0);      // bobot penilaian (%)
            $table->integer('passing_grade')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Pendaftar PMB (data utama calon mahasiswa)
        Schema::create('pmb_registrants', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('pmb_period_id')->constrained('pmb_periods')->cascadeOnDelete();
            $table->foreignId('pmb_path_id')->nullable()->constrained('pmb_paths')->nullOnDelete();
            $table->string('registration_number')->unique(); // Nomor pendaftaran auto-generate

            // Data pribadi
            $table->string('full_name');
            $table->enum('gender', ['L', 'P']);
            $table->string('birth_place');
            $table->date('birth_date');
            $table->string('religion')->nullable();
            $table->string('nik', 20)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('province')->nullable();
            $table->string('city')->nullable();
            $table->string('district')->nullable();
            $table->string('village')->nullable();
            $table->string('postal_code', 10)->nullable();

            // Data orang tua/wali
            $table->string('father_name')->nullable();
            $table->string('father_occupation')->nullable();
            $table->string('father_phone', 20)->nullable();
            $table->string('mother_name')->nullable();
            $table->string('mother_occupation')->nullable();
            $table->string('mother_phone', 20)->nullable();
            $table->string('guardian_name')->nullable();
            $table->string('guardian_occupation')->nullable();
            $table->string('guardian_phone', 20)->nullable();

            // Riwayat pendidikan
            $table->string('school_name')->nullable();
            $table->string('school_address')->nullable();
            $table->year('graduation_year')->nullable();
            $table->string('diploma_number')->nullable();  // nomor ijazah

            // Pilihan prodi (3 pilihan)
            $table->foreignId('choice_1')->nullable()->constrained('study_programs')->nullOnDelete();
            $table->foreignId('choice_2')->nullable()->constrained('study_programs')->nullOnDelete();
            $table->foreignId('choice_3')->nullable()->constrained('study_programs')->nullOnDelete();

            // Jalur khusus / prestasi
            $table->text('achievement_description')->nullable();

            // Foto & dokumen
            $table->string('photo_path')->nullable();           // pas foto upload file
            $table->string('diploma_link')->nullable();         // link GDrive ijazah
            $table->string('family_card_link')->nullable();     // link GDrive KK
            $table->string('identity_link')->nullable();        // link GDrive KTP/identitas

            // Pembayaran
            $table->boolean('is_paid')->default(false);
            $table->string('payment_proof')->nullable();
            $table->timestamp('paid_at')->nullable();

            // Status
            $table->enum('status', [
                'DRAFT',
                'SUBMITTED',
                'MENUNGGU_VERIFIKASI',
                'TERVERIFIKASI',
                'MENGIKUTI_SELEKSI',
                'LULUS',
                'TIDAK_LULUS',
                'DAFTAR_ULANG',
                'MAHASISWA_BARU',
            ])->default('DRAFT');

            // Accepted prodi (setelah lulus seleksi)
            $table->foreignId('accepted_program_id')->nullable()->constrained('study_programs')->nullOnDelete();
            $table->text('admin_note')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });

        // Nilai ujian/seleksi per jenis
        Schema::create('pmb_exam_scores', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('registrant_id')->constrained('pmb_registrants')->cascadeOnDelete();
            $table->foreignId('exam_type_id')->constrained('pmb_exam_types')->cascadeOnDelete();
            $table->decimal('score', 5, 2)->default(0);
            $table->text('note')->nullable();
            $table->foreignId('scored_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['registrant_id', 'exam_type_id']);
        });

        // Hasil seleksi / penilaian akhir
        Schema::create('pmb_selection_results', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('registrant_id')->constrained('pmb_registrants')->cascadeOnDelete();
            $table->decimal('final_score', 5, 2)->default(0);
            $table->integer('rank')->nullable();
            $table->enum('recommendation', ['LULUS', 'TIDAK_LULUS', 'CADANGAN'])->nullable();
            $table->enum('final_status', ['LULUS', 'TIDAK_LULUS'])->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique('registrant_id');
        });

        // Daftar ulang
        Schema::create('pmb_re_registrations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('registrant_id')->constrained('pmb_registrants')->cascadeOnDelete();
            $table->boolean('is_completed')->default(false);
            $table->string('nim', 20)->nullable();       // NIM yang digenerate
            $table->string('payment_proof')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique('registrant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pmb_re_registrations');
        Schema::dropIfExists('pmb_selection_results');
        Schema::dropIfExists('pmb_exam_scores');
        Schema::dropIfExists('pmb_registrants');
        Schema::dropIfExists('pmb_exam_types');
        Schema::dropIfExists('pmb_paths');
        Schema::dropIfExists('pmb_periods');
    }
};
