<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('practical_attendances', function (Blueprint $table) {
            $table->enum('approval_status', ['MENUNGGU', 'DITERIMA', 'DITOLAK'])->default('MENUNGGU')->after('status');
            $table->text('rejection_note')->nullable()->after('approval_status');
            $table->foreignId('approved_by')->nullable()->after('rejection_note')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
        });
    }

    public function down(): void
    {
        Schema::table('practical_attendances', function (Blueprint $table) {
            $table->dropForeign(['approved_by']);
            $table->dropColumn(['approval_status', 'rejection_note', 'approved_by', 'approved_at']);
        });
    }
};
