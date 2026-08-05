<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // RPS - Rencana Pembelajaran Semester
        Schema::create('rps', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnDelete();
            $table->foreignId('lecturer_id')->constrained('lecturers')->cascadeOnDelete();
            $table->string('code')->unique();
            $table->text('course_description')->nullable();
            $table->text('learning_objectives')->nullable();   // CPMK
            $table->text('references')->nullable();
            $table->json('assessment_scheme')->nullable();     // bobot penilaian
            $table->enum('status', ['Draft', 'Submitted', 'Approved', 'Rejected'])->default('Draft');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_note')->nullable();
            $table->timestamps();
        });

        // Pertemuan dalam RPS (16 pertemuan)
        Schema::create('rps_meetings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('rps_id')->constrained('rps')->cascadeOnDelete();
            $table->integer('meeting_number');   // 1-16
            $table->string('topic');
            $table->text('sub_topics')->nullable();
            $table->text('learning_activities')->nullable();
            $table->text('learning_methods')->nullable();
            $table->string('duration')->nullable();            // e.g. 2x50 menit
            $table->text('assessment_indicators')->nullable();
            $table->integer('weight')->default(0);             // bobot pertemuan (%)
            $table->timestamps();

            $table->unique(['rps_id', 'meeting_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rps_meetings');
        Schema::dropIfExists('rps');
    }
};
