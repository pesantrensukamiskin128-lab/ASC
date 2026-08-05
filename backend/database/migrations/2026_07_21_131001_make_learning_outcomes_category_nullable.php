<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Ubah enum category menjadi varchar nullable
        Schema::table('learning_outcomes', function (Blueprint $table) {
            $table->string('category', 50)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('learning_outcomes', function (Blueprint $table) {
            $table->string('category', 50)->nullable(false)->change();
        });
    }
};
