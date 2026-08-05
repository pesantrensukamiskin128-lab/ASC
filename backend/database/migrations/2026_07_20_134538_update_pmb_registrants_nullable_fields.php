<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pmb_registrants', function (Blueprint $table) {
            // Buat field yang sebelumnya NOT NULL menjadi nullable
            // agar bisa save sebagai draft tanpa isi lengkap
            $table->string('birth_place')->nullable()->change();
            $table->date('birth_date')->nullable()->change();
            $table->enum('gender', ['L', 'P'])->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('pmb_registrants', function (Blueprint $table) {
            $table->string('birth_place')->nullable(false)->change();
            $table->date('birth_date')->nullable(false)->change();
            $table->enum('gender', ['L', 'P'])->nullable(false)->change();
        });
    }
};
