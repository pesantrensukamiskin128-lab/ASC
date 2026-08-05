<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('study_programs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('faculty_id')->constrained('faculties')->cascadeOnDelete();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('degree')->nullable();        // e.g. S.Kom, S.T
            $table->string('level')->nullable();         // D3, S1, S2, S3, Profesi
            $table->string('accreditation', 20)->nullable();
            $table->foreignId('head_lecturer_id')->nullable()->constrained('lecturers')->nullOnDelete();
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        // Tambahkan FK study_program_id ke lecturers setelah study_programs dibuat
        Schema::table('lecturers', function (Blueprint $table) {
            $table->foreign('study_program_id')->references('id')->on('study_programs')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('lecturers', function (Blueprint $table) {
            $table->dropForeign(['study_program_id']);
        });

        Schema::dropIfExists('study_programs');
    }
};
