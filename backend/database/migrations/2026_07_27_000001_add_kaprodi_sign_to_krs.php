<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('krs', function (Blueprint $table) {
            $table->timestamp('signed_by_kaprodi_at')->nullable()->after('approved_at');
            $table->foreignId('signed_by_kaprodi_id')->nullable()->after('signed_by_kaprodi_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('krs', function (Blueprint $table) {
            $table->dropForeign(['signed_by_kaprodi_id']);
            $table->dropColumn(['signed_by_kaprodi_at', 'signed_by_kaprodi_id']);
        });
    }
};
