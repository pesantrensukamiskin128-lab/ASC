<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('files', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('owner_type');            // Model class (polymorphic)
            $table->unsignedBigInteger('owner_id');  // ID of the owning model
            $table->string('collection')->default('default'); // Grouping: photo, document, payment, etc.
            $table->string('file_name');             // Nama asli file saat upload
            $table->string('file_path');             // Path di storage/object storage
            $table->string('disk')->default('public'); // Storage disk: public, s3, etc.
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->default(0); // Bytes
            $table->string('file_hash', 64)->nullable(); // SHA-256 untuk dedup/integritas
            $table->json('metadata')->nullable();    // Data tambahan (dimensi gambar, dll)
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['owner_type', 'owner_id']);
            $table->index('collection');
            $table->index('file_hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('files');
    }
};
