<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // =============================================
        // 1. JENIS TAGIHAN (fee_types)
        // =============================================
        Schema::create('fee_types', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('code', 30)->unique();    // SPP, UKT, UJIAN, SKRIPSI, WISUDA, dll
            $table->string('name');                   // Sumbangan Pembinaan Pendidikan
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_mandatory')->default(true); // Wajib bayar?
            $table->boolean('is_recurring')->default(false); // Berulang per semester?
            $table->timestamps();
        });

        // =============================================
        // 2. STRUKTUR BIAYA (fee_structures)
        // =============================================
        Schema::create('fee_structures', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('fee_type_id')->constrained('fee_types')->cascadeOnDelete();
            $table->foreignId('study_program_id')->nullable()->constrained('study_programs')->nullOnDelete();
            $table->foreignId('academic_year_id')->nullable()->constrained('academic_years')->nullOnDelete();
            $table->integer('academic_level')->nullable(); // Semester/tingkat mahasiswa
            $table->decimal('amount', 14, 0);         // Nominal
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['fee_type_id', 'study_program_id', 'academic_year_id'], 'fee_struct_idx');
        });

        // =============================================
        // 3. TAGIHAN (invoices)
        // =============================================
        Schema::create('invoices', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('invoice_number', 50)->unique();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('semester_id')->nullable()->constrained('semesters')->nullOnDelete();
            $table->date('invoice_date');
            $table->date('due_date');
            $table->decimal('total_amount', 14, 0)->default(0);
            $table->decimal('discount_amount', 14, 0)->default(0);
            $table->decimal('scholarship_amount', 14, 0)->default(0);
            $table->decimal('paid_amount', 14, 0)->default(0);
            $table->enum('status', ['UNPAID', 'PARTIAL', 'PAID', 'OVERDUE', 'CANCELLED', 'WAIVED'])->default('UNPAID');
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['student_id', 'semester_id', 'status']);
        });

        // =============================================
        // 4. DETAIL TAGIHAN (invoice_items)
        // =============================================
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('fee_type_id')->constrained('fee_types');
            $table->string('description')->nullable();
            $table->decimal('amount', 14, 0);
            $table->timestamps();
        });

        // =============================================
        // 5. PEMBAYARAN (payments)
        // =============================================
        Schema::create('payments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->string('payment_number', 50)->unique();
            $table->decimal('amount', 14, 0);
            $table->string('payment_method', 50);     // TRANSFER, CASH, VA, QRIS, GATEWAY
            $table->timestamp('payment_date');
            $table->string('reference_number')->nullable(); // Nomor referensi bank/VA
            $table->string('bank_name')->nullable();
            $table->string('account_number')->nullable();
            $table->string('account_name')->nullable();
            $table->string('receipt_path')->nullable(); // Bukti pembayaran (file)
            $table->enum('status', ['PENDING', 'VERIFIED', 'REJECTED', 'REFUNDED'])->default('PENDING');
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('note')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'status']);
            $table->index(['invoice_id', 'status']);
        });

        // =============================================
        // 6. BEASISWA (scholarships)
        // =============================================
        Schema::create('scholarships', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->string('provider')->nullable();   // Pemerintah, Swasta, Internal
            $table->text('description')->nullable();
            $table->text('requirements')->nullable();
            $table->decimal('amount', 14, 0)->nullable(); // Nominal per semester
            $table->enum('type', ['FULL', 'PARTIAL', 'TUITION_ONLY', 'LIVING_COST'])->default('PARTIAL');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // =============================================
        // 7. PENERIMA BEASISWA (student_scholarships)
        // =============================================
        Schema::create('student_scholarships', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('scholarship_id')->constrained('scholarships')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->nullable()->constrained('academic_years')->nullOnDelete();
            $table->foreignId('semester_id')->nullable()->constrained('semesters')->nullOnDelete();
            $table->decimal('amount', 14, 0);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->enum('status', ['AKTIF', 'SELESAI', 'DICABUT', 'PENDING'])->default('AKTIF');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_scholarships');
        Schema::dropIfExists('scholarships');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('fee_structures');
        Schema::dropIfExists('fee_types');
    }
};
