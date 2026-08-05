<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. PERIODE WISUDA
        Schema::create('graduation_periods', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name'); // Wisuda Ke-XX Tahun 2026
            $table->foreignId('academic_year_id')->constrained('academic_years');
            $table->date('registration_start');
            $table->date('registration_end');
            $table->date('graduation_date');
            $table->string('venue')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });

        // 2. PENDAFTARAN WISUDA
        Schema::create('graduation_registrations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('period_id')->constrained('graduation_periods')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->enum('status', [
                'DRAFT', 'SUBMITTED', 'VERIFIKASI_AKADEMIK', 'VERIFIKASI_KEUANGAN',
                'VERIFIKASI_PERPUSTAKAAN', 'APPROVED', 'REJECTED', 'WISUDA',
            ])->default('DRAFT');
            $table->string('toga_size', 10)->nullable();
            $table->string('phone')->nullable();
            $table->string('address_current')->nullable();
            $table->integer('total_credits')->nullable();
            $table->decimal('gpa', 4, 2)->nullable();
            $table->string('thesis_title')->nullable();
            $table->string('predicate')->nullable(); // Cum Laude, Sangat Memuaskan, Memuaskan
            $table->text('notes')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->unique(['period_id', 'student_id']);
        });

        // 3. VERIFIKASI SYARAT WISUDA
        Schema::create('graduation_verifications', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('registration_id')->constrained('graduation_registrations')->cascadeOnDelete();
            $table->string('requirement'); // Bebas Tagihan, Skripsi Lulus, Bebas Perpustakaan, dll
            $table->enum('category', ['AKADEMIK', 'KEUANGAN', 'PERPUSTAKAAN', 'ADMINISTRASI'])->default('AKADEMIK');
            $table->boolean('is_fulfilled')->default(false);
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 4. DOKUMEN WISUDA (Ijazah, Transkrip, dll)
        Schema::create('graduation_documents', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('registration_id')->constrained('graduation_registrations')->cascadeOnDelete();
            $table->enum('type', ['IJAZAH', 'TRANSKRIP', 'SERTIFIKAT', 'SKPI', 'FOTO', 'LAINNYA']);
            $table->string('document_number')->nullable();
            $table->string('file_path')->nullable();
            $table->date('issued_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('graduation_documents');
        Schema::dropIfExists('graduation_verifications');
        Schema::dropIfExists('graduation_registrations');
        Schema::dropIfExists('graduation_periods');
    }
};
