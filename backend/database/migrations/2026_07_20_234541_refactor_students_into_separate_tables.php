<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Profil Mahasiswa (data pribadi detail)
        Schema::create('student_profiles', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->string('religion', 50)->nullable();
            $table->string('nik', 20)->nullable();
            $table->string('nisn', 20)->nullable();
            $table->string('blood_type', 5)->nullable();
            $table->string('marital_status', 20)->nullable();
            $table->string('nationality', 50)->default('Indonesia');
            $table->string('photo_path')->nullable();
            $table->timestamps();

            $table->unique('student_id');
        });

        // 2. Alamat Mahasiswa
        Schema::create('student_addresses', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->enum('type', ['Domisili', 'Asal'])->default('Domisili');
            $table->text('address')->nullable();
            $table->string('village', 100)->nullable();
            $table->string('district', 100)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('province', 100)->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'type']);
        });

        // 3. Data Orang Tua / Wali
        Schema::create('student_parents', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->enum('relation', ['Ayah', 'Ibu', 'Wali']);
            $table->string('name');
            $table->string('nik', 20)->nullable();
            $table->string('birth_place', 100)->nullable();
            $table->date('birth_date')->nullable();
            $table->string('occupation', 100)->nullable();
            $table->string('income', 50)->nullable();
            $table->string('education', 50)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->boolean('is_alive')->default(true);
            $table->timestamps();

            $table->unique(['student_id', 'relation']);
        });

        // 4. Dokumen Mahasiswa
        Schema::create('student_documents', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->string('type', 50);        // KTP, KK, IJAZAH, AKTA_LAHIR, FOTO, dll
            $table->string('name');             // Nama dokumen
            $table->string('file_path')->nullable();   // Upload file
            $table->string('file_url')->nullable();    // Link Google Drive
            $table->string('document_number')->nullable(); // Nomor dokumen
            $table->date('issued_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->boolean('is_verified')->default(false);
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });

        // 5. Riwayat Pendidikan
        Schema::create('student_education_histories', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->enum('level', ['SD', 'SMP', 'SMA/MA/SMK', 'D3', 'S1', 'S2', 'S3', 'Lainnya']);
            $table->string('institution_name');
            $table->string('institution_address')->nullable();
            $table->string('major')->nullable();         // Jurusan/program
            $table->year('entry_year')->nullable();
            $table->year('graduation_year')->nullable();
            $table->string('diploma_number')->nullable();
            $table->decimal('gpa', 4, 2)->nullable();    // IPK jika ada
            $table->timestamps();
        });

        // 6. History Status Mahasiswa (PENTING untuk audit & akreditasi)
        Schema::create('student_status_histories', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('semester_id')->nullable()->constrained('semesters')->nullOnDelete();
            $table->string('status', 30);              // AKTIF, CUTI, NONAKTIF, LULUS, DO, dll
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->text('reason')->nullable();         // Alasan perubahan status
            $table->string('decree_number')->nullable(); // Nomor SK (jika ada)
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // 7. Data Keuangan Mahasiswa
        Schema::create('student_financial_records', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('semester_id')->nullable()->constrained('semesters')->nullOnDelete();
            $table->string('type', 30);                // UKT, SPP, HERREGISTRASI, WISUDA, dll
            $table->string('description')->nullable();
            $table->decimal('amount', 14, 0);          // Jumlah tagihan
            $table->decimal('paid_amount', 14, 0)->default(0);
            $table->date('due_date')->nullable();
            $table->date('paid_date')->nullable();
            $table->string('payment_method')->nullable();
            $table->string('receipt_number')->nullable();
            $table->enum('status', ['BELUM_BAYAR', 'SEBAGIAN', 'LUNAS', 'BEBAS'])->default('BELUM_BAYAR');
            $table->timestamps();
        });

        // ========================================
        // Simplify tabel students utama
        // Hapus kolom yang sudah dipindah ke tabel terpisah
        // ========================================
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn(['address', 'origin_school', 'photo']);
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->text('address')->nullable();
            $table->string('origin_school')->nullable();
            $table->string('photo')->nullable();
        });

        Schema::dropIfExists('student_financial_records');
        Schema::dropIfExists('student_status_histories');
        Schema::dropIfExists('student_education_histories');
        Schema::dropIfExists('student_documents');
        Schema::dropIfExists('student_parents');
        Schema::dropIfExists('student_addresses');
        Schema::dropIfExists('student_profiles');
    }
};
