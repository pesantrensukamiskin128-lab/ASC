<?php

use App\Http\Controllers\Api\AcademicCalendarController;
use App\Http\Controllers\Api\AcademicLeaveController;
use App\Http\Controllers\Api\AcademicYearController;
use App\Http\Controllers\Api\AlumniController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BuildingController;
use App\Http\Controllers\Api\ClassController;
use App\Http\Controllers\Api\ConcentrationController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\CurriculumController;
use App\Http\Controllers\Api\CurriculumObeController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ExamController;
use App\Http\Controllers\Api\FacultyController;
use App\Http\Controllers\Api\FileController;
use App\Http\Controllers\Api\FinanceController;
use App\Http\Controllers\Api\GradeController;
use App\Http\Controllers\Api\GraduationController;
use App\Http\Controllers\Api\GuidanceController;
use App\Http\Controllers\Api\InstitutionController;
use App\Http\Controllers\Api\KrsController;
use App\Http\Controllers\Api\LectureController;
use App\Http\Controllers\Api\LecturerController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PracticalController;
use App\Http\Controllers\Api\QuestionBankController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\ThesisController;
use App\Http\Controllers\Api\TransferCreditController;
use App\Http\Controllers\Api\TwoFactorController;
use App\Http\Controllers\Api\LecturerPositionController;
use App\Http\Controllers\Api\PmbExamTypeController;
use App\Http\Controllers\Api\PmbPathController;
use App\Http\Controllers\Api\PmbPeriodController;
use App\Http\Controllers\Api\PmbPublicController;
use App\Http\Controllers\Api\PmbRegistrantController;
use App\Http\Controllers\Api\RoomController;
use App\Http\Controllers\Api\RpkpsController;
use App\Http\Controllers\Api\SemesterController;
use App\Http\Controllers\Api\StaffController;
use App\Http\Controllers\Api\StudentController;
use App\Http\Controllers\Api\StudyProgramController;
use App\Http\Controllers\Api\TemplateController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\VerifyController;
use App\Http\Controllers\Api\LecturerWorkController;
use App\Http\Controllers\Api\PenelitianController;

/*
|--------------------------------------------------------------------------
| API Routes - Al-Jawami Smart Campus (ASC)
|--------------------------------------------------------------------------
*/

// Public
Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->middleware(\App\Http\Middleware\LoginThrottle::class);
});

// =========================================================================
// VERIFIKASI DOKUMEN (Public - tanpa login, bisa diakses siapapun via QR)
// =========================================================================
Route::prefix('verify')->group(function () {
    Route::get('krs/{id}',       [VerifyController::class, 'verifyKrs']);
    Route::get('rpkps/{code}',   [VerifyController::class, 'verifyRpkps']);
});

// 2FA verification (setelah login, sebelum full access)
Route::middleware('auth:sanctum')->prefix('auth/2fa')->group(function () {
    Route::post('verify', [TwoFactorController::class, 'verify']);
});

// Public institution info (untuk halaman login & header)
Route::get('institution/public', [InstitutionController::class, 'public']);

// =========================================================
// REPOSITORY PUBLIK (tanpa login)
// =========================================================
Route::prefix('repository')->group(function () {
    Route::get('/',                             [\App\Http\Controllers\Api\RepositoryController::class, 'index']);
    Route::get('stats',                         [\App\Http\Controllers\Api\RepositoryController::class, 'stats']);
    Route::get('penelitian/{id}',               [\App\Http\Controllers\Api\RepositoryController::class, 'showPenelitian']);
    Route::get('skripsi/{id}',                  [\App\Http\Controllers\Api\RepositoryController::class, 'showSkripsi']);
    Route::get('karya-dosen/{id}',              [\App\Http\Controllers\Api\RepositoryController::class, 'showKaryaDosen']);
    // Download butuh auth — di-handle di dalam controller
    Route::get('download/{source}/{id}/{fileType}', [\App\Http\Controllers\Api\RepositoryController::class, 'download']);
});

// Public PMB endpoints (tanpa login)
Route::prefix('pmb')->group(function () {
    Route::get('active-period', [PmbPublicController::class, 'activePeriod']);
    Route::get('paths', [PmbPublicController::class, 'paths']);
    Route::get('programs', [PmbPublicController::class, 'programs']);
    Route::post('register', [PmbPublicController::class, 'register']);
});

