<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('letter_templates', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('created_by')->constrained('users');
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('letter_type_id')->nullable()->constrained('letter_types')->nullOnDelete();
            $table->string('subject')->nullable();
            $table->text('recipient')->nullable();
            $table->string('attachment_note')->nullable();
            $table->string('city', 100)->default('Bandung');
            $table->longText('body');
            $table->longText('appendix_body')->nullable();
            $table->boolean('is_shared')->default(true);
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('letter_templates'); }
};
