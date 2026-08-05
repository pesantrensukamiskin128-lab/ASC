<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_years', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('code', 20)->unique();    // e.g. 2025/2026-1
            $table->string('name');                  // e.g. Ganjil 2025/2026
            $table->enum('semester', ['Ganjil', 'Genap', 'Pendek']);
            $table->year('year_start');
            $table->year('year_end');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_years');
    }
};
