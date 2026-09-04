<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\GradeController;
use App\Http\Controllers\Api\StudentController;
use App\Http\Controllers\Api\VerifyController;
use App\Models\Student;
use App\Models\StudentGrade;
use App\Models\User;
use App\Support\AcademicDocumentVerification;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;

class StudentAcademicPortalTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createMinimalSchema();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        DB::table('roles')->insert([
            ['id' => 1, 'name' => 'MAHASISWA', 'guard_name' => 'web'],
            ['id' => 2, 'name' => 'ADMIN_AKADEMIK', 'guard_name' => 'web'],
        ]);
        DB::table('users')->insert([
            [
                'id' => 1, 'name' => 'Mahasiswa Satu', 'username' => 'M001',
                'email' => 'm001@example.test', 'password' => bcrypt('secret'), 'is_active' => true,
                'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'id' => 3, 'name' => 'Admin Akademik', 'username' => 'admin.akademik',
                'email' => 'admin@example.test', 'password' => bcrypt('secret'), 'is_active' => true,
                'created_at' => now(), 'updated_at' => now(),
            ],
        ]);
        DB::table('model_has_roles')->insert([
            ['role_id' => 1, 'model_type' => User::class, 'model_id' => 1],
            ['role_id' => 2, 'model_type' => User::class, 'model_id' => 3],
        ]);
        DB::table('institutions')->insert([
            'code' => 'ASC', 'name' => 'Sekolah Tinggi Agama Islam Al-Jawami',
            'address' => 'Jl. Pendidikan No. 1, Bandung',
            'letterhead_path' => '../../../tests/Fixtures/letterhead.svg',
        ]);
        DB::table('faculties')->insert(['id' => 1, 'code' => 'FT', 'name' => 'Fakultas Tarbiyah']);
        DB::table('lecturers')->insert([
            [
                'id' => 1, 'nidn' => '0011223344', 'full_name' => 'Ahmad Fauzi',
                'degree_front' => 'Dr.', 'degree_back' => 'M.Pd', 'status' => true,
            ],
            [
                'id' => 2, 'nidn' => '0099887766', 'full_name' => 'Siti Rahmawati',
                'degree_front' => 'Dr.', 'degree_back' => 'M.Pd', 'status' => true,
            ],
        ]);
        DB::table('lecturer_positions')->insert([
            'lecturer_id' => 2, 'position_code' => 'WK1', 'position_name' => 'Wakil Ketua I',
            'is_active' => true,
        ]);
        DB::table('study_programs')->insert([
            'id' => 1, 'faculty_id' => 1, 'head_lecturer_id' => 1,
            'code' => 'P1', 'name' => 'Pendidikan Agama Islam', 'level' => 'S1',
        ]);
        DB::table('students')->insert([
            [
                'id' => 1, 'user_id' => 1, 'study_program_id' => 1, 'advisor_id' => 1, 'nim' => 'M001',
                'name' => 'Mahasiswa Satu', 'status' => 'Aktif', 'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'id' => 2, 'user_id' => null, 'study_program_id' => 1, 'advisor_id' => null, 'nim' => 'M002',
                'name' => 'Mahasiswa Dua', 'status' => 'Aktif', 'created_at' => now(), 'updated_at' => now(),
            ],
        ]);
        DB::table('semesters')->insert([
            'id' => 1, 'name' => 'Ganjil 2020/2021', 'type' => 'Ganjil',
            'start_date' => '2020-09-01', 'end_date' => '2021-01-31',
        ]);
        DB::table('student_semester_summaries')->insert([
            'student_id' => 1, 'semester_id' => 1, 'status' => 'AKTIF',
            'semester_gpa' => 3.50, 'cumulative_gpa' => 3.25,
            'credits_taken' => 20, 'total_credits' => 40,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('courses')->insert(['id' => 1, 'code' => 'MK1', 'name' => 'Mata Kuliah 1', 'credits' => 2]);
        DB::table('student_grades')->insert([
            [
                'student_id' => 1, 'course_id' => 1, 'semester_id' => 1,
                'letter_grade' => 'A', 'grade_point' => 4, 'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'student_id' => 2, 'course_id' => 1, 'semester_id' => 1,
                'letter_grade' => 'E', 'grade_point' => 0, 'created_at' => now(), 'updated_at' => now(),
            ],
        ]);
    }

    public function test_portal_returns_authenticated_students_history_and_grades_only(): void
    {
        $user = User::findOrFail(1);

        $historyRequest = Request::create('/api/students/me/academic-history', 'GET');
        $historyRequest->setUserResolver(fn () => $user);
        $history = app(StudentController::class)->myAcademicHistory($historyRequest)->getData(true);

        $this->assertSame('M001', $history['student']['nim']);
        $this->assertCount(1, $history['summaries']);
        $this->assertSame('3.50', $history['summaries'][0]['semester_gpa']);
        $this->assertSame(20, $history['summaries'][0]['credits_taken']);

        // Meskipun ID mahasiswa lain dikirim, akun mahasiswa tetap menerima nilainya sendiri.
        $khsRequest = Request::create('/api/grades/khs', 'GET', [
            'student_id' => 2,
            'semester_id' => 1,
        ]);
        $khsRequest->setUserResolver(fn () => $user);
        $khs = app(GradeController::class)->khs($khsRequest)->getData(true);

        $this->assertCount(1, $khs['grades']);
        $this->assertSame('A', $khs['grades'][0]['letter_grade']);
        $this->assertEquals(3.50, $khs['ips']);

        $transcriptRequest = Request::create('/api/grades/transcript', 'GET', ['student_id' => 2]);
        $transcriptRequest->setUserResolver(fn () => $user);
        $transcript = app(GradeController::class)->transcript($transcriptRequest)->getData(true);

        $this->assertCount(1, $transcript['grades']);
        $this->assertSame('A', $transcript['grades'][0]['letter_grade']);
        $this->assertEquals(3.25, $transcript['ipk']);
    }

    public function test_pdf_and_admin_excel_exports_are_valid_and_protected(): void
    {
        $studentUser = User::findOrFail(1);
        $adminUser = User::findOrFail(3);

        $khsRequest = Request::create('/api/grades/khs/pdf', 'GET', ['semester_id' => 1]);
        $khsRequest->setUserResolver(fn () => $studentUser);
        $khsResponse = app(GradeController::class)->khsPdf($khsRequest);
        $khsBytes = (string) $khsResponse->getContent();
        $this->assertStringStartsWith('%PDF-', $khsBytes);
        $this->assertStringContainsString('KHS-M001-Ganjil-2020-2021.pdf', (string) $khsResponse->headers->get('content-disposition'));

        $transcriptRequest = Request::create('/api/grades/transcript/pdf', 'GET');
        $transcriptRequest->setUserResolver(fn () => $studentUser);
        $transcriptResponse = app(GradeController::class)->transcriptPdf($transcriptRequest);
        $transcriptBytes = (string) $transcriptResponse->getContent();
        $this->assertStringStartsWith('%PDF-', $transcriptBytes);

        $excelRequest = Request::create('/api/grades/transcript/excel', 'GET', ['student_id' => 1]);
        $excelRequest->setUserResolver(fn () => $adminUser);
        $excelResponse = app(GradeController::class)->transcriptExcel($excelRequest);
        $this->assertInstanceOf(BinaryFileResponse::class, $excelResponse);
        $excelBytes = file_get_contents($excelResponse->getFile()->getPathname());
        $this->assertIsString($excelBytes);
        $this->assertStringStartsWith('PK', $excelBytes);

        $this->actingAs($studentUser)
            ->get('/api/grades/transcript/excel?student_id=1')
            ->assertForbidden();

        DB::table('student_grades')->where('student_id', 1)->delete();
        $emptyExcelResponse = app(GradeController::class)->transcriptExcel($excelRequest);
        $this->assertStringStartsWith('PK', file_get_contents($emptyExcelResponse->getFile()->getPathname()));
    }

    public function test_academic_document_token_verifies_current_data_and_rejects_changes(): void
    {
        $student = Student::with(['studyProgram.faculty', 'studyProgram.headLecturer'])->findOrFail(1);
        $grades = StudentGrade::where('student_id', 1)->get();
        $token = AcademicDocumentVerification::issue('khs', $student, $grades, 1);
        $this->assertNotNull(AcademicDocumentVerification::decode(str_replace('_', '.', $token)));

        $request = Request::create('/api/verify/khs/'.$token, 'GET', ['signer' => 'kaprodi']);
        $verified = app(VerifyController::class)->verifyKhs($request, $token);
        $verifiedData = $verified->getData(true);

        $this->assertTrue($verifiedData['valid']);
        $this->assertSame('M001', $verifiedData['student']['nim']);
        $this->assertTrue($verifiedData['signer_info']['signed']);

        $advisorRequest = Request::create('/api/verify/khs/'.$token, 'GET', ['signer' => 'dosen_wali']);
        $advisorData = app(VerifyController::class)->verifyKhs($advisorRequest, $token)->getData(true);
        $this->assertSame('Dosen Pembimbing Akademik', $advisorData['signer_info']['label']);
        $this->assertSame('Dr. Ahmad Fauzi, M.Pd.', $advisorData['signer_info']['name']);
        $this->assertTrue($advisorData['signer_info']['signed']);

        $studentRequest = Request::create('/api/verify/khs/'.$token, 'GET', ['signer' => 'mahasiswa']);
        $studentData = app(VerifyController::class)->verifyKhs($studentRequest, $token)->getData(true);
        $this->assertSame('Mahasiswa Satu', $studentData['signer_info']['name']);
        $this->assertTrue($studentData['signer_info']['signed']);

        $transcriptToken = AcademicDocumentVerification::issue('transcript', $student, $grades);
        $wk1Request = Request::create('/api/verify/transcript/'.$transcriptToken, 'GET', ['signer' => 'waket1']);
        $wk1Data = app(VerifyController::class)->verifyTranscript($wk1Request, $transcriptToken)->getData(true);
        $this->assertSame('Wakil Ketua I', $wk1Data['signer_info']['label']);
        $this->assertSame('Dr. Siti Rahmawati, M.Pd.', $wk1Data['signer_info']['name']);
        $this->assertTrue($wk1Data['signer_info']['signed']);

        DB::table('student_grades')->where('student_id', 1)->update([
            'grade_point' => 3.5,
            'updated_at' => now()->addMinute(),
        ]);
        $obsolete = app(VerifyController::class)->verifyKhs($request, $token);

        $this->assertSame(409, $obsolete->getStatusCode());
        $this->assertFalse($obsolete->getData(true)['valid']);
        $this->assertNull(AcademicDocumentVerification::decode($token.'tampered'));
    }

    private function createMinimalSchema(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('username')->unique();
            $table->string('email')->nullable();
            $table->string('password');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->unique(['name', 'guard_name']);
        });
        Schema::create('model_has_roles', function (Blueprint $table): void {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->primary(['role_id', 'model_id', 'model_type']);
        });
        Schema::create('study_programs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('faculty_id')->nullable();
            $table->unsignedBigInteger('head_lecturer_id')->nullable();
            $table->string('code');
            $table->string('name');
            $table->string('level')->nullable();
        });
        Schema::create('institutions', function (Blueprint $table): void {
            $table->id();
            $table->string('code');
            $table->string('name');
            $table->string('address')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('letterhead_path')->nullable();
            $table->timestamps();
        });
        Schema::create('faculties', function (Blueprint $table): void {
            $table->id();
            $table->string('code');
            $table->string('name');
        });
        Schema::create('lecturers', function (Blueprint $table): void {
            $table->id();
            $table->string('nidn')->nullable();
            $table->string('degree_front')->nullable();
            $table->string('degree_back')->nullable();
            $table->string('full_name');
            $table->boolean('status')->default(true);
        });
        Schema::create('lecturer_positions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('lecturer_id');
            $table->string('position_code');
            $table->string('position_name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('students', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('study_program_id');
            $table->unsignedBigInteger('advisor_id')->nullable();
            $table->string('nim')->unique();
            $table->string('name');
            $table->string('status');
            $table->unsignedInteger('current_semester')->nullable();
            $table->timestamps();
        });
        Schema::create('semesters', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('type');
            $table->date('start_date');
            $table->date('end_date');
        });
        Schema::create('student_semester_summaries', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('semester_id');
            $table->string('status');
            $table->decimal('semester_gpa', 4, 2)->nullable();
            $table->decimal('cumulative_gpa', 4, 2)->nullable();
            $table->unsignedSmallInteger('credit_limit')->nullable();
            $table->unsignedSmallInteger('credits_taken')->nullable();
            $table->unsignedSmallInteger('required_credits')->nullable();
            $table->unsignedSmallInteger('elective_credits')->nullable();
            $table->unsignedSmallInteger('total_credits')->nullable();
            $table->timestamps();
        });
        Schema::create('student_status_histories', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('semester_id')->nullable();
            $table->string('status');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->text('reason')->nullable();
            $table->string('decree_number')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
        Schema::create('courses', function (Blueprint $table): void {
            $table->id();
            $table->string('code');
            $table->string('name');
            $table->unsignedInteger('credits');
        });
        Schema::create('student_grades', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('course_id');
            $table->unsignedBigInteger('semester_id');
            $table->string('letter_grade')->nullable();
            $table->decimal('final_score', 5, 2)->nullable();
            $table->decimal('grade_point', 3, 2)->nullable();
            $table->timestamps();
        });
    }
}
