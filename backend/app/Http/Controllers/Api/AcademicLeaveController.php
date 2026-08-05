<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AcademicLeave;
use App\Models\AcademicLeaveApproval;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AcademicLeaveController extends Controller
{
    private const MAX_LEAVE_SEMESTERS = 4; // Maksimal 4 semester cuti

    /** List cuti (admin lihat semua, mahasiswa lihat sendiri) */
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();
        $query = AcademicLeave::with(['student.studyProgram', 'semester', 'approvals.approver'])
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->student_id, fn($q) => $q->where('student_id', $request->student_id))
            ->when($request->search, fn($q) => $q->whereHas('student', fn($q2) =>
                $q2->where('name', 'like', "%{$request->search}%")
                   ->orWhere('nim', 'like', "%{$request->search}%")));

        if ($user->hasRole('MAHASISWA') && $user->student) {
            $query->where('student_id', $user->student->id);
        }

        return response()->json($query->orderByDesc('created_at')->paginate($request->per_page ?? 15));
    }

    /** Detail cuti */
    public function show(AcademicLeave $academicLeave): JsonResponse
    {
        return response()->json($academicLeave->load(['student.studyProgram', 'semester', 'approvals.approver']));
    }

    /** Mahasiswa mengajukan cuti */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'semester_id'          => 'required|exists:semesters,id',
            'type'                 => 'nullable|in:CUTI,PERPANJANGAN',
            'reason'               => 'required|string',
            'leave_semester_count' => 'nullable|integer|min:1|max:2',
        ]);

        $user = auth()->user();
        $student = $user->student;
        if (!$student) return response()->json(['message' => 'Bukan mahasiswa.'], 403);

        // Validasi maksimal semester cuti
        $totalLeave = AcademicLeave::totalLeaveSemesters($student->id);
        $requested = $validated['leave_semester_count'] ?? 1;
        if ($totalLeave + $requested > self::MAX_LEAVE_SEMESTERS) {
            return response()->json([
                'message' => "Melebihi batas cuti. Maksimal " . self::MAX_LEAVE_SEMESTERS . " semester. Anda sudah cuti {$totalLeave} semester.",
            ], 422);
        }

        // Cek tidak ada cuti aktif
        $activeCuti = AcademicLeave::where('student_id', $student->id)
            ->whereIn('status', ['DIAJUKAN', 'DOSEN_WALI_APPROVED', 'KAPRODI_APPROVED', 'APPROVED', 'AKTIF'])
            ->exists();
        if ($activeCuti) {
            return response()->json(['message' => 'Sudah ada pengajuan cuti yang belum selesai.'], 422);
        }

        $leave = DB::transaction(function () use ($validated, $student) {
            $leave = AcademicLeave::create([
                'student_id'          => $student->id,
                'semester_id'         => $validated['semester_id'],
                'type'                => $validated['type'] ?? 'CUTI',
                'reason'              => $validated['reason'],
                'leave_semester_count'=> $validated['leave_semester_count'] ?? 1,
                'status'              => 'DIAJUKAN',
                'submitted_at'        => now(),
            ]);

            // Buat approval chain: Dosen Wali → Kaprodi → Admin Akademik
            $approvalChain = [
                ['role' => 'DOSEN_WALI', 'order' => 1],
                ['role' => 'KAPRODI', 'order' => 2],
                ['role' => 'ADMIN_AKADEMIK', 'order' => 3],
            ];

            foreach ($approvalChain as $chain) {
                AcademicLeaveApproval::create([
                    'academic_leave_id' => $leave->id,
                    'approver_id'       => auth()->id(), // Placeholder, akan diisi saat approve
                    'role'              => $chain['role'],
                    'order'             => $chain['order'],
                    'status'            => 'PENDING',
                ]);
            }

            return $leave;
        });

        return response()->json(['message' => 'Pengajuan cuti berhasil disubmit.', 'data' => $leave->load('approvals')], 201);
    }

    /** Upload dokumen pendukung */
    public function uploadDocument(Request $request, AcademicLeave $academicLeave): JsonResponse
    {
        $request->validate(['document' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120']);

        if ($academicLeave->document_path) {
            Storage::disk('public')->delete($academicLeave->document_path);
        }

        $path = $request->file('document')->store('academic-leaves', 'public');
        $academicLeave->update(['document_path' => $path]);

        return response()->json(['message' => 'Dokumen berhasil diupload.', 'path' => $path]);
    }

    /** Approval berjenjang */
    public function approve(Request $request, AcademicLeave $academicLeave): JsonResponse
    {
        $validated = $request->validate([
            'role'   => 'required|in:DOSEN_WALI,KAPRODI,ADMIN_AKADEMIK',
            'action' => 'required|in:approve,reject',
            'notes'  => 'nullable|string',
        ]);

        $approval = $academicLeave->approvals()
            ->where('role', $validated['role'])
            ->where('status', 'PENDING')
            ->first();

        if (!$approval) {
            return response()->json(['message' => 'Tidak ada approval pending untuk role ini.'], 422);
        }

        // Cek urutan: approval sebelumnya harus sudah approved
        $prevApprovals = $academicLeave->approvals()->where('order', '<', $approval->order)->get();
        foreach ($prevApprovals as $prev) {
            if ($prev->status !== 'APPROVED') {
                return response()->json(['message' => 'Approval sebelumnya belum selesai.'], 422);
            }
        }

        if ($validated['action'] === 'approve') {
            $approval->update([
                'status'      => 'APPROVED',
                'approver_id' => auth()->id(),
                'notes'       => $validated['notes'] ?? null,
                'approved_at' => now(),
            ]);

            // Update status cuti berdasarkan role
            $statusMap = [
                'DOSEN_WALI'     => 'DOSEN_WALI_APPROVED',
                'KAPRODI'        => 'KAPRODI_APPROVED',
                'ADMIN_AKADEMIK' => 'APPROVED',
            ];
            $academicLeave->update(['status' => $statusMap[$validated['role']]]);

            // Jika semua approved (ADMIN_AKADEMIK), aktifkan cuti
            if ($validated['role'] === 'ADMIN_AKADEMIK') {
                $academicLeave->update([
                    'status'       => 'AKTIF',
                    'activated_at' => now(),
                    'start_date'   => now()->toDateString(),
                ]);

                // Update status mahasiswa ke CUTI
                $academicLeave->student->recordStatus('Cuti', $academicLeave->semester_id, "Cuti akademik: {$academicLeave->reason}");
            }

            return response()->json(['message' => "Disetujui oleh {$validated['role']}.", 'data' => $academicLeave->fresh('approvals')]);
        } else {
            $approval->update([
                'status'      => 'REJECTED',
                'approver_id' => auth()->id(),
                'notes'       => $validated['notes'] ?? 'Ditolak.',
                'approved_at' => now(),
            ]);

            // Jika ditolak, status cuti langsung rejected
            $rejectedStatus = $validated['role'] . '_REJECTED';
            $academicLeave->update(['status' => $rejectedStatus]);

            return response()->json(['message' => "Ditolak oleh {$validated['role']}.", 'data' => $academicLeave->fresh('approvals')]);
        }
    }

    /** Aktivasi kembali (selesai cuti) */
    public function activate(AcademicLeave $academicLeave): JsonResponse
    {
        if ($academicLeave->status !== 'AKTIF') {
            return response()->json(['message' => 'Cuti belum aktif.'], 422);
        }

        $academicLeave->update([
            'status'       => 'SELESAI',
            'completed_at' => now(),
            'end_date'     => now()->toDateString(),
        ]);

        // Kembalikan status mahasiswa ke Aktif
        $academicLeave->student->recordStatus('Aktif', null, 'Kembali dari cuti akademik');

        return response()->json(['message' => 'Mahasiswa berhasil diaktifkan kembali.', 'data' => $academicLeave->fresh()]);
    }

    /** Batalkan pengajuan (oleh mahasiswa, hanya status DIAJUKAN/DRAFT) */
    public function cancel(AcademicLeave $academicLeave): JsonResponse
    {
        if (!in_array($academicLeave->status, ['DRAFT', 'DIAJUKAN'])) {
            return response()->json(['message' => 'Hanya pengajuan yang belum diproses yang bisa dibatalkan.'], 422);
        }

        $academicLeave->update(['status' => 'DIBATALKAN']);
        return response()->json(['message' => 'Pengajuan cuti berhasil dibatalkan.']);
    }

    /** Riwayat cuti mahasiswa */
    public function history(Request $request): JsonResponse
    {
        $studentId = $request->student_id;
        if (!$studentId && auth()->user()->student) {
            $studentId = auth()->user()->student->id;
        }

        $leaves = AcademicLeave::where('student_id', $studentId)
            ->with(['semester', 'approvals.approver'])
            ->orderByDesc('created_at')
            ->get();

        $totalSemesters = AcademicLeave::totalLeaveSemesters($studentId);

        return response()->json([
            'leaves'          => $leaves,
            'total_semesters' => $totalSemesters,
            'max_semesters'   => self::MAX_LEAVE_SEMESTERS,
            'remaining'       => self::MAX_LEAVE_SEMESTERS - $totalSemesters,
        ]);
    }
}
