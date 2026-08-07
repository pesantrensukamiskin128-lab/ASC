<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outgoing_letters', function (Blueprint $table) {
            $table->json('supporting_documents')->nullable()->after('external_recipients');
        });
    }

    public function down(): void
    {
        Schema::table('outgoing_letters', function (Blueprint $table) {
            $table->dropColumn('supporting_documents');
        });
    }
};
