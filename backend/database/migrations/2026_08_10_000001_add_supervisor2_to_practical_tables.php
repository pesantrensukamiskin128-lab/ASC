<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('practical_participants', function (Blueprint $table) {
            $table->foreignId('supervisor2_id')->nullable()->after('supervisor_id')
                ->constrained('lecturers')->nullOnDelete();
        });

        Schema::table('practical_locations', function (Blueprint $table) {
            $table->foreignId('supervisor2_id')->nullable()->after('supervisor_id')
                ->constrained('lecturers')->nullOnDelete();
        });

        Schema::table('practical_groups', function (Blueprint $table) {
            $table->foreignId('supervisor2_id')->nullable()->after('supervisor_id')
                ->constrained('lecturers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('practical_participants', function (Blueprint $table) {
            $table->dropForeign(['supervisor2_id']);
            $table->dropColumn('supervisor2_id');
        });
        Schema::table('practical_locations', function (Blueprint $table) {
            $table->dropForeign(['supervisor2_id']);
            $table->dropColumn('supervisor2_id');
        });
        Schema::table('practical_groups', function (Blueprint $table) {
            $table->dropForeign(['supervisor2_id']);
            $table->dropColumn('supervisor2_id');
        });
    }
};
