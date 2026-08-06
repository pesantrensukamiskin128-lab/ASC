<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AcademicCalendar;
use App\Models\AcademicYear;
use App\Models\Krs;
use App\Models\LecturerPosition;
use App\Models\Rpkps;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VerifyController extends Controller
{
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

        if (!$krs) {
            return response()->json([
                'valid'   => false,
                'message' => 'Dokumen KRS tidak ditemukan.',
            ], 404);
        }

        $signer = $request->query('signer', '');

        $signatureMap = [
            'kaprodi' => [
                'label'     => 'Ketua Program Studi',
                'signed'    => !is_null($krs->signed_by_kaprodi_at),
                'signed_at' => $krs->signed_by_kaprodi_at ? $krs->signed_by_kaprodi_at->format('d F Y, H:i') : null,
            ],
            'dosen_wali' => [
                'label'     => 'Dosen Wali / Pembimbing Akademik',
                'signed'    => !is_null($krs->approved_at),
                'signed_at' => $krs->approved_at ? $krs->approved_at->format('d F Y, H:i') : null,
            ],
            'mahasiswa' => [
                'label'     => 'Mahasiswa',
                'signed'    => !is_null($krs->submitted_at),
                'signed_at' => $krs->submitted_at ? $krs->submitted_at->format('d F Y, H:i') : null,
            ],
        ];
        $signatureInfo = isset($signatureMap[$signer]) ? $signatureMap[$signer] : null;

        return response()->json([
            'valid'           => true,
            'document'        => 'KARTU RENCANA STUDI (KRS)',
            'krs_id'          => $krs->id,
            'status'          => $krs->status,
            'student'         => [
                'name'          => $krs->student ? $krs->student->name : null,
                'nim'           => $krs->student ? $krs->student->nim : null,
                'study_program' => $krs->student && $krs->student->studyProgram
                    ? $krs->student->studyProgram->name : null,
            ],
            'semester'        => $krs->semester ? $krs->semester->name : null,
            'total_credits'   => $krs->total_credits,
            'courses_count'   => $krs->details->count(),
            'submitted_at'    => $krs->submitted_at ? $krs->submitted_at->format('d F Y') : null,
            'approved_at'     => $krs->approved_at ? $krs->approved_at->format('d F Y') : null,
            'signed_kaprodi'  => $krs->signed_by_kaprodi_at
                ? $krs->signed_by_kaprodi_at->format('d F Y') : null,
            'is_fully_signed' => !is_null($krs->signed_by_kaprodi_at),
            'signer_info'     => $signatureInfo,
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
            'approvals' => function ($q) { $q->orderByDesc('created_at')->limit(5); },
        ])->where('verification_code', $verificationCode)->first();

        if (!$rpkps) {
            return response()->json([
                'valid'   => false,
                'message' => 'Dokumen RPS tidak ditemukan atau kode verifikasi tidak valid.',
            ], 404);
        }

        $signer = $request->query('signer', '');

        $signerMap = [
            'dosen' => [
                'label'  => 'Dosen Pengampu Mata Kuliah',
                'name'   => $rpkps->lecturer ? $rpkps->lecturer->name : null,
                'signed' => true,
            ],
            'kaprodi' => [
                'label'  => 'Ketua Program Studi',
                'signed' => $rpkps->status === 'DIKUNCI',
                'name'   => null,
            ],
        ];
        $signatureInfo = isset($signerMap[$signer]) ? $signerMap[$signer] : null;

        $approvedByName = null;
        if ($rpkps->approved_by) {
            $approver = User::find($rpkps->approved_by);
            $approvedByName = $approver ? $approver->name : null;
        }

        return response()->json([
            'valid'       => true,
            'document'    => 'RENCANA PEMBELAJARAN SEMESTER (RPS/RPKPS)',
            'code'        => $rpkps->code,
            'status'      => $rpkps->status,
            'course'      => [
                'code'          => $rpkps->course ? $rpkps->course->code : null,
                'name'          => $rpkps->course ? $rpkps->course->name : null,
                'credits'       => $rpkps->course ? $rpkps->course->credits : null,
                'study_program' => $rpkps->course && $rpkps->course->studyProgram
                    ? $rpkps->course->studyProgram->name : null,
            ],
            'lecturer'      => $rpkps->lecturer ? $rpkps->lecturer->name : null,
            'academic_year' => $rpkps->academicYear ? $rpkps->academicYear->name : null,
            'semester'      => $rpkps->semester ? $rpkps->semester->name : null,
            'version'       => $rpkps->version,
            'approved_at'   => $rpkps->approved_at ? $rpkps->approved_at->format('d F Y, H:i') : null,
            'approved_by'   => $approvedByName,
            'is_valid'      => in_array($rpkps->status, ['DISETUJUI', 'DIKUNCI']),
            'signer_info'   => $signatureInfo,
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
                'valid'   => false,
                'message' => 'ID kalender akademik tidak valid.',
            ], 404);
        }

        $academicYear = AcademicYear::find($id);
        if (!$academicYear) {
            return response()->json([
                'valid'   => false,
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
            'valid'         => true,
            'document'      => 'KALENDER AKADEMIK',
            'academic_year' => $academicYear->name,
            'events_count'  => $eventsCount,
            'signed_by'     => $wk1Name,
            'position'      => 'Wakil Ketua I Bidang Akademik',
            'is_valid'      => true,
        ]);
    }
}
