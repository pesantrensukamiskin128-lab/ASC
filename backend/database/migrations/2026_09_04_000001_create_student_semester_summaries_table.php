<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_semester_summaries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('semester_id')->constrained('semesters')->cascadeOnDelete();
            $table->string('status', 30);
            $table->decimal('semester_gpa', 4, 2)->nullable();
            $table->decimal('cumulative_gpa', 4, 2)->nullable();
            $table->unsignedSmallInteger('credit_limit')->nullable();
            $table->unsignedSmallInteger('credits_taken')->nullable();
            $table->unsignedSmallInteger('required_credits')->nullable();
            $table->unsignedSmallInteger('elective_credits')->nullable();
            $table->unsignedSmallInteger('total_credits')->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'semester_id'], 'student_semester_summary_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_semester_summaries');
    }
};
