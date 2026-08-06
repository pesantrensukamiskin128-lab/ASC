<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClassModel;
use App\Models\ClassMember;
use App\Models\Course;
use App\Models\Krs;
use App\Models\KrsDetail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class KrsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user  = Auth::user();
        $query = Krs::with(['student.studyProgram', 'semester.academicYear', 'advisor'])
            ->when($request->semester_id, fn($q) => $q->where('semester_id', $request->semester_id))
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->search, fn($q) => $q->whereHas('student', fn($q2) =>
                $q2->where('name', 'like', "%{$request->search}%")
                   ->orWhere('nim', 'like', "%{$request->search}%")));

        // Mahasiswa hanya lihat KRS sendiri
        if ($user->hasRole('MAHASISWA') && $user->student) {
            $query->where('student_id', $user->student->id);
        }

        return response()->json($query->orderByDesc('created_at')->paginate($request->per_page ?? 20));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id'  => 'nullable|exists:students,id',
            'semester_id' => 'required|exists:semesters,id',
        ]);

        // Auto-detect student_id jika mahasiswa
        $user = auth()->user();
        $studentId = $validated['student_id'] ?? $user->student?->id;

        if (!$studentId) {
            return response()->json(['message' => 'Data mahasiswa tidak ditemukan.'], 404);
        }

        $existing = Krs::where('student_id', $studentId)
            ->where('semester_id', $validated['semester_id'])
            ->first();

        if ($existing) {
            return response()->json(['message' => 'KRS untuk semester ini sudah ada.', 'data' => $existing], 200);
        }

        $student = \App\Models\Student::find($studentId);

        $krs = Krs::create([
            'student_id'  => $studentId,
            'semester_id' => $validated['semester_id'],
            'advisor_id'  => $student->advisor_id,
            'status'      => 'DRAFT',
        ]);

        return response()->json(['message' => 'KRS berhasil dibuat.', 'data' => $krs->load(['student', 'semester'])], 201);
    }

    public function show(Krs $krs): JsonResponse
    {
        return response()->json(
            $krs->load([
                'student.studyProgram',
                'semester.academicYear',
                'advisor',
                'details.course',
                'details.class_.lecturer',
                'details.class_.room',
                'details.class_.schedules.room',
            ])
        );
    }

    public function addCourse(Request $request, Krs $krs): JsonResponse
    {
        $request->validate([
            'course_id' => 'required|exists:courses,id',
            'class_id'  => 'nullable|exists:classes,id',
        ]);

        if (!in_array($krs->status, ['DRAFT', 'REJECTED'])) {
            return response()->json(['message' => 'KRS sudah disubmit, tidak dapat diubah.'], 422);
        }

        $course = Course::findOrFail($request->course_id);

        // Cek sudah terdaftar
        if ($krs->details()->where('course_id', $course->id)->where('krs_details.status', 'AKTIF')->exists()) {
            return response()->json(['message' => 'Mata kuliah ini sudah ada dalam KRS.'], 422);
        }

        // Cek batas SKS (default maks 24)
        $currentCredits = $krs->details()->where('krs_details.status', 'AKTIF')
            ->join('courses', 'krs_details.course_id', '=', 'courses.id')
            ->sum('courses.credits');

        if ($currentCredits + $course->credits > 24) {
            return response()->json(['message' => "Batas SKS terlampaui. Sisa: " . (24 - $currentCredits) . " SKS."], 422);
        }

        // Cek kuota kelas jika dipilih
        if ($request->class_id) {
            $class = ClassModel::findOrFail($request->class_id);
            if (!$class->isAvailable()) {
                return response()->json(['message' => 'Kuota kelas sudah penuh.'], 422);
            }
        }

        DB::transaction(function () use ($krs, $course, $request) {
            KrsDetail::create([
                'krs_id'    => $krs->id,
                'course_id' => $course->id,
                'class_id'  => $request->class_id,
            ]);
            $krs->recalculateCredits();

            // Sync ke class_members jika ada class_id
            if ($request->class_id) {
                \App\Models\ClassMember::firstOrCreate([
                    'class_id'   => $request->class_id,
                    'student_id' => $krs->student_id,
                ]);
            }
        });

        return response()->json(['message' => 'Mata kuliah berhasil ditambahkan ke KRS.']);
    }

    public function removeCourse(Krs $krs, KrsDetail $detail): JsonResponse
    {
        if (!in_array($krs->status, ['DRAFT', 'REJECTED'])) {
            return response()->json(['message' => 'KRS sudah disubmit, tidak dapat diubah.'], 422);
        }

        DB::transaction(function () use ($krs, $detail) {
            // Hapus dari class_members jika ada class_id
            if ($detail->class_id) {
                \App\Models\ClassMember::where('class_id', $detail->class_id)
                    ->where('student_id', $krs->student_id)
                    ->delete();
            }
            $detail->delete();
            $krs->recalculateCredits();
        });

        return response()->json(['message' => 'Mata kuliah berhasil dihapus dari KRS.']);
    }

    public function submit(Krs $krs): JsonResponse
    {
        if (!in_array($krs->status, ['DRAFT', 'REJECTED'])) {
            return response()->json(['message' => 'KRS sudah disubmit.'], 422);
        }
        if ($krs->total_credits === 0) {
            return response()->json(['message' => 'KRS kosong, belum ada mata kuliah.'], 422);
        }

        // Cek status keuangan — tagihan semester harus lunas
        $unpaid = \App\Models\Invoice::where('student_id', $krs->student_id)
            ->where('semester_id', $krs->semester_id)
            ->whereIn('status', ['UNPAID', 'PARTIAL', 'OVERDUE'])
            ->first();

        if ($unpaid) {
            return response()->json([
                'message' => 'Tidak dapat mengajukan KRS. Tagihan semester ini belum lunas.',
                'invoice_number' => $unpaid->invoice_number,
            ], 422);
        }

        $krs->update(['status' => 'SUBMITTED', 'submitted_at' => now()]);

        // Notifikasi ke dosen wali
        $krs->loadMissing(['student', 'advisor']);
        if ($krs->advisor?->user_id) {
            \App\Models\AppNotification::send(
                $krs->advisor->user_id,
                'Pengajuan KRS Baru',
                "Mahasiswa {$krs->student?->name} ({$krs->student?->nim}) mengajukan KRS ({$krs->total_credits} SKS). Mohon direview.",
                'warning',
                '/akademik/krs/' . $krs->id
            );
        }

        return response()->json(['message' => 'KRS berhasil disubmit ke dosen wali.']);
    }

    public function approve(Request $request, Krs $krs): JsonResponse
    {
        if ($krs->status !== 'SUBMITTED') {
            return response()->json(['message' => 'Hanya KRS berstatus SUBMITTED yang bisa disetujui.'], 422);
        }

        $krs->update([
            'status'       => 'APPROVED',
            'advisor_note' => $request->note ?? null,
            'approved_at'  => now(),
        ]);

        // Pastikan semua krs_details yang punya class_id sudah masuk class_members
        $krs->details()->whereNotNull('class_id')->where('status', 'AKTIF')->each(function ($detail) use ($krs) {
            ClassMember::firstOrCreate([
                'class_id'   => $detail->class_id,
                'student_id' => $krs->student_id,
            ]);
        });

        // Notifikasi ke mahasiswa
        $krs->loadMissing('student');
        if ($krs->student?->user_id) {
            \App\Models\AppNotification::send(
                $krs->student->user_id,
                'KRS Disetujui ✓',
                "KRS Anda ({$krs->total_credits} SKS) telah disetujui oleh dosen wali.",
                'success',
                '/akademik/krs-saya'
            );
        }

        return response()->json(['message' => 'KRS berhasil disetujui.']);
    }

    public function reject(Request $request, Krs $krs): JsonResponse
    {
        $request->validate(['note' => 'required|string']);

        if ($krs->status !== 'SUBMITTED') {
            return response()->json(['message' => 'Hanya KRS berstatus SUBMITTED yang bisa ditolak.'], 422);
        }

        $krs->update(['status' => 'REJECTED', 'advisor_note' => $request->note]);

        // Notifikasi ke mahasiswa
        $krs->loadMissing('student');
        if ($krs->student?->user_id) {
            \App\Models\AppNotification::send(
                $krs->student->user_id,
                'KRS Ditolak',
                "KRS Anda ditolak oleh dosen wali. Catatan: \"{$request->note}\". Silakan perbaiki dan submit ulang.",
                'error',
                '/akademik/krs-saya'
            );
        }

        return response()->json(['message' => 'KRS ditolak.']);
    }

    /** Kaprodi tandatangan KRS */
    public function signByKaprodi(Krs $krs): JsonResponse
    {
        if ($krs->status !== 'APPROVED') {
            return response()->json(['message' => 'KRS harus sudah disetujui dosen wali sebelum ditandatangani Kaprodi.'], 422);
        }

        if ($krs->signed_by_kaprodi_at) {
            return response()->json(['message' => 'KRS sudah ditandatangani Kaprodi.'], 422);
        }

        $krs->update([
            'signed_by_kaprodi_at' => now(),
            'signed_by_kaprodi_id' => auth()->id(),
        ]);

        // Notifikasi ke mahasiswa
        $krs->loadMissing('student');
        if ($krs->student?->user_id) {
            \App\Models\AppNotification::send(
                $krs->student->user_id,
                'KRS Ditandatangani Kaprodi ✓',
                "KRS Anda telah ditandatangani oleh Ketua Program Studi. Dokumen KRS sudah final.",
                'success',
                '/akademik/krs-saya'
            );
        }

        return response()->json(['message' => 'KRS berhasil ditandatangani.']);
    }

    /** Download PDF KRS */
    public function downloadPdf(Krs $krs)
    {
        $krs->load([
            'student.studyProgram.faculty',
            'semester.academicYear',
            'advisor',
            'details.course',
            'details.class_.lecturer',
            'details.class_.room',
            'details.class_.schedules.room',
        ]);

        $institution = \App\Models\Institution::first();
        $logoPath = $institution?->logo_path ? storage_path('app/public/' . $institution->logo_path) : null;

        // Kaprodi
        $prodiId = $krs->student?->study_program_id;
        $kaprodi = null;
        if ($prodiId) {
            $pos = \App\Models\LecturerPosition::where('position_code', 'KAPRODI')
                ->where('scope_type', 'study_program')->where('scope_id', $prodiId)
                ->where('is_active', true)->first();
            if ($pos) $kaprodi = \App\Models\Lecturer::find($pos->lecturer_id);
        }

        // QR codes — link ke halaman verifikasi frontend (ukuran kecil untuk tanda tangan)
        $verifyUrl = rtrim(config('app.frontend_url'), '/') . "/verify/krs/{$krs->id}";
        $qrKaprodi = "https://api.qrserver.com/v1/create-qr-code/?size=60x60&data=" . urlencode($verifyUrl . '?signer=kaprodi');
        $qrAdvisor = "https://api.qrserver.com/v1/create-qr-code/?size=60x60&data=" . urlencode($verifyUrl . '?signer=dosen_wali');
        $qrStudent = "https://api.qrserver.com/v1/create-qr-code/?size=60x60&data=" . urlencode($verifyUrl . '?signer=mahasiswa');

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.krs', [
            'krs' => $krs,
            'institution' => $institution,
            'logoPath' => $logoPath,
            'kaprodi' => $kaprodi,
            'qrKaprodi' => $qrKaprodi,
            'qrAdvisor' => $qrAdvisor,
            'qrStudent' => $qrStudent,
            'verifyUrl' => $verifyUrl,
        ])->setPaper('a4', 'portrait')->setOption('isRemoteEnabled', true);

        return $pdf->download("KRS-{$krs->student?->nim}-" . str_replace(['/', '\\'], '-', $krs->semester?->name ?? '') . ".pdf");
    }

    public function destroy(Krs $krs): JsonResponse
    {
        $user = auth()->user();

        // SUPER_ADMIN bisa hapus semua KRS
        if (!$user->hasRole('SUPER_ADMIN') && $krs->status === 'APPROVED') {
            return response()->json(['message' => 'KRS yang sudah disetujui hanya bisa dihapus oleh Super Admin.'], 422);
        }

        $krs->details()->delete();
        $krs->delete();
        return response()->json(['message' => 'KRS berhasil dihapus.']);
    }
}
