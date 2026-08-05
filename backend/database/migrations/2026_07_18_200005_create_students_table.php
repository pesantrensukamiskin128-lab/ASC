<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('study_program_id')->constrained('study_programs');
            $table->foreignId('academic_year_id')->nullable()->constrained('academic_years')->nullOnDelete();
            $table->foreignId('advisor_id')->nullable()->constrained('lecturers')->nullOnDelete();
            $table->string('nim', 20)->unique();
            $table->string('name');
            $table->enum('gender', ['L', 'P'])->nullable();
            $table->string('birth_place')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 20)->nullable();
            $table->text('address')->nullable();
            $table->string('origin_school')->nullable();
            $table->year('entry_year')->nullable();
            $table->enum('status', ['Aktif', 'Cuti', 'Lulus', 'DO', 'Mengundurkan Diri'])->default('Aktif');
            $table->integer('current_semester')->default(1);
            $table->string('photo')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
