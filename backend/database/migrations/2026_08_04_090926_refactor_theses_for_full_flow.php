<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Ubah kolom status di tabel theses — tambah status baru
        // MySQL: ubah enum dengan cara drop + recreate
        DB::statement("ALTER TABLE theses MODIFY COLUMN status ENUM(
            'DRAFT',
            'PENGAJUAN_JUDUL',
            'JUDUL_DITOLAK',
            'SEMINAR_PROPOSAL',
            'REVISI_PROPOSAL',
            'PEMERIKSAAN_REVISI',
            'PENUNJUKAN_PEMBIMBING',
            'BIMBINGAN',
            'SIDANG',
            'REVISI_SIDANG',
            'SELESAI',
            'DIPUBLIKASIKAN',
            'GAGAL'
        ) NOT NULL DEFAULT 'DRAFT'");

        // 2. Migrasi status lama ke status baru
        DB::table('theses')->where('status', 'JUDUL_DISETUJUI')->update(['status' => 'SEMINAR_PROPOSAL']);
        DB::table('theses')->where('status', 'PROPOSAL')->update(['status' => 'SEMINAR_PROPOSAL']);
        DB::table('theses')->where('status', 'PENELITIAN')->update(['status' => 'BIMBINGAN']);
        DB::table('theses')->where('status', 'PENULISAN')->update(['status' => 'BIMBINGAN']);
        DB::table('theses')->where('status', 'LULUS')->update(['status' => 'SELESAI']);

        // 3. Tambah kolom baru ke theses
        Schema::table('theses', function (Blueprint $table) {
            $table->string('submission_link')->nullable()->after('proposal_file_url'); // link saat pengajuan
            $table->string('revision_link')->nullable()->after('submission_link');    // link revisi proposal/bimbingan
            $table->string('final_pdf_path')->nullable()->after('revision_link');    // upload skripsi final
            $table->boolean('is_published')->default(false)->after('final_pdf_path');
            $table->timestamp('published_at')->nullable()->after('is_published');
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete()->after('published_at');
            $table->string('repository_url')->nullable()->after('published_by');
            $table->string('cover_image_path')->nullable()->after('repository_url');
            // Penunjukan pembimbing oleh Kaprodi
            $table->foreignId('supervisor_assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('supervisor_assigned_at')->nullable();
        });

        // 4. Tabel revision_reviews — penguji memeriksa revisi proposal
        Schema::create('thesis_revision_reviews', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('thesis_id')->constrained('theses')->cascadeOnDelete();
            $table->foreignId('examiner_id')->constrained('lecturers'); // penguji yang review
            $table->enum('type', ['SEMINAR_PROPOSAL', 'SIDANG_AKHIR'])->default('SEMINAR_PROPOSAL');
            $table->decimal('score', 5, 2)->nullable();
            $table->text('notes')->nullable();
            $table->string('revision_link')->nullable();        // link revisi yang dikirim mahasiswa
            $table->enum('revision_result', ['PERLU_REVISI', 'SELESAI', 'SIAP_SIDANG'])->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });

        // 5. Tabel thesis_seminar_results — hasil seminar proposal
        Schema::create('thesis_seminar_results', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('thesis_id')->constrained('theses')->cascadeOnDelete();
            $table->enum('seminar_type', ['PROPOSAL', 'SIDANG'])->default('PROPOSAL');
            $table->date('seminar_date')->nullable();
            $table->string('room')->nullable();
            $table->enum('result', ['DISETUJUI', 'REVISI', 'TIDAK_LULUS'])->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('thesis_seminar_results');
        Schema::dropIfExists('thesis_revision_reviews');

        Schema::table('theses', function (Blueprint $table) {
            $table->dropColumn([
                'submission_link', 'revision_link', 'final_pdf_path',
                'is_published', 'published_at', 'published_by', 'repository_url',
                'cover_image_path', 'supervisor_assigned_by', 'supervisor_assigned_at',
            ]);
        });

        DB::statement("ALTER TABLE theses MODIFY COLUMN status ENUM(
            'PENGAJUAN_JUDUL','JUDUL_DISETUJUI','JUDUL_DITOLAK',
            'PROPOSAL','SEMINAR_PROPOSAL','REVISI_PROPOSAL',
            'PENELITIAN','PENULISAN',
            'SIDANG','REVISI_SIDANG','LULUS','GAGAL'
        ) NOT NULL DEFAULT 'PENGAJUAN_JUDUL'");
    }
};
