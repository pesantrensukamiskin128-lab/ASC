<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cpl_graduate_profile', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('learning_outcome_id')->constrained('learning_outcomes')->cascadeOnDelete();
            $table->foreignId('graduate_profile_id')->constrained('graduate_profiles')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['learning_outcome_id', 'graduate_profile_id'], 'cpl_gp_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cpl_graduate_profile');
    }
};
