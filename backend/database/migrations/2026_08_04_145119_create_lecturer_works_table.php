<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lecturer_works', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('lecturer_id')->constrained('lecturers')->cascadeOnDelete();
            $table->enum('type', ['buku', 'modul_ajar', 'hki_paten', 'penelitian_mandiri', 'pengabdian_mandiri']);
            $table->string('title');
            $table->smallInteger('year');
            $table->text('description')->nullable();
            $table->string('keywords')->nullable();
            $table->string('publisher')->nullable();
            $table->string('isbn_issn')->nullable();
            $table->string('hki_number')->nullable();   // No. HKI / paten
            $table->date('published_date')->nullable();
            // Files
            $table->string('main_file_path')->nullable();
            $table->string('support_file_path')->nullable();
            $table->string('cover_image_path')->nullable();
            // Status
            $table->enum('status', ['draft', 'diajukan', 'revisi', 'diverifikasi', 'dipublikasikan'])->default('draft');
            $table->text('revision_note')->nullable();   // catatan revisi dari LP2M
            $table->string('repository_url')->nullable();
            // Audit
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->index(['lecturer_id', 'status']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lecturer_works');
    }
};
