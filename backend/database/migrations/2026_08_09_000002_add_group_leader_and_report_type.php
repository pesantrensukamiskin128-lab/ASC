<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tambah ketua kelompok di tabel groups
        Schema::table('practical_groups', function (Blueprint $table) {
            $table->foreignId('leader_id')->nullable()->after('supervisor_id')
                ->constrained('practical_participants')->nullOnDelete();
        });

        // Tambah tipe laporan dan group_id di tabel reports
        Schema::table('practical_reports', function (Blueprint $table) {
            $table->enum('report_type', ['INDIVIDU', 'KELOMPOK'])->default('INDIVIDU')->after('participant_id');
            $table->foreignId('group_id')->nullable()->after('report_type')
                ->constrained('practical_groups')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('practical_reports', function (Blueprint $table) {
            $table->dropForeign(['group_id']);
            $table->dropColumn(['report_type', 'group_id']);
        });
        Schema::table('practical_groups', function (Blueprint $table) {
            $table->dropForeign(['leader_id']);
            $table->dropColumn('leader_id');
        });
    }
};
