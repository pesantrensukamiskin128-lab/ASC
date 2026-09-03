<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legacy_migration_maps', function (Blueprint $table): void {
            $table->id();
            $table->string('source_system', 32)->default('siakad');
            $table->string('entity', 64);
            $table->string('source_id', 100);
            $table->string('target_table', 64);
            $table->unsignedBigInteger('target_id');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(
                ['source_system', 'entity', 'source_id'],
                'legacy_map_source_unique'
            );
            $table->index(['target_table', 'target_id'], 'legacy_map_target_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legacy_migration_maps');
    }
};
