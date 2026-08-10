<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('practical_attendances', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable()->after('notes');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->string('proof_url', 500)->nullable()->after('longitude');
        });
    }

    public function down(): void
    {
        Schema::table('practical_attendances', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude', 'proof_url']);
        });
    }
};
