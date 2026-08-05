<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lecturers', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            // study_programs FK ditambahkan setelah tabel study_programs dibuat
            $table->unsignedBigInteger('study_program_id')->nullable();
            $table->string('nidn', 20)->unique()->nullable();
            $table->string('nip', 30)->nullable();
            $table->string('name');
            $table->enum('gender', ['L', 'P'])->nullable();
            $table->string('birth_place')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 20)->nullable();
            $table->text('address')->nullable();
            $table->string('last_education', 10)->nullable();
            $table->string('functional_position')->nullable();
            $table->string('employment_status')->default('Tetap');
            $table->boolean('status')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lecturers');
    }
};
