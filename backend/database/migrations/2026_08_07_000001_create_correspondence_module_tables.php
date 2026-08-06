<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // =============================================
        // JENIS SURAT
        // =============================================
        Schema::create('letter_types', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('code', 10)->unique();       // A, B, C, SK, dll
            $table->string('name');                      // Surat Keterangan, Surat Tugas, dll
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // =============================================
        // SURAT KELUAR
        // =============================================
        Schema::create('outgoing_letters', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('letter_type_id')->constrained('letter_types');

            // Nomor surat (auto-generated setelah selesai)
            $table->string('letter_number')->nullable()->unique();
            $table->string('subject');                   // Perihal
            $table->text('recipient');                   // Kepada Yth (bisa multi-baris)
            $table->string('attachment_note')->nullable(); // Keterangan lampiran
            $table->string('city', 100)->default('Bandung'); // Tempat terbit
            $table->date('letter_date');                 // Tanggal surat
            $table->longText('body');                    // Isi surat (HTML)
            $table->longText('appendix_body')->nullable(); // Isi lampiran (HTML)

            // Penandatangan & pemeriksa (dari jabatan struktural)
            $table->foreignId('reviewer_id')->nullable()->constrained('users'); // Pemeriksa (opsional)
            $table->foreignId('signer_id')->constrained('users');              // Penandatangan

            // Status alur
            $table->enum('status', [
                'DRAFT',
                'MENUNGGU_PEMERIKSA',
                'REVISI_PEMERIKSA',
                'MENUNGGU_PENANDATANGAN',
                'REVISI_PENANDATANGAN',
                'DITANDATANGANI',
                'TERKIRIM',
            ])->default('DRAFT');

            // Tanda tangan & metadata
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->text('revision_note')->nullable();   // Catatan revisi terakhir
            $table->string('verification_token')->nullable()->unique(); // Untuk QR

            // Penerima eksternal (teks)
            $table->text('external_recipients')->nullable();

            $table->timestamps();
        });

        // Penerima internal surat keluar (many-to-many)
        Schema::create('outgoing_letter_recipients', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('outgoing_letter_id')->constrained('outgoing_letters')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->unique(['outgoing_letter_id', 'user_id']);
        });

        // =============================================
        // SURAT MASUK (Eksternal)
        // =============================================
        Schema::create('incoming_letters', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('created_by')->constrained('users');
            $table->string('letter_number')->nullable(); // Nomor surat pengirim
            $table->string('sender');                    // Nama pengirim / instansi
            $table->string('subject');                   // Perihal
            $table->date('letter_date')->nullable();     // Tanggal di surat
            $table->date('received_date');               // Tanggal diterima
            $table->text('notes')->nullable();           // Catatan
            $table->string('file_path')->nullable();     // File scan surat
            $table->enum('status', ['BARU', 'DIBACA', 'DIDISPOSISI'])->default('BARU');
            $table->timestamps();
        });

        // =============================================
        // DISPOSISI SURAT
        // =============================================
        Schema::create('dispositions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('incoming_letter_id')->constrained('incoming_letters')->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users');       // Pemberi disposisi
            $table->text('instruction');                 // Instruksi disposisi
            $table->text('notes')->nullable();           // Catatan tambahan
            $table->timestamps();
        });

        // Penerima disposisi (many-to-many)
        Schema::create('disposition_recipients', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('disposition_id')->constrained('dispositions')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->text('response')->nullable();        // Jawaban/tindak lanjut
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->unique(['disposition_id', 'user_id']);
        });

        // =============================================
        // AGENDA KEGIATAN & PRESENSI
        // =============================================
        Schema::create('events', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('created_by')->constrained('users');
            $table->string('title');                     // Nama kegiatan
            $table->string('organizer')->nullable();     // Penyelenggara
            $table->enum('category', ['Rapat', 'Seminar', 'Workshop', 'Pelatihan', 'Wisuda', 'Dies Natalis', 'Lainnya'])->default('Rapat');
            $table->enum('type', ['Luring', 'Daring', 'Hibrid'])->default('Luring');
            $table->string('location')->nullable();      // Tempat
            $table->string('meeting_link')->nullable();  // Link meeting online
            $table->date('event_date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->text('description')->nullable();
            $table->string('qr_token')->unique();        // Token untuk QR presensi
            $table->boolean('is_open')->default(true);   // Presensi masih dibuka?
            $table->timestamps();
        });

        // Peserta yang diundang (opsional, untuk tracking)
        Schema::create('event_invitees', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['event_id', 'user_id']);
        });

        // Presensi kehadiran
        Schema::create('event_attendances', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete(); // null = tamu
            // Data tamu (jika user_id null)
            $table->string('guest_name')->nullable();
            $table->string('guest_phone')->nullable();
            $table->string('guest_institution')->nullable();
            $table->string('guest_position')->nullable();
            // Metadata
            $table->enum('method', ['APP', 'FORM'])->default('APP');
            $table->timestamp('attended_at');
            $table->timestamps();

            $table->index(['event_id', 'user_id']);
        });

        // =============================================
        // Seed jenis surat default
        // =============================================
        $this->seedLetterTypes();
    }

    public function down(): void
    {
        Schema::dropIfExists('event_attendances');
        Schema::dropIfExists('event_invitees');
        Schema::dropIfExists('events');
        Schema::dropIfExists('disposition_recipients');
        Schema::dropIfExists('dispositions');
        Schema::dropIfExists('incoming_letters');
        Schema::dropIfExists('outgoing_letter_recipients');
        Schema::dropIfExists('outgoing_letters');
        Schema::dropIfExists('letter_types');
    }

    private function seedLetterTypes(): void
    {
        $types = [
            ['code' => 'A',  'name' => 'Surat Rutin Internal'],
            ['code' => 'B',  'name' => 'Surat Rutin Eksternal'],
            ['code' => 'C',  'name' => 'Surat Keterangan'],
            ['code' => 'D',  'name' => 'Surat Rekomendasi'],
            ['code' => 'E',  'name' => 'Surat Tugas'],
            ['code' => 'F',  'name' => 'Surat Mandat'],
            ['code' => 'G',  'name' => 'Surat Peringatan'],
            ['code' => 'H',  'name' => 'Surat Edaran'],
            ['code' => 'I',  'name' => 'Surat Pengumuman'],
            ['code' => 'SK', 'name' => 'Surat Keputusan'],
        ];

        foreach ($types as $t) {
            \Illuminate\Support\Facades\DB::table('letter_types')->insert(
                array_merge($t, ['is_active' => true, 'created_at' => now(), 'updated_at' => now()])
            );
        }
    }
};
