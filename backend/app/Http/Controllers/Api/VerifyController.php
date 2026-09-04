<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AcademicCalendar;
use App\Models\AcademicYear;
use App\Models\Event;
use App\Models\Krs;
use App\Models\LecturerPosition;
use App\Models\OutgoingLetter;
use App\Models\PmbRegistrant;
use App\Models\Rpkps;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentGrade;
use App\Models\User;
use App\Support\AcademicDocumentVerification;
use App\Support\OperationalDocumentVerification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class VerifyController extends Controller
{
    public function verifyKhs(Request $request, string $token): JsonResponse
    {
        return $this->verifyAcademicDocument($request, $token, 'khs');
    }

    public function verifyTranscript(Request $request, string $token): JsonResponse
    {
        return $this->verifyAcademicDocument($request, $token, 'transcript');
    }

    public function verifyEventAttendance(Request $request, string $token): JsonResponse
    {
        $id = OperationalDocumentVerification::id($token);
        $event = $id ? Event::with('creator')->withCount('attendances')->find($id) : null;
        if (! $event || ! OperationalDocumentVerification::matches($token, 'event-attendance', $event->id, (string) $event->qr_token)) {
            return response()->json(['valid' => false, 'message' => 'Token verifikasi daftar hadir tidak valid.'], 404);
        }

        $signerName = $event->organizer ?: $event->creator?->name;

        return response()->json([
            'valid' => true,
            'document' => 'DAFTAR HADIR AGENDA',
            'document_type' => 'event-attendance',
            'event' => [
                'title' => $event->title,
                'date' => $event->event_date?->translatedFormat('d F Y'),
                'location' => $event->location,
                'organizer' => $event->organizer,
                'attendances_count' => $event->attendances_count,
            ],
            'signed_by' => $signerName,
            'signer_position' => 'Penanggung Jawab/Penyelenggara',
            'is_valid' => true,
            'signer_info' => $request->query('signer') === 'organizer' ? [
                'label' => 'Penanggung Jawab/Penyelenggara',
                'name' => $signerName,
                'signed' => filled($signerName),
            ] : null,
        ]);
    }

    public function verifyPmbCard(Request $request, string $token): JsonResponse
    {
        $id = OperationalDocumentVerification::id($token);
        $registrant = $id ? PmbRegistrant::with(['period', 'path', 'studyProgramChoice1', 'verifiedBy'])->find($id) : null;
        if (! $registrant || ! OperationalDocumentVerification::matches($token, 'pmb-card', $registrant->id, (string) $registrant->registration_number)) {
            return response()->json(['valid' => false, 'message' => 'Token verifikasi kartu peserta tidak valid.'], 404);
        }

        $signerName = $registrant->verifiedBy?->name ?? 'Panitia PMB';

        return response()->json([
            'valid' => true,
            'document' => 'KARTU PESERTA PMB',
            'document_type' => 'pmb-card',
            'registrant' => [
                'registration_number' => $registrant->registration_number,
                'name' => $registrant->full_name,
                'period' => $registrant->period?->name,
                'path' => $registrant->path?->name,
                'study_program' => $registrant->studyProgramChoice1?->name,
                'status' => $registrant->status,
            ],
            'signed_by' => $signerName,
            'signer_position' => 'Verifikator/Panitia PMB',
            'issued_at' => $registrant->verified_at?->translatedFormat('d F Y, H:i'),
            'is_valid' => true,
            'signer_info' => $request->query('signer') === 'verifier' ? [
                'label' => 'Verifikator/Panitia PMB',
                'name' => $signerName,
                'signed' => true,
                'signed_at' => $registrant->verified_at?->translatedFormat('d F Y, H:i'),
            ] : null,
        ]);
    }

    /**
     * Verifikasi dokumen KRS via QR Code
     * URL: /api/verify/krs/{id}?signer=kaprodi|dosen_wali|mahasiswa
     */
    public function verifyKrs(Request $request, int $id): JsonResponse
    {
        $krs = Krs::with([
            'student.studyProgram',
            'semester.academicYear',
            'advisor',
            'details.course',
        ])->find($id);

        if (! $krs) {
            return response()->json([
                'valid' => false,
                'message' => 'Dokumen KRS tidak ditemukan.',
            ], 404);
        }

        $signer = $request->query('signer', '');

        $signatureMap = [
            'kaprodi' => [
                'label' => 'Ketua Program Studi',
                'signed' => ! is_null($krs->signed_by_kaprodi_at),
                'signed_at' => $krs->signed_by_kaprodi_at ? $krs->signed_by_kaprodi_at->format('d F Y, H:i') : null,
            ],
            'dosen_wali' => [
                'label' => 'Dosen Wali / Pembimbing Akademik',
                'signed' => ! is_null($krs->approved_at),
                'signed_at' => $krs->approved_at ? $krs->approved_at->format('d F Y, H:i') : null,
            ],
            'mahasiswa' => [
                'label' => 'Mahasiswa',
                'signed' => ! is_null($krs->submitted_at),
                'signed_at' => $krs->submitted_at ? $krs->submitted_at->format('d F Y, H:i') : null,
            ],
        ];
        $signatureInfo = isset($signatureMap[$signer]) ? $signatureMap[$signer] : null;

        return response()->json([
            'valid' => true,
            'document' => 'KARTU RENCANA STUDI (KRS)',
            'krs_id' => $krs->id,
            'status' => $krs->status,
            'student' => [
                'name' => $krs->student ? $krs->student->name : null,
                'nim' => $krs->student ? $krs->student->nim : null,
                'study_program' => $krs->student && $krs->student->studyProgram
                    ? $krs->student->studyProgram->name : null,
            ],
            'semester' => $krs->semester ? $krs->semester->name : null,
            'total_credits' => $krs->total_credits,
            'courses_count' => $krs->details->count(),
            'submitted_at' => $krs->submitted_at ? $krs->submitted_at->format('d F Y') : null,
            'approved_at' => $krs->approved_at ? $krs->approved_at->format('d F Y') : null,
            'signed_kaprodi' => $krs->signed_by_kaprodi_at
                ? $krs->signed_by_kaprodi_at->format('d F Y') : null,
            'is_fully_signed' => ! is_null($krs->signed_by_kaprodi_at),
            'signer_info' => $signatureInfo,
        ]);
    }

    /**
     * Verifikasi dokumen RPKPS/RPS via QR Code
     * URL: /api/verify/rpkps/{verification_code}?signer=dosen|kaprodi
     */
    public function verifyRpkps(Request $request, string $verificationCode): JsonResponse
    {
        $rpkps = Rpkps::with([
            'course.studyProgram',
            'lecturer',
            'academicYear',
            'semester',
            'approvals' => function ($q) {
                $q->orderByDesc('created_at')->limit(5);
            },
        ])->where('verification_code', $verificationCode)->first();

        if (! $rpkps) {
            return response()->json([
                'valid' => false,
                'message' => 'Dokumen RPS tidak ditemukan atau kode verifikasi tidak valid.',
            ], 404);
        }

        $signer = $request->query('signer', '');

        $signerMap = [
            'dosen' => [
                'label' => 'Dosen Pengampu Mata Kuliah',
                'name' => $rpkps->lecturer ? $rpkps->lecturer->name : null,
                'signed' => true,
            ],
            'kaprodi' => [
                'label' => 'Ketua Program Studi',
                'signed' => $rpkps->status === 'DIKUNCI',
                'name' => null,
            ],
        ];
        $signatureInfo = isset($signerMap[$signer]) ? $signerMap[$signer] : null;

        $approvedByName = null;
        if ($rpkps->approved_by) {
            $approver = User::find($rpkps->approved_by);
            $approvedByName = $approver ? $approver->name : null;
        }

        return response()->json([
            'valid' => true,
            'document' => 'RENCANA PEMBELAJARAN SEMESTER (RPS/RPKPS)',
            'code' => $rpkps->code,
            'status' => $rpkps->status,
            'course' => [
                'code' => $rpkps->course ? $rpkps->course->code : null,
                'name' => $rpkps->course ? $rpkps->course->name : null,
                'credits' => $rpkps->course ? $rpkps->course->credits : null,
                'study_program' => $rpkps->course && $rpkps->course->studyProgram
                    ? $rpkps->course->studyProgram->name : null,
            ],
            'lecturer' => $rpkps->lecturer ? $rpkps->lecturer->name : null,
            'academic_year' => $rpkps->academicYear ? $rpkps->academicYear->name : null,
            'semester' => $rpkps->semester ? $rpkps->semester->name : null,
            'version' => $rpkps->version,
            'approved_at' => $rpkps->approved_at ? $rpkps->approved_at->format('d F Y, H:i') : null,
            'approved_by' => $approvedByName,
            'is_valid' => in_array($rpkps->status, ['DISETUJUI', 'DIKUNCI']),
            'signer_info' => $signatureInfo,
        ]);
    }

    /**
     * Verifikasi Kalender Akademik via QR Code
     * URL: /api/verify/academic-calendar/{id}
     */
    public function verifyAcademicCalendar(int $id): JsonResponse
    {
        // id bisa berupa academic_year_id atau 'all'
        if ($id === 0) {
            return response()->json([
                'valid' => false,
                'message' => 'ID kalender akademik tidak valid.',
            ], 404);
        }

        $academicYear = AcademicYear::find($id);
        if (! $academicYear) {
            return response()->json([
                'valid' => false,
                'message' => 'Tahun akademik tidak ditemukan.',
            ], 404);
        }

        $eventsCount = AcademicCalendar::where('academic_year_id', $id)->count();

        // Ambil WK1 yang menandatangani
        $wk1Position = LecturerPosition::where('position_code', 'WK1')
            ->where('is_active', true)
            ->first();
        $wk1Name = $wk1Position?->lecturer?->full_name ?? null;

        return response()->json([
            'valid' => true,
            'document' => 'KALENDER AKADEMIK',
            'academic_year' => $academicYear->name,
            'events_count' => $eventsCount,
            'signed_by' => $wk1Name,
            'position' => 'Wakil Ketua I Bidang Akademik',
            'is_valid' => true,
        ]);
    }

    /**
     * Verifikasi Surat Keluar via QR Code
     * URL: /api/verify/surat/{token}
     */
    public function verifyOutgoingLetter(string $token): JsonResponse
    {
        $letter = OutgoingLetter::with(['letterType', 'signer.lecturer'])
            ->where('verification_token', $token)
            ->first();

        if (! $letter) {
            return response()->json([
                'valid' => false,
                'message' => 'Surat tidak ditemukan atau token verifikasi tidak valid.',
            ], 404);
        }

        if (! in_array($letter->status, ['DITANDATANGANI', 'TERKIRIM'])) {
            return response()->json([
                'valid' => false,
                'message' => 'Surat belum selesai ditandatangani.',
                'document' => 'SURAT KELUAR',
                'status' => $letter->status,
            ]);
        }

        $signerName = $letter->signer?->lecturer?->display_name ?? $letter->signer?->name ?? '-';

        // Jabatan penandatangan
        $signerPosition = '';
        if ($letter->signer?->lecturer) {
            $pos = LecturerPosition::where('lecturer_id', $letter->signer->lecturer->id)
                ->where('is_active', true)->first();
            $signerPosition = $pos?->position_name ?? '';
        }

        return response()->json([
            'valid' => true,
            'document' => 'SURAT KELUAR',
            'letter_number' => $letter->letter_number,
            'subject' => $letter->subject,
            'letter_type' => $letter->letterType?->name,
            'letter_date' => $letter->letter_date?->format('d F Y'),
            'recipient' => $letter->recipient,
            'signed_by' => $signerName,
            'signer_position' => $signerPosition,
            'signed_at' => $letter->signed_at?->format('d F Y, H:i'),
            'status' => $letter->status,
            'is_valid' => true,
        ]);
    }

    private function verifyAcademicDocument(Request $request, string $token, string $type): JsonResponse
    {
        $payload = AcademicDocumentVerification::decode($token);
        if (! $payload || ($payload['t'] ?? null) !== $type) {
            return response()->json(['valid' => false, 'message' => 'Token verifikasi dokumen tidak valid.'], 404);
        }

        $student = Student::with(['studyProgram.faculty', 'studyProgram.headLecturer'])->find($payload['s'] ?? null);
        $semesterId = $type === 'khs' ? (int) ($payload['m'] ?? 0) : null;
        $semester = $semesterId ? Semester::find($semesterId) : null;
        if (! $student || ($type === 'khs' && ! $semester)) {
            return response()->json(['valid' => false, 'message' => 'Data dokumen akademik tidak ditemukan.'], 404);
        }

        $grades = StudentGrade::with(['course:id,code,name,credits', 'semester:id,name,start_date'])
            ->where('student_id', $student->id)
            ->when($semesterId, fn ($query) => $query->where('semester_id', $semesterId))
            ->get();

        if (! AcademicDocumentVerification::matches($payload, $type, $student, $grades, $semesterId)) {
            return response()->json([
                'valid' => false,
                'message' => 'Data akademik telah berubah setelah dokumen diterbitkan. Silakan cetak dokumen terbaru.',
            ], 409);
        }

        $totalCredits = $grades->sum(fn ($grade) => (int) ($grade->course?->credits ?? 0));
        $totalPoints = $grades->sum(fn ($grade) => (int) ($grade->course?->credits ?? 0) * (float) ($grade->grade_point ?? 0));
        $calculatedGpa = $totalCredits > 0 ? round($totalPoints / $totalCredits, 2) : 0;
        $summary = $type === 'khs'
            ? DB::table('student_semester_summaries')->where(['student_id' => $student->id, 'semester_id' => $semesterId])->first()
            : DB::table('student_semester_summaries as summaries')
                ->join('semesters', 'semesters.id', '=', 'summaries.semester_id')
                ->where('summaries.student_id', $student->id)
                ->orderByDesc('semesters.start_date')
                ->select('summaries.*')->first();
        $signer = $student->studyProgram?->headLecturer;
        $signedAt = isset($payload['i']) ? Carbon::parse($payload['i'])->translatedFormat('d F Y, H:i') : null;

        return response()->json([
            'valid' => true,
            'document' => $type === 'khs' ? 'KARTU HASIL STUDI (KHS)' : 'TRANSKRIP NILAI AKADEMIK',
            'document_type' => $type,
            'student' => [
                'name' => $student->name,
                'nim' => $student->nim,
                'study_program' => $student->studyProgram?->name,
                'faculty' => $student->studyProgram?->faculty?->name,
            ],
            'semester' => $semester?->name,
            'courses_count' => $grades->count(),
            'total_credits' => $totalCredits,
            'gpa' => $type === 'khs' ? ($summary?->semester_gpa ?? $calculatedGpa) : ($summary?->cumulative_gpa ?? $calculatedGpa),
            'signed_by' => $signer?->display_name,
            'signer_position' => 'Ketua Program Studi',
            'issued_at' => $signedAt,
            'is_valid' => true,
            'signer_info' => $request->query('signer') === 'kaprodi' ? [
                'label' => 'Ketua Program Studi',
                'name' => $signer?->display_name,
                'signed' => $signer !== null && (int) ($payload['g'] ?? 0) === (int) $signer->id,
                'signed_at' => $signedAt,
            ] : null,
        ]);
    }
}