// Protected
Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::prefix('auth')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::put('profile', [AuthController::class, 'updateProfile']);
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('logout-all', [AuthController::class, 'logoutAll']);
        // 2FA management
        Route::post('2fa/setup', [TwoFactorController::class, 'setup']);
        Route::post('2fa/confirm', [TwoFactorController::class, 'confirm']);
        Route::post('2fa/disable', [TwoFactorController::class, 'disable']);
        Route::post('2fa/recovery-codes', [TwoFactorController::class, 'recoveryCodes']);
    });

    // Dashboard (role-aware)
    Route::get('dashboard', [DashboardController::class, 'index']);

    // --- Template download (semua role terautentikasi) ---
    Route::get('templates/{type}', [TemplateController::class, 'download']);

    // --- File Manager (upload/list/delete) ---
    Route::post('files/upload', [FileController::class, 'upload']);
    Route::get('files', [FileController::class, 'index']);
    Route::delete('files/{file}', [FileController::class, 'destroy']);

    // --- Dropdown / select endpoints (semua role) ---
    Route::get('faculties/all', [FacultyController::class, 'all']);
    Route::get('study-programs/all', [StudyProgramController::class, 'all']);
    Route::get('concentrations/all', [ConcentrationController::class, 'all']);
    Route::get('academic-years/all', [AcademicYearController::class, 'all']);
    Route::get('lecturers/all', [LecturerController::class, 'all']);
    Route::get('courses/all', [CourseController::class, 'all']);
    Route::get('buildings/all', [BuildingController::class, 'all']);
    Route::get('rooms/all', [RoomController::class, 'all']);
    Route::get('classes/all', [ClassController::class, 'all']);

    // --- User Management ---
    Route::middleware('role:SUPER_ADMIN|ADMIN_AKADEMIK')->group(function () {
        Route::apiResource('users', UserController::class);
        Route::get('roles', [UserController::class, 'roles']);
    });

    // --- Institusi (SUPER_ADMIN only) ---
    Route::middleware('role:SUPER_ADMIN')->group(function () {
        Route::apiResource('institutions', InstitutionController::class);
        Route::post('institutions/{institution}/logo', [InstitutionController::class, 'uploadLogo']);
    });

    // --- Master Data ---
    // Data yang bisa diakses semua user terautentikasi (read-only)
    Route::get('academic-calendars', [AcademicCalendarController::class, 'index']);
    Route::get('academic-calendars/export', [AcademicCalendarController::class, 'export']);
    Route::get('academic-calendars/pdf', [AcademicCalendarController::class, 'downloadPdf']);
    Route::get('academic-calendars/{academic_calendar}', [AcademicCalendarController::class, 'show']);
    Route::get('semesters', [SemesterController::class, 'index']);
    Route::get('semesters/{semester}', [SemesterController::class, 'show']);

    Route::middleware('role:SUPER_ADMIN|ADMIN_AKADEMIK')->group(function () {
        Route::apiResource('faculties', FacultyController::class);
        Route::apiResource('study-programs', StudyProgramController::class);
        Route::apiResource('concentrations', ConcentrationController::class);
        Route::apiResource('academic-years', AcademicYearController::class);
        Route::post('academic-years/{academicYear}/activate', [AcademicYearController::class, 'activate']);
        Route::apiResource('semesters', SemesterController::class)->except(['index', 'show']);
        Route::post('semesters/{semester}/activate', [SemesterController::class, 'activate']);
        // CRUD kalender hanya admin
        Route::post('academic-calendars', [AcademicCalendarController::class, 'store']);
        Route::post('academic-calendars/import', [AcademicCalendarController::class, 'import']);
        Route::put('academic-calendars/{academic_calendar}', [AcademicCalendarController::class, 'update']);
        Route::delete('academic-calendars/{academic_calendar}', [AcademicCalendarController::class, 'destroy']);
        // Upload kop surat
        Route::post('institutions/{institution}/letterhead', [InstitutionController::class, 'uploadLetterhead']);
        // Courses — export/import harus sebelum apiResource
        Route::get('courses/export', [CourseController::class, 'export']);
        Route::post('courses/import', [CourseController::class, 'import']);
        Route::apiResource('courses', CourseController::class);
        Route::apiResource('buildings', BuildingController::class);
        Route::apiResource('rooms', RoomController::class);
    });

    // --- Tenaga Kependidikan ---
    Route::middleware('role:SUPER_ADMIN|ADMIN_AKADEMIK')->group(function () {
        Route::get('staff/export', [StaffController::class, 'export']);
        Route::post('staff/import', [StaffController::class, 'import']);
        Route::apiResource('staff', StaffController::class);
    });

    // --- Dosen ---
    Route::middleware('role:SUPER_ADMIN|ADMIN_AKADEMIK')->group(function () {
        Route::get('lecturers/export', [LecturerController::class, 'export']);
        Route::post('lecturers/import', [LecturerController::class, 'import']);
        Route::post('lecturers/{lecturer}/photo', [LecturerController::class, 'uploadPhoto']);
        Route::apiResource('lecturers', LecturerController::class);
        // Jabatan struktural
        Route::get('lecturer-positions/available', [LecturerPositionController::class, 'availablePositions']);
        Route::get('lecturers/{lecturer}/positions', [LecturerPositionController::class, 'index']);
        Route::post('lecturers/{lecturer}/positions', [LecturerPositionController::class, 'store']);
        Route::put('lecturers/{lecturer}/positions/{position}', [LecturerPositionController::class, 'update']);
        Route::delete('lecturers/{lecturer}/positions/{position}', [LecturerPositionController::class, 'destroy']);
    });

    // --- Mahasiswa ---
    Route::middleware('role:SUPER_ADMIN|ADMIN_AKADEMIK|DOSEN|ADMIN_PMB')->group(function () {
        Route::get('students/export', [StudentController::class, 'export']);
        Route::post('students/import', [StudentController::class, 'import']);
        Route::apiResource('students', StudentController::class);
    });

    // --- KRS & Perwalian ---
    Route::middleware('role:SUPER_ADMIN|ADMIN_AKADEMIK|DOSEN|MAHASISWA')->group(function () {
        Route::get('classes', [ClassController::class, 'index']);
        Route::get('classes/{class}', [ClassController::class, 'show']);
    });

    Route::middleware('role:SUPER_ADMIN|ADMIN_AKADEMIK|DOSEN')->group(function () {
        Route::post('classes', [ClassController::class, 'store']);
        Route::put('classes/{class}', [ClassController::class, 'update']);
        Route::delete('classes/{class}', [ClassController::class, 'destroy']);
        Route::post('classes/{class}/schedules', [ClassController::class, 'addSchedule']);
        Route::delete('classes/{class}/schedules/{schedule}', [ClassController::class, 'removeSchedule']);
    });

    Route::middleware('role:SUPER_ADMIN|ADMIN_AKADEMIK|DOSEN|MAHASISWA')->group(function () {
        Route::apiResource('krs', KrsController::class)->only(['index', 'show', 'store'])->parameters(['krs' => 'krs']);
        Route::get('krs/{krs}/pdf', [KrsController::class, 'downloadPdf']);
        Route::post('krs/{krs}/courses', [KrsController::class, 'addCourse']);
        Route::delete('krs/{krs}/courses/{detail}', [KrsController::class, 'removeCourse']);
        Route::post('krs/{krs}/submit', [KrsController::class, 'submit']);
    });

    // --- Perkuliahan (Dosen & Mahasiswa) ---
    Route::middleware('role:SUPER_ADMIN|ADMIN_AKADEMIK|DOSEN|MAHASISWA')->group(function () {
        // Read — mahasiswa bisa lihat info kelas & jurnal
        Route::get('lectures/{class}', [LectureController::class, 'show']);
        Route::get('lectures/{class}/journals', [LectureController::class, 'journalList']);
        Route::get('lectures/{class}/rps-plans', [LectureController::class, 'getFromRps']);
        Route::get('lectures/{class}/members', [LectureController::class, 'memberList']);
        Route::get('lectures/journals/{journal}/attendances', [LectureController::class, 'attendanceList']);
        Route::post('lectures/journals/{journal}/attendances', [LectureController::class, 'saveAttendance']);
        Route::get('lectures/{class}/materials', [LectureController::class, 'materialList']);
        Route::get('lectures/{class}/assignments', [LectureController::class, 'assignmentList']);
        Route::get('lectures/{class}/announcements', [LectureController::class, 'announcementList']);
        // Mahasiswa submit tugas
        Route::post('assignments/{assignment}/submit', [LectureController::class, 'submitAssignment']);
    });

    // Write — hanya dosen pengampu kelas (atau admin)
    Route::middleware(['role:SUPER_ADMIN|ADMIN_AKADEMIK|DOSEN', 'class.lecturer'])->group(function () {
        Route::post('lectures/{class}/journals', [LectureController::class, 'storeJournal']);
        Route::post('lectures/{class}/materials', [LectureController::class, 'storeMaterial']);
        Route::post('lectures/{class}/assignments', [LectureController::class, 'storeAssignment']);
        Route::post('assignments/submissions/{submission}/grade', [LectureController::class, 'gradeSubmission']);
        Route::post('lectures/{class}/announcements', [LectureController::class, 'storeAnnouncement']);
    });

    // Delete routes (tidak butuh class param, tapi harus DOSEN)
    Route::middleware('role:SUPER_ADMIN|ADMIN_AKADEMIK|DOSEN')->group(function () {
        Route::delete('lectures/journals/{journal}', [LectureController::class, 'destroyJournal']);
        Route::put('lectures/journals/{journal}', [LectureController::class, 'updateJournal']);
        Route::delete('lectures/materials/{material}', [LectureController::class, 'destroyMaterial']);
        Route::put('lectures/materials/{material}', [LectureController::class, 'updateMaterial']);
        Route::delete('lectures/assignments/{assignment}', [LectureController::class, 'destroyAssignment']);
        Route::put('lectures/assignments/{assignment}', [LectureController::class, 'updateAssignment']);
        Route::delete('lectures/announcements/{announcement}', [LectureController::class, 'destroyAnnouncement']);
        Route::put('lectures/announcements/{announcement}', [LectureController::class, 'updateAnnouncement']);
    });

    // --- Ujian ---
    // Read: dosen & mahasiswa bisa lihat daftar & detail ujian
    Route::middleware('role:SUPER_ADMIN|ADMIN_AKADEMIK|DOSEN|MAHASISWA')->group(function () {
        Route::get('exams', [ExamController::class, 'index']);
        Route::get('exams/{exam}', [ExamController::class, 'show']);
    });

    // Write: hanya dosen & admin
    Route::middleware('role:SUPER_ADMIN|ADMIN_AKADEMIK|DOSEN')->group(function () {
        Route::post('exams', [ExamController::class, 'store']);
        Route::put('exams/{exam}', [ExamController::class, 'update']);
        Route::delete('exams/{exam}', [ExamController::class, 'destroy']);
        Route::post('exams/{exam}/questions', [ExamController::class, 'storeQuestion']);
        Route::put('exams/{exam}/questions/{question}', [ExamController::class, 'updateQuestion']);
        Route::delete('exams/{exam}/questions/{question}', [ExamController::class, 'destroyQuestion']);
        // Hasil ujian
        Route::get('exams/{exam}/results', [ExamController::class, 'results']);
        Route::get('exams/{exam}/results/{student}', [ExamController::class, 'studentResult']);
        Route::post('exams/{exam}/results/{student}/grade', [ExamController::class, 'gradeAnswer']);
    });

    Route::middleware('role:SUPER_ADMIN|ADMIN_AKADEMIK|DOSEN|MAHASISWA')->group(function () {
        Route::post('exams/{exam}/start', [ExamController::class, 'startExam']);
        Route::post('exams/{exam}/submit-answers', [ExamController::class, 'submitAnswer']);
        Route::post('exams/{exam}/tab-switch', [ExamController::class, 'logTabSwitch']);
        Route::get('exams/{exam}/my-result', [ExamController::class, 'myResult']);
    });

    // --- Bank Soal ---
    Route::middleware('role:SUPER_ADMIN|ADMIN_AKADEMIK|DOSEN')->group(function () {
        Route::get('question-banks', [QuestionBankController::class, 'index']);
        Route::post('question-banks', [QuestionBankController::class, 'store']);
        Route::get('question-banks/{questionBank}', [QuestionBankController::class, 'show']);
        Route::put('question-banks/{questionBank}', [QuestionBankController::class, 'update']);
        Route::delete('question-banks/{questionBank}', [QuestionBankController::class, 'destroy']);
        Route::post('question-banks/{questionBank}/items', [QuestionBankController::class, 'storeItem']);
        Route::put('question-banks/{questionBank}/items/{item}', [QuestionBankController::class, 'updateItem']);
        Route::delete('question-banks/{questionBank}/items/{item}', [QuestionBankController::class, 'destroyItem']);
        Route::post('question-banks/{questionBank}/import-to-exam', [QuestionBankController::class, 'importToExam']);
        Route::post('question-banks/{questionBank}/import-excel', [QuestionBankController::class, 'importFromExcel']);
        Route::get('question-banks/{questionBank}/template', [QuestionBankController::class, 'downloadTemplate']);
    });

    // --- Notifikasi ---
    Route::get('notifications', [NotificationController::class, 'index']);
    Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::post('notifications/{notification}/read', [NotificationController::class, 'markAsRead']);
    Route::post('notifications/read-all', [NotificationController::class, 'markAllAsRead']);
    Route::delete('notifications/{notification}', [NotificationController::class, 'destroy']);
    Route::middleware('role:SUPER_ADMIN|ADMIN_AKADEMIK')->group(function () {
        Route::post('notifications/send', [NotificationController::class, 'send']);
    });

    // --- Penilaian ---
    Route::middleware('role:SUPER_ADMIN|ADMIN_AKADEMIK|DOSEN')->group(function () {
        Route::get('grades', [GradeController::class, 'index']);
        Route::post('grades', [GradeController::class, 'store']);
        Route::post('grades/batch', [GradeController::class, 'batchStore']);
        Route::post('grades/import', [GradeController::class, 'importGrades']);
        Route::post('grades/import-class', [GradeController::class, 'importClassGrades']);
        Route::get('grades/template-class', [GradeController::class, 'downloadClassTemplate']);
        Route::get('grades/schema', [GradeController::class, 'schema']);
    });

    Route::middleware('role:SUPER_ADMIN|ADMIN_AKADEMIK|DOSEN|MAHASISWA')->group(function () {
        Route::get('grades/khs', [GradeController::class, 'khs']);
        Route::get('grades/transcript', [GradeController::class, 'transcript']);
    });

    Route::middleware('role:SUPER_ADMIN|ADMIN_AKADEMIK|DOSEN')->group(function () {
        Route::post('krs/{krs}/approve', [KrsController::class, 'approve']);
        Route::post('krs/{krs}/reject', [KrsController::class, 'reject']);
        Route::post('krs/{krs}/sign-kaprodi', [KrsController::class, 'signByKaprodi']);
        Route::delete('krs/{krs}', [KrsController::class, 'destroy']);
    });

    // --- Kurikulum OBE ---
    Route::middleware('role:SUPER_ADMIN|ADMIN_AKADEMIK|DOSEN')->group(function () {
        Route::apiResource('curriculums', CurriculumController::class);
        Route::post('curriculums/{curriculum}/courses', [CurriculumController::class, 'syncCourses']);
        Route::post('curriculums/{curriculum}/learning-outcomes', [CurriculumController::class, 'storeLearningOutcome']);
        Route::put('curriculums/{curriculum}/learning-outcomes/{lo}', [CurriculumController::class, 'updateLearningOutcome']);
        Route::delete('curriculums/{curriculum}/learning-outcomes/{lo}', [CurriculumController::class, 'destroyLearningOutcome']);

        // OBE: Profil Lulusan
        Route::get('curriculums/{curriculum}/graduate-profiles', [CurriculumObeController::class, 'graduateProfiles']);
        Route::post('curriculums/{curriculum}/graduate-profiles', [CurriculumObeController::class, 'storeGraduateProfile']);
        Route::put('curriculums/{curriculum}/graduate-profiles/{profile}', [CurriculumObeController::class, 'updateGraduateProfile']);
        Route::delete('curriculums/{curriculum}/graduate-profiles/{profile}', [CurriculumObeController::class, 'destroyGraduateProfile']);

        // OBE: Matriks CPL–MK
        Route::get('curriculums/{curriculum}/cpl-course-matrix', [CurriculumObeController::class, 'cplCourseMatrix']);
        Route::post('curriculums/{curriculum}/cpl-course-mapping', [CurriculumObeController::class, 'updateCplCourseMapping']);

        // OBE: CPMK
        Route::get('curriculums/{curriculum}/courses/{courseId}/cpmks', [CurriculumObeController::class, 'courseLearningOutcomes']);
        Route::post('curriculums/{curriculum}/cpmks', [CurriculumObeController::class, 'storeCpmk']);
        Route::get('curriculums/{curriculum}/courses/{courseId}/cpmk-cpl-matrix', [CurriculumObeController::class, 'cpmkCplMatrix']);
    });

    // --- RPKPS / RPS Digital ---
    // Mahasiswa bisa lihat & download RPS yang sudah disetujui/dikunci
    Route::middleware('role:SUPER_ADMIN|ADMIN_AKADEMIK|DOSEN|MAHASISWA')->group(function () {
        Route::get('rpkps', [RpkpsController::class, 'index']);
        Route::get('rpkps/{rpkp}', [RpkpsController::class, 'show']);
        Route::get('rpkps/{rpkp}/pdf', [RpkpsController::class, 'downloadPdf']);
    });

    // Dosen & admin: kelola RPS
    Route::middleware('role:SUPER_ADMIN|ADMIN_AKADEMIK|DOSEN')->group(function () {
        Route::get('rpkps/statistics', [RpkpsController::class, 'statistics']);
        Route::get('rpkps/my-courses', [RpkpsController::class, 'myAssignedCourses']);
        Route::post('rpkps', [RpkpsController::class, 'store']);
        Route::put('rpkps/{rpkp}', [RpkpsController::class, 'update']);
        Route::delete('rpkps/{rpkp}', [RpkpsController::class, 'destroy']);
        Route::post('rpkps/{rpkp}/cpls', [RpkpsController::class, 'syncCpls']);
        Route::post('rpkps/{rpkp}/cpmks', [RpkpsController::class, 'storeCpmk']);
        Route::put('rpkps/{rpkp}/cpmks/{cpmk}', [RpkpsController::class, 'updateCpmk']);
        Route::delete('rpkps/{rpkp}/cpmks/{cpmk}', [RpkpsController::class, 'destroyCpmk']);
        Route::post('rpkps/{rpkp}/weekly-plans', [RpkpsController::class, 'saveWeeklyPlans']);
        Route::post('rpkps/{rpkp}/assessments', [RpkpsController::class, 'saveAssessments']);
        Route::post('rpkps/{rpkp}/references', [RpkpsController::class, 'saveReferences']);
        Route::post('rpkps/{rpkp}/submit', [RpkpsController::class, 'submit']);
        Route::post('rpkps/{rpkp}/duplicate', [RpkpsController::class, 'duplicate']);
    });

    Route::middleware('role:SUPER_ADMIN|ADMIN_AKADEMIK|DOSEN')->group(function () {
        Route::post('rpkps/{rpkp}/approve', [RpkpsController::class, 'approve']);
        Route::post('rpkps/{rpkp}/revise', [RpkpsController::class, 'revise']);
        Route::post('rpkps/{rpkp}/lock', [RpkpsController::class, 'lock']);
    });

    // =========================================================================
    // PMB - PENERIMAAN MAHASISWA BARU
    // =========================================================================

    // --- Self-service calon mahasiswa (semua user terautentikasi) ---
    Route::prefix('pmb/my')->group(function () {
        Route::get('registration', [PmbPublicController::class, 'myRegistration']);
        Route::post('form', [PmbPublicController::class, 'saveForm']);
        Route::post('photo', [PmbPublicController::class, 'uploadPhoto']);
        Route::post('submit', [PmbPublicController::class, 'submit']);
        Route::post('payment', [PmbPublicController::class, 'confirmPayment']);
        Route::get('result', [PmbPublicController::class, 'result']);
        Route::get('card-pdf', [PmbPublicController::class, 'downloadCard']);
    });

    // --- Admin PMB: pengaturan ---
    Route::middleware('role:SUPER_ADMIN|ADMIN_PMB')->group(function () {
        Route::apiResource('pmb-periods', PmbPeriodController::class);
        Route::get('pmb-periods-all', [PmbPeriodController::class, 'all']);
        Route::apiResource('pmb-paths', PmbPathController::class);
        Route::apiResource('pmb-exam-types', PmbExamTypeController::class);
    });

    // --- Admin PMB: manajemen pendaftar & seleksi ---
    Route::middleware('role:SUPER_ADMIN|ADMIN_PMB|ADMIN_AKADEMIK')->group(function () {
        Route::get('pmb-registrants', [PmbRegistrantController::class, 'index']);
        Route::get('pmb-registrants/statistics', [PmbRegistrantController::class, 'statistics']);
        Route::get('pmb-registrants/{pmbRegistrant}', [PmbRegistrantController::class, 'show']);
        Route::delete('pmb-registrants/{pmbRegistrant}', [PmbRegistrantController::class, 'destroy']);
        Route::post('pmb-registrants/{pmbRegistrant}/verify', [PmbRegistrantController::class, 'verify']);
        Route::post('pmb-registrants/{pmbRegistrant}/set-selection', [PmbRegistrantController::class, 'setSelectionStatus']);
        Route::post('pmb-registrants/{pmbRegistrant}/scores', [PmbRegistrantController::class, 'inputScore']);
        Route::post('pmb-registrants/{pmbRegistrant}/calculate', [PmbRegistrantController::class, 'calculateResult']);
        Route::post('pmb-registrants/{pmbRegistrant}/final-status', [PmbRegistrantController::class, 'setFinalStatus']);
        Route::post('pmb-registrants/{pmbRegistrant}/re-registration', [PmbRegistrantController::class, 'processReRegistration']);
    });

    // =========================================================================
    // KEUANGAN MAHASISWA
    // =========================================================================

    // --- Admin Keuangan ---
    Route::middleware('role:SUPER_ADMIN|ADMIN_KEUANGAN|ADMIN_AKADEMIK')->group(function () {
        // Jenis Tagihan
        Route::get('finance/fee-types', [FinanceController::class, 'feeTypes']);
        Route::post('finance/fee-types', [FinanceController::class, 'storeFeeType']);
        Route::put('finance/fee-types/{feeType}', [FinanceController::class, 'updateFeeType']);
        Route::delete('finance/fee-types/{feeType}', [FinanceController::class, 'destroyFeeType']);
        // Struktur Biaya
        Route::get('finance/fee-structures', [FinanceController::class, 'feeStructures']);
        Route::post('finance/fee-structures', [FinanceController::class, 'storeFeeStructure']);
        Route::put('finance/fee-structures/{feeStructure}', [FinanceController::class, 'updateFeeStructure']);
        Route::delete('finance/fee-structures/{feeStructure}', [FinanceController::class, 'destroyFeeStructure']);
        // Tagihan
        Route::get('finance/invoices', [FinanceController::class, 'invoices']);
        Route::get('finance/invoices/{invoice}', [FinanceController::class, 'showInvoice']);
        Route::post('finance/invoices', [FinanceController::class, 'storeInvoice']);
        Route::post('finance/invoices/generate-batch', [FinanceController::class, 'generateBatch']);
        Route::post('finance/invoices/{invoice}/cancel', [FinanceController::class, 'cancelInvoice']);
        Route::post('finance/invoices/{invoice}/waive', [FinanceController::class, 'waiveInvoice']);
        Route::delete('finance/invoices/{invoice}', [FinanceController::class, 'destroyInvoice']);
        // Pembayaran
        Route::get('finance/payments', [FinanceController::class, 'payments']);
        Route::post('finance/payments', [FinanceController::class, 'storePayment']);
        Route::post('finance/payments/{payment}/verify', [FinanceController::class, 'verifyPayment']);
        // Beasiswa
        Route::get('finance/scholarships', [FinanceController::class, 'scholarships']);
        Route::post('finance/scholarships', [FinanceController::class, 'storeScholarship']);
        Route::put('finance/scholarships/{scholarship}', [FinanceController::class, 'updateScholarship']);
        Route::delete('finance/scholarships/{scholarship}', [FinanceController::class, 'destroyScholarship']);
        Route::get('finance/student-scholarships', [FinanceController::class, 'studentScholarships']);
        Route::post('finance/student-scholarships', [FinanceController::class, 'assignScholarship']);
        Route::post('finance/student-scholarships/{studentScholarship}/revoke', [FinanceController::class, 'revokeScholarship']);
        // Dashboard & Reports
        Route::get('finance/dashboard', [FinanceController::class, 'dashboard']);
    });

    // --- Mahasiswa: lihat tagihan & summary ---
    Route::middleware('role:SUPER_ADMIN|ADMIN_KEUANGAN|ADMIN_AKADEMIK|DOSEN|MAHASISWA')->group(function () {
        Route::get('finance/my-invoices', [FinanceController::class, 'invoices']);
        Route::get('finance/my-payments', [FinanceController::class, 'payments']);
        Route::get('finance/my-summary', [FinanceController::class, 'studentSummary']);
        Route::get('finance/check-payment', [FinanceController::class, 'checkPaymentStatus']);
        Route::post('finance/my-payment', [FinanceController::class, 'studentPayment']);
    });

    // =========================================================================
    // BIMBINGAN AKADEMIK
    // =========================================================================

    Route::middleware('role:SUPER_ADMIN|ADMIN_AKADEMIK|DOSEN|MAHASISWA')->group(function () {
        // Sesi bimbingan
        Route::get('guidance/sessions', [GuidanceController::class, 'sessions']);
        Route::get('guidance/sessions/{session}', [GuidanceController::class, 'showSession']);
        Route::post('guidance/sessions/request', [GuidanceController::class, 'requestSession']);
        Route::post('guidance/sessions', [GuidanceController::class, 'createSession']);
        Route::put('guidance/sessions/{session}/status', [GuidanceController::class, 'updateSessionStatus']);
        Route::post('guidance/sessions/{session}/notes', [GuidanceController::class, 'addNote']);
        // Catatan akademik
        Route::get('guidance/academic-notes', [GuidanceController::class, 'academicNotes']);
        Route::post('guidance/academic-notes', [GuidanceController::class, 'storeAcademicNote']);
        Route::delete('guidance/academic-notes/{note}', [GuidanceController::class, 'destroyAcademicNote']);
        // Peringatan akademik
        Route::get('guidance/warnings', [GuidanceController::class, 'warnings']);
        Route::post('guidance/warnings', [GuidanceController::class, 'storeWarning']);
        Route::post('guidance/warnings/{warning}/resolve', [GuidanceController::class, 'resolveWarning']);
        // Dashboard dosen wali
        Route::get('guidance/advisor-dashboard', [GuidanceController::class, 'advisorDashboard']);
        Route::get('guidance/my-students', [GuidanceController::class, 'myStudents']);
    });

    // =========================================================================
    // CUTI AKADEMIK
    // =========================================================================

    Route::middleware('role:SUPER_ADMIN|ADMIN_AKADEMIK|DOSEN|MAHASISWA')->group(function () {
        Route::get('academic-leaves', [AcademicLeaveController::class, 'index']);
        Route::get('academic-leaves/history', [AcademicLeaveController::class, 'history']);
        Route::get('academic-leaves/{academicLeave}', [AcademicLeaveController::class, 'show']);
        Route::post('academic-leaves', [AcademicLeaveController::class, 'store']);
        Route::post('academic-leaves/{academicLeave}/document', [AcademicLeaveController::class, 'uploadDocument']);
        Route::post('academic-leaves/{academicLeave}/approve', [AcademicLeaveController::class, 'approve']);
        Route::post('academic-leaves/{academicLeave}/activate', [AcademicLeaveController::class, 'activate']);
        Route::post('academic-leaves/{academicLeave}/cancel', [AcademicLeaveController::class, 'cancel']);
    });

    // =========================================================================
    // TRANSFER NILAI (MAHASISWA PINDAHAN)
    // =========================================================================

    Route::middleware('role:SUPER_ADMIN|ADMIN_AKADEMIK|DOSEN')->group(function () {
        // Dashboard & list
        Route::get('transfer-credits/dashboard', [TransferCreditController::class, 'dashboard']);
        Route::get('transfer-credits/template-source-courses', [TransferCreditController::class, 'templateSourceCourses']);
        Route::get('transfer-credits', [TransferCreditController::class, 'index']);
        Route::get('transfer-credits/{application}', [TransferCreditController::class, 'show']);
        Route::post('transfer-credits', [TransferCreditController::class, 'store']);
        Route::post('transfer-credits/{application}/submit', [TransferCreditController::class, 'submit']);
        // Mata kuliah asal
        Route::post('transfer-credits/{application}/source-courses', [TransferCreditController::class, 'addSourceCourse']);
        Route::post('transfer-credits/{application}/source-courses/import', [TransferCreditController::class, 'importSourceCourses']);
        Route::delete('transfer-credits/{application}/source-courses/{course}', [TransferCreditController::class, 'removeSourceCourse']);
        // Konversi / Pemetaan
        Route::post('transfer-credits/{application}/conversions', [TransferCreditController::class, 'mapConversion']);
        Route::delete('transfer-credits/{application}/conversions/{conversion}', [TransferCreditController::class, 'removeConversion']);
        // Dokumen
        Route::post('transfer-credits/{application}/documents', [TransferCreditController::class, 'addDocument']);
        Route::post('transfer-credits/documents/{document}/verify', [TransferCreditController::class, 'verifyDocument']);
        // Evaluasi
        Route::post('transfer-credits/{application}/evaluate', [TransferCreditController::class, 'evaluate']);
        // Approval
        Route::post('transfer-credits/{application}/approve', [TransferCreditController::class, 'approve']);
        // Finalisasi
        Route::post('transfer-credits/{application}/finalize', [TransferCreditController::class, 'finalize']);
        // Institusi asal
        Route::get('transfer-credits-institutions', [TransferCreditController::class, 'institutions']);
        Route::post('transfer-credits-institutions', [TransferCreditController::class, 'storeInstitution']);
    });

    // =========================================================================
    // PRAKTIKUM / KKN / PPL / MAGANG / PKL
    // =========================================================================

    Route::middleware('role:SUPER_ADMIN|ADMIN_AKADEMIK|DOSEN|LP2M')->group(function () {
        Route::post('practical-programs', [PracticalController::class, 'storeProgram']);
        Route::put('practical-programs/{program}', [PracticalController::class, 'updateProgram']);
        Route::delete('practical-programs/{program}', [PracticalController::class, 'destroyProgram']);
        // Locations
        Route::post('practical-programs/{program}/locations', [PracticalController::class, 'storeLocation']);
        Route::delete('practical-programs/{program}/locations/{location}', [PracticalController::class, 'destroyLocation']);
        // Groups
        Route::post('practical-programs/{program}/groups', [PracticalController::class, 'storeGroup']);
        Route::delete('practical-programs/{program}/groups/{group}', [PracticalController::class, 'destroyGroup']);
        // Participants
        Route::get('practical-programs/{program}/participants', [PracticalController::class, 'participants']);
        Route::post('practical-programs/{program}/participants', [PracticalController::class, 'registerParticipant']);
        Route::put('practical-participants/{participant}', [PracticalController::class, 'updateParticipant']);
        Route::delete('practical-participants/{participant}', [PracticalController::class, 'removeParticipant']);
        // Assessments
        Route::get('practical-participants/{participant}/assessments', [PracticalController::class, 'assessments']);
        Route::post('practical-participants/{participant}/assessments', [PracticalController::class, 'storeAssessment']);
        // Logbook approval
        Route::post('practical-logbooks/{logbook}/review', [PracticalController::class, 'approveLogbook']);
        // Reports
        Route::post('practical-reports/{report}/review', [PracticalController::class, 'reviewReport']);
    });

    // Mahasiswa: logbook, presensi, laporan
    Route::middleware('role:SUPER_ADMIN|ADMIN_AKADEMIK|DOSEN|MAHASISWA|LP2M')->group(function () {
        Route::get('practical-programs', [PracticalController::class, 'programs']);
        Route::get('practical-programs/{program}', [PracticalController::class, 'showProgram']);
        Route::get('practical-my-programs', [PracticalController::class, 'myPrograms']);
        Route::post('practical-programs/{program}/self-register', [PracticalController::class, 'selfRegister']);
        Route::get('practical-participants/{participant}/logbooks', [PracticalController::class, 'logbooks']);
        Route::post('practical-participants/{participant}/logbooks', [PracticalController::class, 'storeLogbook']);
        Route::get('practical-participants/{participant}/attendances', [PracticalController::class, 'attendances']);
        Route::post('practical-participants/{participant}/attendances', [PracticalController::class, 'storeAttendance']);
        Route::post('practical-participants/{participant}/reports', [PracticalController::class, 'storeReport']);
    });

    // =========================================================================
    // SKRIPSI / TUGAS AKHIR
    // =========================================================================

    // =========================================================================
    // SKRIPSI / TUGAS AKHIR
    // =========================================================================

    // Baca: semua role bisa lihat
    Route::middleware('role:SUPER_ADMIN|ADMIN_AKADEMIK|DOSEN|MAHASISWA|LP2M')->group(function () {
        Route::get('theses/dashboard', [ThesisController::class, 'dashboard']);
        Route::get('theses', [ThesisController::class, 'index']);
        Route::get('theses/{thesis}', [ThesisController::class, 'show']);
    });

    // Mahasiswa: buat draft, edit draft, submit, upload revisi, upload final
    Route::middleware('role:SUPER_ADMIN|ADMIN_AKADEMIK|DOSEN|MAHASISWA')->group(function () {
        Route::post('theses', [ThesisController::class, 'store']);
        Route::put('theses/{thesis}/draft', [ThesisController::class, 'updateDraft']);
        Route::post('theses/{thesis}/submit', [ThesisController::class, 'submitToKaprodi']);
        Route::post('theses/{thesis}/resubmit', [ThesisController::class, 'resubmit']);
        Route::post('theses/{thesis}/revision-link', [ThesisController::class, 'uploadRevisionLink']);
        Route::post('theses/{thesis}/upload-final', [ThesisController::class, 'uploadFinal']);
    });

    // Kaprodi & Dosen Wali: review judul
    Route::middleware('role:SUPER_ADMIN|ADMIN_AKADEMIK|DOSEN')->group(function () {
        Route::post('theses/{thesis}/review-title', [ThesisController::class, 'reviewTitle']);
    });

    // Kaprodi & Admin: kelola seminar, pembimbing, sidang
    Route::middleware('role:SUPER_ADMIN|ADMIN_AKADEMIK|DOSEN')->group(function () {
        Route::post('theses/{thesis}/schedule-seminar', [ThesisController::class, 'scheduleSeminar']);
        Route::post('theses/{thesis}/seminar-result', [ThesisController::class, 'recordSeminarResult']);
        Route::post('theses/{thesis}/assign-supervisors', [ThesisController::class, 'assignSupervisors']);
        Route::post('theses/{thesis}/schedule-defense', [ThesisController::class, 'scheduleDefense']);
        Route::post('theses/{thesis}/ready-defense', [ThesisController::class, 'declareReadyForDefense']);
        // Tambah/hapus penguji individual
        Route::post('theses/{thesis}/examiners', [ThesisController::class, 'addExaminer']);
        Route::delete('theses/{thesis}/examiners/{examiner}', [ThesisController::class, 'removeExaminer']);
        // Hapus pembimbing
        Route::delete('theses/{thesis}/supervisors/{supervisor}', [ThesisController::class, 'removeSupervisor']);
        // Update judul
        Route::post('theses/{thesis}/title', [ThesisController::class, 'updateTitle']);
        // Hasil sidang & nilai
        Route::post('thesis-defenses/{defense}/result', [ThesisController::class, 'updateDefenseResult']);
        Route::post('thesis-defenses/{defense}/scores', [ThesisController::class, 'storeDefenseScore']);
    });

    // LP2M: publikasi skripsi ke repository
    Route::middleware('role:SUPER_ADMIN|ADMIN_AKADEMIK|LP2M')->group(function () {
        Route::post('theses/{thesis}/publish', [ThesisController::class, 'publish']);
    });

    // Penguji: input nilai seminar & review revisi
    Route::middleware('role:SUPER_ADMIN|ADMIN_AKADEMIK|DOSEN')->group(function () {
        Route::post('theses/{thesis}/seminar-score', [ThesisController::class, 'inputSeminarScore']);
        Route::post('theses/{thesis}/review-revision', [ThesisController::class, 'reviewRevision']);
        // Bimbingan (dosen catat)
        Route::post('theses/{thesis}/guidances', [ThesisController::class, 'storeGuidance']);
    });

    // =========================================================================
    // WISUDA
    // =========================================================================

    Route::middleware('role:SUPER_ADMIN|ADMIN_AKADEMIK|DOSEN|MAHASISWA')->group(function () {
        // Periode
        Route::get('graduation/periods', [GraduationController::class, 'periods']);
        Route::post('graduation/periods', [GraduationController::class, 'storePeriod']);
        Route::put('graduation/periods/{period}', [GraduationController::class, 'updatePeriod']);
        Route::delete('graduation/periods/{period}', [GraduationController::class, 'destroyPeriod']);
        // Pendaftaran
        Route::get('graduation/registrations', [GraduationController::class, 'registrations']);
        Route::get('graduation/registrations/{registration}', [GraduationController::class, 'showRegistration']);
        Route::post('graduation/register', [GraduationController::class, 'register']);
        Route::post('graduation/registrations/{registration}/status', [GraduationController::class, 'updateRegistrationStatus']);
        Route::post('graduation/registrations/{registration}/predicate', [GraduationController::class, 'setPredicate']);
        // Verifikasi
        Route::post('graduation/verifications/{verification}', [GraduationController::class, 'verify']);
        Route::post('graduation/registrations/{registration}/auto-verify-finance', [GraduationController::class, 'autoVerifyFinance']);
        // Dashboard
        Route::get('graduation/dashboard', [GraduationController::class, 'dashboard']);
    });

    // =========================================================================
    // ALUMNI
    // =========================================================================

    Route::middleware('role:SUPER_ADMIN|ADMIN_AKADEMIK|DOSEN')->group(function () {
        Route::get('alumni/dashboard', [AlumniController::class, 'dashboard']);
        Route::get('alumni/export', [AlumniController::class, 'export']);
        Route::get('alumni', [AlumniController::class, 'index']);
        Route::get('alumni/{alumni}', [AlumniController::class, 'show']);
        Route::post('alumni', [AlumniController::class, 'store']);
        Route::put('alumni/{alumni}', [AlumniController::class, 'update']);
        Route::delete('alumni/{alumni}', [AlumniController::class, 'destroy']);
        // Employment
        Route::post('alumni/{alumni}/employments', [AlumniController::class, 'storeEmployment']);
        Route::delete('alumni/{alumni}/employments/{employment}', [AlumniController::class, 'destroyEmployment']);
        // Further Study
        Route::post('alumni/{alumni}/further-studies', [AlumniController::class, 'storeFurtherStudy']);
        Route::delete('alumni/{alumni}/further-studies/{study}', [AlumniController::class, 'destroyFurtherStudy']);
        // Tracer Study
        Route::get('tracer-studies', [AlumniController::class, 'tracerStudies']);
        Route::post('alumni/{alumni}/tracer-study', [AlumniController::class, 'storeTracerStudy']);
    });

    // =========================================================================
    // LAPORAN & STATISTIK
    // =========================================================================

    Route::middleware('role:SUPER_ADMIN|ADMIN_AKADEMIK|DOSEN')->prefix('reports')->group(function () {
        Route::get('summary', [ReportController::class, 'summary']);
        Route::get('students', [ReportController::class, 'students']);
        Route::get('academic', [ReportController::class, 'academic']);
        Route::get('finance', [ReportController::class, 'finance']);
        Route::get('human-resources', [ReportController::class, 'humanResources']);
    });

    // =========================================================================
    // AUDIT LOG
    // =========================================================================

    Route::middleware('role:SUPER_ADMIN')->prefix('audit-logs')->group(function () {
        Route::get('/', [AuditLogController::class, 'index']);
        Route::get('/actions', [AuditLogController::class, 'actions']);
        Route::get('/model-types', [AuditLogController::class, 'modelTypes']);
        Route::get('/{auditLog}', [AuditLogController::class, 'show']);
    });

    // =========================================================================
    // KARYA DOSEN
    // =========================================================================

    // Dosen: kelola karya miliknya sendiri
    Route::middleware('role:SUPER_ADMIN|ADMIN_AKADEMIK|DOSEN|LP2M')->group(function () {
        Route::get('lecturer-works/stats', [LecturerWorkController::class, 'stats']);
        Route::get('lecturer-works', [LecturerWorkController::class, 'index']);
        Route::get('lecturer-works/{lecturerWork}', [LecturerWorkController::class, 'show']);
    });

    Route::middleware('role:SUPER_ADMIN|ADMIN_AKADEMIK|DOSEN')->group(function () {
        Route::post('lecturer-works', [LecturerWorkController::class, 'store']);
        Route::post('lecturer-works/{lecturerWork}', [LecturerWorkController::class, 'update']); // POST karena ada file upload
        Route::delete('lecturer-works/{lecturerWork}', [LecturerWorkController::class, 'destroy']);
        Route::post('lecturer-works/{lecturerWork}/submit', [LecturerWorkController::class, 'submit']);
    });

    // Admin/LP2M: verifikasi & publikasi
    Route::middleware('role:SUPER_ADMIN|ADMIN_AKADEMIK|LP2M')->group(function () {
        Route::post('lecturer-works/{lecturerWork}/verify', [LecturerWorkController::class, 'verify']);
        Route::post('lecturer-works/{lecturerWork}/publish', [LecturerWorkController::class, 'publish']);
    });

    // =========================================================================
    // HIBAH PENELITIAN & PENGABDIAN KEPADA MASYARAKAT
    // =========================================================================

    // Periode hibah (LP2M/Admin kelola, semua bisa baca)
    Route::get('penelitian-periods', [PenelitianController::class, 'periods']);
    Route::middleware('role:SUPER_ADMIN|ADMIN_AKADEMIK|LP2M')->group(function () {
        Route::post('penelitian-periods', [PenelitianController::class, 'storePeriod']);
        Route::put('penelitian-periods/{period}', [PenelitianController::class, 'updatePeriod']);
        Route::delete('penelitian-periods/{period}', [PenelitianController::class, 'destroyPeriod']);
    });

    // Repository publik (semua terautentikasi bisa lihat)
    Route::get('penelitian-repository', [PenelitianController::class, 'repository']);

    // Read: semua role yang relevan
    Route::middleware('role:SUPER_ADMIN|ADMIN_AKADEMIK|LP2M|DOSEN|KEUANGAN')->group(function () {
        Route::get('penelitian/stats', [PenelitianController::class, 'stats']);
        Route::get('penelitian', [PenelitianController::class, 'index']);
        Route::get('penelitian/{penelitian}', [PenelitianController::class, 'show']);
    });

    // Dosen: buat, edit, hapus draft; submit; upload laporan
    Route::middleware('role:SUPER_ADMIN|ADMIN_AKADEMIK|DOSEN')->group(function () {
        Route::post('penelitian', [PenelitianController::class, 'store']);
        Route::put('penelitian/{penelitian}', [PenelitianController::class, 'update']);
        Route::delete('penelitian/{penelitian}', [PenelitianController::class, 'destroy']);
        Route::post('penelitian/{penelitian}/submit-kaprodi', [PenelitianController::class, 'submitToKaprodi']);
        Route::post('penelitian/{penelitian}/upload-revisi-proposal', [PenelitianController::class, 'uploadRevisiProposal']);
        Route::post('penelitian/{penelitian}/upload-laporan-kemajuan', [PenelitianController::class, 'uploadLaporanKemajuan']);
        Route::post('penelitian/{penelitian}/upload-revisi-kemajuan', [PenelitianController::class, 'uploadRevisiKemajuan']);
        Route::post('penelitian/{penelitian}/upload-laporan-akhir', [PenelitianController::class, 'uploadLaporanAkhir']);
        Route::post('penelitian/{penelitian}/upload-laporan-final', [PenelitianController::class, 'uploadLaporanFinal']);
        Route::post('penelitian/{penelitian}/upload-lpj', [PenelitianController::class, 'uploadLpj']);
        Route::post('penelitian/{penelitian}/upload-revisi-lpj', [PenelitianController::class, 'uploadRevisiLpj']);
        // Reviewer (dosen yang ditugaskan): submit review & review monev
        Route::post('penelitian/{penelitian}/submit-review', [PenelitianController::class, 'submitReview']);
    });

    // Ka.Prodi: review proposal
    Route::middleware('role:SUPER_ADMIN|ADMIN_AKADEMIK|DOSEN')->group(function () {
        Route::post('penelitian/{penelitian}/review-kaprodi', [PenelitianController::class, 'reviewKaprodi']);
    });

    // LP2M: seleksi, kontrak, monev, seminar, LPJ, publikasi
    Route::middleware('role:SUPER_ADMIN|ADMIN_AKADEMIK|LP2M')->group(function () {
        Route::post('penelitian/{penelitian}/assign-reviewers', [PenelitianController::class, 'assignReviewers']);
        Route::post('penelitian/{penelitian}/assign-monev-reviewer', [PenelitianController::class, 'assignMonevReviewer']);
        Route::post('penelitian/{penelitian}/seleksi-result', [PenelitianController::class, 'setSeleksiResult']);
        Route::post('penelitian/{penelitian}/save-kontrak', [PenelitianController::class, 'saveKontrak']);
        Route::post('penelitian/{penelitian}/monev-result', [PenelitianController::class, 'setMonevResult']);
        Route::post('penelitian/{penelitian}/seminar-date', [PenelitianController::class, 'setSeminarDate']);
        Route::post('penelitian/{penelitian}/seminar-result', [PenelitianController::class, 'setSeminarResult']);
        Route::post('penelitian/{penelitian}/review-lpj', [PenelitianController::class, 'reviewLpj']);
        Route::post('penelitian/{penelitian}/publish', [PenelitianController::class, 'publish']);
    });

    // Keuangan: alokasi & cairkan dana
    Route::middleware('role:SUPER_ADMIN|ADMIN_AKADEMIK|KEUANGAN')->group(function () {
        Route::post('penelitian/{penelitian}/allocate-funding', [PenelitianController::class, 'allocateFunding']);
        Route::post('penelitian/{penelitian}/disburse-funding/{stage}', [PenelitianController::class, 'disburseFunding']);
    });
});

