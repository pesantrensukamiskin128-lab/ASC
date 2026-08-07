<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Konfigurasi integrasi LMS
        Schema::create('lms_config', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('base_url');              // https://lms.stai-aljawami.ac.id/api
            $table->text('api_token');               // Bearer token (encrypted)
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamps();
        });

        // Log sinkronisasi
        Schema::create('lms_sync_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('sync_type', 30);        // users, courses, classes, enrollments
            $table->enum('status', ['success', 'partial', 'failed']);
            $table->integer('total_items')->default(0);
            $table->integer('synced_items')->default(0);
            $table->integer('failed_items')->default(0);
            $table->json('errors')->nullable();
            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->integer('duration_ms')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lms_sync_logs');
        Schema::dropIfExists('lms_config');
    }
};
