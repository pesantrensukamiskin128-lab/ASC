<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('study_program_id')->constrained('study_programs');
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->integer('credits')->default(2);       // SKS
            $table->integer('semester')->default(1);
            $table->enum('type', ['Wajib', 'Pilihan', 'Praktikum'])->default('Wajib');
            $table->boolean('status')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
