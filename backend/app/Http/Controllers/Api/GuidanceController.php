<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AcademicNote;
use App\Models\AcademicWarning;
use App\Models\GuidanceNote;
use App\Models\GuidanceSession;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GuidanceController extends Controller
{
    // =============================================
    // SESI BIMBINGAN
    // =============================================

    public function sessions(Request $request): JsonResponse
    {
        $user = auth()->user();
        $query = GuidanceSession::with(['student.studyProgram', 'advisor', 'semester'])
            ->withCount('notes')
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->type, fn($q) => $q->where('type', $request->type))
            ->when($request->student_id, fn($q) => $q->where('student_id', $request->student_id))
            ->when($request->semester_id, fn($q) => $q->where('semester_id', $request->semester_id));

        // Mahasiswa: hanya lihat sesi sendiri
        if ($user->hasRole('MAHASISWA') && $user->student) {
            $query->where('student_id', $user->student->id);
        }
        // Dosen: hanya lihat sesi sebagai advisor
        elseif ($user->hasRole('DOSEN') && $user->lecturer) {
            $query->where('advisor_id', $user->lecturer->id);
        }

        return response()->json($query->orderByDesc('created_at')->paginate($request->per_page ?? 15));
    }

    public function showSession(GuidanceSession $session): JsonResponse
    {
        $user = auth()->user();
        $notes = $session->notes()->with('user')->get();

        // Mahasiswa tidak bisa lihat catatan private
        if ($user->hasRole('MAHASISWA')) {
            $notes = $notes->where('is_private', false)->values();
        }

        return response()->json([
            'session' => $session->load(['student.studyProgram', 'advisor', 'semester']),
            'notes'   => $notes,
        ]);
    }

    /** Mahasiswa mengajukan bimbingan */
    public function requestSession(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'topic'          => 'required|string|max:255',
            'description'    => 'nullable|string',
            'type'           => 'required|in:KONSULTASI,PERWALIAN,BIMBINGAN_TA,LAINNYA',
            'mode'           => 'nullable|in:TATAP_MUKA,ONLINE,CHAT',
            'scheduled_date' => 'nullable|date',
            'scheduled_time' => 'nullable|date_format:H:i',
        ]);

        $user = auth()->user();
        $student = $user->student;
        if (!$student) return response()->json(['message' => 'Bukan mahasiswa.'], 403);
        if (!$student->advisor_id) return response()->json(['message' => 'Belum memiliki dosen wali.'], 422);

        $session = GuidanceSession::create([
            'student_id'     => $student->id,
            'advisor_id'     => $student->advisor_id,
            'semester_id'    => $request->semester_id ?? null,
            'topic'          => $validated['topic'],
            'description'    => $validated['description'] ?? null,
            'type'           => $validated['type'],
            'mode'           => $validated['mode'] ?? 'TATAP_MUKA',
            'scheduled_date' => $validated['scheduled_date'] ?? null,
            'scheduled_time' => $validated['scheduled_time'] ?? null,
            'status'         => 'DIAJUKAN',
            'requested_by'   => $user->id,
        ]);

        return response()->json(['message' => 'Pengajuan bimbingan berhasil.', 'data' => $session->load(['student', 'advisor'])], 201);
    }

    /** Dosen membuat sesi bimbingan (inisiasi dari dosen) */
    public function createSession(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id'     => 'required|exists:students,id',
            'topic'          => 'required|string|max:255',
            'description'    => 'nullable|string',
            'type'           => 'required|in:KONSULTASI,PERWALIAN,PERINGATAN,BIMBINGAN_TA,LAINNYA',
            'mode'           => 'nullable|in:TATAP_MUKA,ONLINE,CHAT',
            'scheduled_date' => 'nullable|date',
            'scheduled_time' => 'nullable|date_format:H:i',
            'location'       => 'nullable|string|max:255',
            'semester_id'    => 'nullable|exists:semesters,id',
        ]);

        $user = auth()->user();
        $advisorId = $user->lecturer?->id;
        if (!$advisorId) return response()->json(['message' => 'Bukan dosen.'], 403);

        $session = GuidanceSession::create([
            'student_id'     => $validated['student_id'],
            'advisor_id'     => $advisorId,
            'semester_id'    => $validated['semester_id'] ?? null,
            'topic'          => $validated['topic'],
            'description'    => $validated['description'] ?? null,
            'type'           => $validated['type'],
            'mode'           => $validated['mode'] ?? 'TATAP_MUKA',
            'scheduled_date' => $validated['scheduled_date'] ?? null,
            'scheduled_time' => $validated['scheduled_time'] ?? null,
            'location'       => $validated['location'] ?? null,
            'status'         => 'DIJADWALKAN',
            'requested_by'   => $user->id,
        ]);

        return response()->json(['message' => 'Sesi bimbingan berhasil dibuat.', 'data' => $session->load(['student', 'advisor'])], 201);
    }

    /** Update status sesi */
    public function updateSessionStatus(Request $request, GuidanceSession $session): JsonResponse
    {
        $validated = $request->validate([
            'status'         => 'required|in:DIJADWALKAN,BERLANGSUNG,SELESAI,DIBATALKAN',
            'scheduled_date' => 'nullable|date',
            'scheduled_time' => 'nullable|date_format:H:i',
            'location'       => 'nullable|string|max:255',
        ]);

        $session->update($validated);
        return response()->json(['message' => 'Status sesi berhasil diupdate.', 'data' => $session->fresh()]);
    }

    /** Tambah catatan ke sesi */
    public function addNote(Request $request, GuidanceSession $session): JsonResponse
    {
        $validated = $request->validate([
            'content'    => 'required|string',
            'is_private' => 'boolean',
        ]);

        $note = $session->notes()->create([
            'user_id'    => auth()->id(),
            'content'    => $validated['content'],
            'is_private' => $validated['is_private'] ?? false,
        ]);

        return response()->json(['message' => 'Catatan berhasil ditambahkan.', 'data' => $note->load('user')], 201);
    }

    // =============================================
    // CATATAN AKADEMIK
    // =============================================

    public function academicNotes(Request $request): JsonResponse
    {
        $query = AcademicNote::with(['student.studyProgram', 'advisor', 'semester'])
            ->when($request->student_id, fn($q) => $q->where('student_id', $request->student_id))
            ->when($request->type, fn($q) => $q->where('type', $request->type));

        $user = auth()->user();
        if ($user->hasRole('MAHASISWA') && $user->student) {
            $query->where('student_id', $user->student->id);
        } elseif ($user->hasRole('DOSEN') && $user->lecturer) {
            $query->where('advisor_id', $user->lecturer->id);
        }

        return response()->json($query->orderByDesc('created_at')->paginate($request->per_page ?? 15));
    }

    public function storeAcademicNote(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id'   => 'required|exists:students,id',
            'semester_id'  => 'nullable|exists:semesters,id',
            'type'         => 'required|in:UMUM,PERINGATAN,REKOMENDASI,PRESTASI,PELANGGARAN',
            'content'      => 'required|string',
            'is_important' => 'boolean',
        ]);

        $user = auth()->user();
        $advisorId = $user->lecturer?->id;
        if (!$advisorId) return response()->json(['message' => 'Bukan dosen.'], 403);

        $note = AcademicNote::create(array_merge($validated, ['advisor_id' => $advisorId]));
        return response()->json(['message' => 'Catatan akademik berhasil disimpan.', 'data' => $note->load(['student', 'advisor'])], 201);
    }

    public function destroyAcademicNote(AcademicNote $note): JsonResponse
    {
        $note->delete();
        return response()->json(['message' => 'Catatan berhasil dihapus.']);
    }

    // =============================================
    // PERINGATAN AKADEMIK
    // =============================================

    public function warnings(Request $request): JsonResponse
    {
        $query = AcademicWarning::with(['student.studyProgram', 'advisor', 'semester'])
            ->when($request->student_id, fn($q) => $q->where('student_id', $request->student_id))
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->level, fn($q) => $q->where('level', $request->level));

        $user = auth()->user();
        if ($user->hasRole('MAHASISWA') && $user->student) {
            $query->where('student_id', $user->student->id);
        } elseif ($user->hasRole('DOSEN') && $user->lecturer) {
            $query->where('advisor_id', $user->lecturer->id);
        }

        return response()->json($query->orderByDesc('created_at')->paginate($request->per_page ?? 15));
    }

    public function storeWarning(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id'            => 'required|exists:students,id',
            'semester_id'           => 'nullable|exists:semesters,id',
            'level'                 => 'required|in:RINGAN,SEDANG,BERAT',
            'reason'                => 'required|string|max:255',
            'description'           => 'nullable|string',
            'ipk'                   => 'nullable|numeric|min:0|max:4',
            'ips'                   => 'nullable|numeric|min:0|max:4',
            'requires_consultation' => 'boolean',
            'consultation_deadline' => 'nullable|date',
        ]);

        $user = auth()->user();
        $advisorId = $user->lecturer?->id;

        $warning = AcademicWarning::create(array_merge($validated, [
            'advisor_id' => $advisorId,
            'status'     => 'AKTIF',
        ]));

        return response()->json(['message' => 'Peringatan akademik berhasil dibuat.', 'data' => $warning->load(['student', 'advisor'])], 201);
    }

    public function resolveWarning(Request $request, AcademicWarning $warning): JsonResponse
    {
        $request->validate(['resolution_note' => 'required|string']);

        $warning->update([
            'status'            => 'SELESAI',
            'consultation_done' => true,
            'resolution_note'   => $request->resolution_note,
        ]);

        return response()->json(['message' => 'Peringatan berhasil diselesaikan.', 'data' => $warning->fresh()]);
    }

    // =============================================
    // DASHBOARD DOSEN WALI
    // =============================================

    public function advisorDashboard(Request $request): JsonResponse
    {
        $user = auth()->user();
        $lecturerId = $user->lecturer?->id;
        if (!$lecturerId) return response()->json(['message' => 'Bukan dosen.'], 403);

        // Mahasiswa bimbingan
        $students = Student::where('advisor_id', $lecturerId)
            ->with(['studyProgram'])
            ->withCount(['statusHistories'])
            ->get();

        $activeWarnings = AcademicWarning::where('advisor_id', $lecturerId)
            ->whereIn('status', ['AKTIF', 'PROSES'])->count();

        $pendingSessions = GuidanceSession::where('advisor_id', $lecturerId)
            ->where('status', 'DIAJUKAN')->count();

        $totalSessions = GuidanceSession::where('advisor_id', $lecturerId)
            ->where('status', 'SELESAI')->count();

        return response()->json([
            'students'         => $students,
            'total_students'   => $students->count(),
            'active_warnings'  => $activeWarnings,
            'pending_sessions' => $pendingSessions,
            'total_sessions'   => $totalSessions,
        ]);
    }

    /** List mahasiswa bimbingan dosen wali */
    public function myStudents(Request $request): JsonResponse
    {
        $user = auth()->user();
        $lecturerId = $user->lecturer?->id;
        if (!$lecturerId) return response()->json(['message' => 'Bukan dosen.'], 403);

        $students = Student::where('advisor_id', $lecturerId)
            ->with(['studyProgram', 'latestStatusHistory'])
            ->when($request->search, fn($q) => $q->where('name', 'like', "%{$request->search}%")->orWhere('nim', 'like', "%{$request->search}%"))
            ->orderBy('name')
            ->get();

        return response()->json($students);
    }
}
