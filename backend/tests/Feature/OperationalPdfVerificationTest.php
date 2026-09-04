<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\PmbPublicController;
use App\Http\Controllers\Api\VerifyController;
use App\Models\Event;
use App\Models\PmbRegistrant;
use App\Models\User;
use App\Support\OperationalDocumentVerification;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OperationalPdfVerificationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('institutions', function (Blueprint $table) {
            $table->id();
            $table->string('code');
            $table->string('name');
            $table->string('logo_path')->nullable();
            $table->string('legal_entity_name')->nullable();
        });
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('username')->unique();
            $table->string('email')->unique();
            $table->string('password');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('academic_years', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });
        Schema::create('pmb_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id');
            $table->string('name');
            $table->date('registration_start');
            $table->date('registration_end');
            $table->date('selection_date')->nullable();
            $table->timestamps();
        });
        Schema::create('pmb_paths', function (Blueprint $table) {
            $table->id();
            $table->string('code');
            $table->string('name');
            $table->timestamps();
        });
        Schema::create('pmb_registrants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->foreignId('pmb_period_id');
            $table->foreignId('pmb_path_id')->nullable();
            $table->string('registration_number');
            $table->string('full_name');
            $table->string('gender');
            $table->string('birth_place');
            $table->date('birth_date');
            $table->string('school_name')->nullable();
            $table->foreignId('choice_1')->nullable();
            $table->foreignId('choice_2')->nullable();
            $table->foreignId('choice_3')->nullable();
            $table->string('photo_path')->nullable();
            $table->string('status');
            $table->foreignId('verified_by')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by');
            $table->string('title');
            $table->string('organizer')->nullable();
            $table->string('category');
            $table->string('type');
            $table->string('location')->nullable();
            $table->date('event_date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->string('qr_token');
            $table->boolean('is_open')->default(true);
            $table->timestamps();
        });
        Schema::create('event_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id');
            $table->foreignId('user_id')->nullable();
            $table->string('guest_name')->nullable();
            $table->string('guest_phone')->nullable();
            $table->string('guest_institution')->nullable();
            $table->string('guest_position')->nullable();
            $table->string('method')->default('APP');
            $table->timestamp('attended_at');
            $table->timestamps();
        });
    }

    public function test_event_attendance_and_pmb_card_pdfs_have_verifiable_tokens(): void
    {
        DB::table('institutions')->insert(['code' => 'ASC', 'name' => 'STAI Al-Jawami']);
        DB::table('users')->insert([
            'id' => 1, 'name' => 'Admin ASC', 'username' => 'admin', 'email' => 'admin@example.test',
            'password' => bcrypt('secret'), 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('academic_years')->insert([
            'id' => 1, 'name' => 'Tahun Akademik 2026/2027', 'start_date' => '2026-08-01',
            'end_date' => '2027-07-31', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('pmb_periods')->insert([
            'id' => 1, 'academic_year_id' => 1, 'name' => 'Gelombang 1',
            'registration_start' => '2026-01-01', 'registration_end' => '2026-12-31',
            'selection_date' => '2026-10-01', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('pmb_paths')->insert([
            'id' => 1, 'code' => 'REG', 'name' => 'Reguler', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('pmb_registrants')->insert([
            'id' => 1, 'user_id' => 1, 'pmb_period_id' => 1, 'pmb_path_id' => 1,
            'registration_number' => 'PMB-2026-00001', 'full_name' => 'Calon Mahasiswa',
            'gender' => 'L', 'birth_place' => 'Bandung', 'birth_date' => '2007-01-01',
            'status' => 'TERVERIFIKASI', 'verified_by' => 1, 'verified_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $user = User::findOrFail(1);
        $event = Event::create([
            'created_by' => $user->id, 'title' => 'Rapat Akademik', 'organizer' => 'Bagian Akademik',
            'category' => 'Rapat', 'type' => 'Luring', 'location' => 'Aula', 'event_date' => '2026-09-04',
        ]);

        $eventPdf = app(EventController::class)->exportPdf($event);
        $this->assertStringStartsWith('%PDF-', (string) $eventPdf->getContent());

        $this->actingAs($user);
        $pmbPdf = app(PmbPublicController::class)->downloadCard();
        $this->assertStringStartsWith('%PDF-', (string) $pmbPdf->getContent());

        $eventToken = OperationalDocumentVerification::issue('event-attendance', $event->id, (string) $event->qr_token);
        $eventResult = app(VerifyController::class)
            ->verifyEventAttendance(Request::create('/api/verify/event-attendance/'.$eventToken), $eventToken)
            ->getData(true);
        $this->assertTrue($eventResult['valid']);

        $registrant = PmbRegistrant::findOrFail(1);
        $pmbToken = OperationalDocumentVerification::issue('pmb-card', $registrant->id, $registrant->registration_number);
        $pmbResult = app(VerifyController::class)
            ->verifyPmbCard(Request::create('/api/verify/pmb-card/'.$pmbToken), $pmbToken)
            ->getData(true);
        $this->assertTrue($pmbResult['valid']);

        if ($qaDirectory = getenv('OPERATIONAL_PDF_QA_DIR')) {
            file_put_contents($qaDirectory.'/Daftar-Hadir-QA.pdf', (string) $eventPdf->getContent());
            file_put_contents($qaDirectory.'/Kartu-Peserta-QA.pdf', (string) $pmbPdf->getContent());
        }
    }
}
