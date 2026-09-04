<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\LecturerController;
use App\Models\Lecturer;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LecturerStatusTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('study_programs', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });
        Schema::create('lecturers', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('study_program_id')->nullable();
            $table->string('nidn')->nullable()->unique();
            $table->string('nuptk')->nullable()->unique();
            $table->string('nip')->nullable();
            $table->string('degree_front')->nullable();
            $table->string('degree_back')->nullable();
            $table->string('full_name');
            $table->string('gender')->nullable();
            $table->string('birth_place')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->string('academic_rank')->nullable();
            $table->string('employment_status')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
        });
    }

    public function test_status_can_be_set_when_creating_and_updating_a_lecturer(): void
    {
        $createRequest = Request::create('/api/lecturers', 'POST', [
            'full_name' => 'Dosen Nonaktif',
            'status' => false,
        ]);
        $created = app(LecturerController::class)->store($createRequest)->getData(true);

        $this->assertFalse($created['data']['status']);

        $lecturer = Lecturer::findOrFail($created['data']['id']);
        $updateRequest = Request::create('/api/lecturers/'.$lecturer->id, 'PUT', ['status' => true]);
        $updated = app(LecturerController::class)->update($updateRequest, $lecturer)->getData(true);

        $this->assertTrue($updated['data']['status']);
    }
}
