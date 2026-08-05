<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Kelas / Offering: dosen mengajar MK di semester tertentu
        Schema::create('class_offerings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnDelete();
            $table->foreignId('lecturer_id')->constrained('lecturers')->cascadeOnDelete();
            $table->foreignId('room_id')->nullable()->constrained('rooms')->nullOnDelete();
            $table->string('class_code', 20);        // e.g. TI101-A
            $table->integer('max_students')->default(40);
            $table->integer('enrolled_count')->default(0);
            $table->enum('day', ['Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'])->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['course_id', 'academic_year_id', 'class_code']);
        });

        // KRS - Kartu Rencana Studi (header per mahasiswa per semester)
        Schema::create('krs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnDelete();
            $table->foreignId('advisor_id')->nullable()->constrained('lecturers')->nullOnDelete();
            $table->integer('total_credits')->default(0);
            $table->enum('status', ['Draft', 'Submitted', 'Approved', 'Rejected'])->default('Draft');
            $table->text('advisor_note')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'academic_year_id']);
        });

        // Detail KRS - mata kuliah yang diambil
        Schema::create('krs_details', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('krs_id')->constrained('krs')->cascadeOnDelete();
            $table->foreignId('class_offering_id')->constrained('class_offerings')->cascadeOnDelete();
            $table->enum('status', ['Aktif', 'Dibatalkan'])->default('Aktif');
            $table->timestamps();

            $table->unique(['krs_id', 'class_offering_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('krs_details');
        Schema::dropIfExists('krs');
        Schema::dropIfExists('class_offerings');
    }
};
