<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('semesters', function (Blueprint $table) {
            // Tambah kolom name setelah academic_year_id
            $table->string('name')->nullable()->after('academic_year_id');
        });

        // Isi name dari type yang sudah ada sebagai default
        DB::statement("UPDATE semesters SET name = type WHERE name IS NULL");

        Schema::table('semesters', function (Blueprint $table) {
            $table->string('name')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('semesters', function (Blueprint $table) {
            $table->dropColumn('name');
        });
    }
};
