<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('letter_requests', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('requested_by')->constrained('users');
            $table->foreignId('letter_type_id')->nullable()->constrained('letter_types')->nullOnDelete();
            $table->string('purpose');                    // Keperluan surat
            $table->text('description')->nullable();      // Keterangan tambahan
            $table->enum('status', ['DIAJUKAN', 'DIPROSES', 'SELESAI', 'DITOLAK'])->default('DIAJUKAN');
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('admin_note')->nullable();        // Catatan dari admin
            $table->foreignId('outgoing_letter_id')->nullable()->constrained('outgoing_letters')->nullOnDelete(); // Link ke surat yang dibuat
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('letter_requests');
    }
};
