<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Rps;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RpsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user  = Auth::user();
        $query = Rps::with(['course.studyProgram', 'academicYear', 'lecturer.user', 'approvedBy'])
            ->when($request->academic_year_id, fn($q) => $q->where('academic_year_id', $request->academic_year_id))
            ->when($request->study_program_id, fn($q) => $q->whereHas('course', fn($q2) => $q2->where('study_program_id', $request->study_program_id)))
            ->when($request->status, fn($q) => $q->where('status', $request->status));

        // Dosen hanya lihat RPS miliknya sendiri
        if ($user->hasRole('DOSEN') && $user->lecturer) {
            $query->where('lecturer_id', $user->lecturer->id);
        }

        return response()->json($query->paginate($request->per_page ?? 15));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'course_id'          => 'required|exists:courses,id',
            'academic_year_id'   => 'required|exists:academic_years,id',
            'lecturer_id'        => 'required|exists:lecturers,id',
            'course_description' => 'nullable|string',
            'learning_objectives'=> 'nullable|string',
            'references'         => 'nullable|string',
            'assessment_scheme'  => 'nullable|array',
        ]);

        // Generate kode RPS otomatis
        $validated['code']   = 'RPS-' . date('Y') . '-' . str_pad(Rps::count() + 1, 4, '0', STR_PAD_LEFT);
        $validated['status'] = 'Draft';

        $rps = Rps::create($validated);

        // Auto-generate 16 pertemuan kosong
        for ($i = 1; $i <= 16; $i++) {
            $rps->meetings()->create([
                'meeting_number' => $i,
                'topic'          => "Pertemuan $i",
                'duration'       => '2x50 menit',
                'weight'         => ($i <= 7 ? 5 : ($i == 8 ? 10 : ($i <= 15 ? 5 : 10))),
            ]);
        }

        return response()->json([
            'message' => 'RPS berhasil dibuat.',
            'data'    => $rps->load(['course', 'academicYear', 'lecturer', 'meetings']),
        ], 201);
    }

    public function show(Rps $rps): JsonResponse
    {
        return response()->json(
            $rps->load(['course.studyProgram', 'academicYear', 'lecturer.user', 'meetings', 'approvedBy'])
        );
    }

    public function update(Request $request, Rps $rps): JsonResponse
    {
        // Hanya bisa edit jika Draft atau Rejected
        if (!in_array($rps->status, ['Draft', 'Rejected'])) {
            return response()->json(['message' => 'RPS yang sudah disubmit tidak dapat diedit.'], 422);
        }

        $validated = $request->validate([
            'course_description'  => 'nullable|string',
            'learning_objectives' => 'nullable|string',
            'references'          => 'nullable|string',
            'assessment_scheme'   => 'nullable|array',
        ]);

        $rps->update($validated);

        return response()->json(['message' => 'RPS berhasil diupdate.', 'data' => $rps->fresh()]);
    }

    public function destroy(Rps $rps): JsonResponse
    {
        if ($rps->status === 'Approved') {
            return response()->json(['message' => 'RPS yang sudah disetujui tidak dapat dihapus.'], 422);
        }

        $rps->delete();
        return response()->json(['message' => 'RPS berhasil dihapus.']);
    }

    // Submit untuk review
    public function submit(Rps $rps): JsonResponse
    {
        if ($rps->status !== 'Draft' && $rps->status !== 'Rejected') {
            return response()->json(['message' => 'RPS sudah disubmit sebelumnya.'], 422);
        }

        $rps->update(['status' => 'Submitted']);
        return response()->json(['message' => 'RPS berhasil disubmit untuk review.']);
    }

    // Approve oleh Kaprodi
    public function approve(Rps $rps): JsonResponse
    {
        if ($rps->status !== 'Submitted') {
            return response()->json(['message' => 'Hanya RPS dengan status Submitted yang dapat disetujui.'], 422);
        }

        $rps->update([
            'status'      => 'Approved',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);

        return response()->json(['message' => 'RPS berhasil disetujui.']);
    }

    // Reject oleh Kaprodi
    public function reject(Request $request, Rps $rps): JsonResponse
    {
        $request->validate(['rejection_note' => 'required|string']);

        if ($rps->status !== 'Submitted') {
            return response()->json(['message' => 'Hanya RPS dengan status Submitted yang dapat ditolak.'], 422);
        }

        $rps->update([
            'status'         => 'Rejected',
            'rejection_note' => $request->rejection_note,
        ]);

        return response()->json(['message' => 'RPS ditolak.']);
    }

    // Update pertemuan
    public function updateMeeting(Request $request, Rps $rps, int $meetingId): JsonResponse
    {
        $meeting   = $rps->meetings()->findOrFail($meetingId);
        $validated = $request->validate([
            'topic'                  => 'sometimes|string|max:255',
            'sub_topics'             => 'nullable|string',
            'learning_activities'    => 'nullable|string',
            'learning_methods'       => 'nullable|string',
            'duration'               => 'nullable|string|max:50',
            'assessment_indicators'  => 'nullable|string',
            'weight'                 => 'nullable|integer|min:0|max:100',
        ]);

        $meeting->update($validated);

        return response()->json(['message' => 'Pertemuan berhasil diupdate.', 'data' => $meeting]);
    }
}
