<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Konsentrasi / Peminatan dalam Program Studi
        Schema::create('concentrations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('study_program_id')->constrained('study_programs')->cascadeOnDelete();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        // Semester (periode dalam tahun akademik)
        Schema::create('semesters', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnDelete();
            $table->enum('type', ['Ganjil', 'Genap', 'Pendek']);
            $table->date('start_date');
            $table->date('end_date');
            $table->date('krs_start')->nullable();
            $table->date('krs_end')->nullable();
            $table->date('exam_mid_start')->nullable();
            $table->date('exam_mid_end')->nullable();
            $table->date('exam_final_start')->nullable();
            $table->date('exam_final_end')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });

        // Kalender Akademik (event penting dalam satu tahun akademik)
        Schema::create('academic_calendars', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->enum('category', ['Akademik', 'UTS', 'UAS', 'Libur', 'KKN', 'Wisuda', 'Lainnya'])->default('Akademik');
            $table->string('color', 20)->default('#3b82f6');
            $table->timestamps();
        });

        // Gedung
        Schema::create('buildings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('institution_id')->constrained('institutions')->cascadeOnDelete();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->integer('floors')->default(1);
            $table->text('address')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        // Ruangan
        Schema::create('rooms', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('building_id')->constrained('buildings')->cascadeOnDelete();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->integer('floor')->default(1);
            $table->integer('capacity')->default(40);
            $table->enum('type', ['Kelas', 'Lab', 'Aula', 'Seminar', 'Kantor', 'Lainnya'])->default('Kelas');
            $table->json('facilities')->nullable();   // AC, Proyektor, dll
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        // Tenaga Kependidikan (Staff non-dosen)
        Schema::create('staff', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('nip', 30)->nullable()->unique();
            $table->string('name');
            $table->enum('gender', ['L', 'P'])->nullable();
            $table->string('birth_place')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 20)->nullable();
            $table->text('address')->nullable();
            $table->string('position')->nullable();       // Jabatan
            $table->string('department')->nullable();     // Unit/Bagian
            $table->string('employment_status')->default('Tetap');
            $table->boolean('status')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff');
        Schema::dropIfExists('rooms');
        Schema::dropIfExists('buildings');
        Schema::dropIfExists('academic_calendars');
        Schema::dropIfExists('semesters');
        Schema::dropIfExists('concentrations');
    }
};
