<?php

namespace Tests\Feature;

use App\Models\Student;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SyncGraduatedStudentsToAlumniTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('students', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('study_program_id')->nullable();
            $table->string('nim')->unique();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->integer('entry_year')->nullable();
            $table->string('status');
            $table->timestamps();
        });
        Schema::create('alumni', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('student_id')->nullable();
            $table->unsignedBigInteger('study_program_id');
            $table->string('nim');
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->integer('entry_year');
            $table->integer('graduation_year');
            $table->string('graduation_date')->nullable();
            $table->decimal('gpa', 4, 2)->nullable();
            $table->string('thesis_title')->nullable();
            $table->string('predicate')->nullable();
            $table->string('photo_path')->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('province')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function test_status_change_to_lulus_automatically_creates_alumni(): void
    {
        $this->travelTo('2026-09-05');
        $student = Student::create([
            'study_program_id' => 7,
            'nim' => '2018110001',
            'name' => 'Mahasiswa Lulus',
            'email' => 'lulusan@example.test',
            'phone' => '08123456789',
            'entry_year' => 2018,
            'status' => 'Aktif',
        ]);

        $student->update(['status' => 'Lulus']);

        $this->assertDatabaseHas('alumni', [
            'student_id' => $student->id,
            'study_program_id' => 7,
            'nim' => '2018110001',
            'name' => 'Mahasiswa Lulus',
            'graduation_year' => 2026,
            'graduation_date' => '2026-09-05',
        ]);
    }

    public function test_sync_command_is_idempotent_and_dry_run_does_not_write(): void
    {
        Student::withoutEvents(fn () => Student::create([
            'study_program_id' => 7,
            'nim' => '2018110002',
            'name' => 'Alumni Tertunda',
            'entry_year' => 2018,
            'status' => 'Lulus',
        ]));

        $this->assertSame(0, Artisan::call('students:sync-alumni', ['--dry-run' => true]));
        $this->assertDatabaseCount('alumni', 0);

        $this->assertSame(0, Artisan::call('students:sync-alumni'));
        $this->assertSame(0, Artisan::call('students:sync-alumni'));

        $this->assertDatabaseCount('alumni', 1);
        $this->assertDatabaseHas('alumni', ['nim' => '2018110002', 'student_id' => 1]);
    }
}
