<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classes', function (Blueprint $table): void {
            $table->unsignedBigInteger('lecturer_id')->nullable()->change();
        });

        Schema::create('class_lecturers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('lecturer_id')->constrained('lecturers')->cascadeOnDelete();
            $table->unsignedInteger('teaching_order')->default(1);
            $table->unsignedInteger('planned_meetings')->nullable();
            $table->unsignedInteger('actual_meetings')->nullable();
            $table->boolean('can_input_grades')->default(true);
            $table->decimal('teaching_credits', 5, 2)->nullable();
            $table->timestamps();

            $table->unique(['class_id', 'lecturer_id']);
        });

        Schema::table('schedules', function (Blueprint $table): void {
            $table->enum('day', ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'])->change();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_lecturers');

        if (! DB::table('schedules')->where('day', 'Minggu')->exists()) {
            Schema::table('schedules', function (Blueprint $table): void {
                $table->enum('day', ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'])->change();
            });
        }

        if (! DB::table('classes')->whereNull('lecturer_id')->exists()) {
            Schema::table('classes', function (Blueprint $table): void {
                $table->unsignedBigInteger('lecturer_id')->nullable(false)->change();
            });
        }
    }
};
