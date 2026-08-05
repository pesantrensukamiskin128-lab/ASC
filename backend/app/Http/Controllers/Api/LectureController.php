<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\ClassAnnouncement;
use App\Models\ClassModel;
use App\Models\LectureJournal;
use App\Models\LectureMaterial;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LectureController extends Controller
{
    // =============================================
    // JURNAL PERKULIAHAN
    // =============================================

    /**
     * GET /lectures/{class} — detail kelas (dipakai ClassDetailView)
     */
    public function show(ClassModel $class): JsonResponse
    {
        $class->load(['course', 'semester', 'lecturer.user', 'room'])
              ->loadCount('members');
        return response()->json($class);
    }

    public function memberList(ClassModel $class): JsonResponse
    {
        // Ambil dari class_members (sudah sync dari KRS)
        $members = $class->members()->with('student:id,nim,name')->orderBy('id')->get();

        // Fallback: ambil dari KRS jika class_members kosong
        if ($members->isEmpty()) {
            $members = \App\Models\KrsDetail::where('class_id', $class->id)
                ->where('status', 'AKTIF')
                ->with(['krs.student:id,nim,name'])
                ->get()
                ->map(fn($d) => (object)[
                    'student_id' => $d->krs?->student_id,
                    'student'    => $d->krs?->student,
                ])
                ->filter(fn($m) => $m->student_id)
                ->values();
        }

        return response()->json($members);
    }

    public function journalList(ClassModel $class): JsonResponse
    {
        $journals = LectureJournal::where('class_id', $class->id)
            ->withCount('attendances')
            ->orderBy('meeting_number')
            ->get();
        return response()->json($journals);
    }

    public function storeJournal(Request $request, ClassModel $class): JsonResponse
    {
        // Mahasiswa tidak bisa buat jurnal
        if (auth()->user()->hasRole('MAHASISWA')) {
            return response()->json(['message' => 'Tidak diizinkan.'], 403);
        }

        $validated = $request->validate([
            'meeting_number'    => 'required|integer|min:1|max:16',
            'meeting_date'      => 'required|date',
            'topic'             => 'required|string|max:255',
            'description'       => 'nullable|string',
            'learning_activity' => 'nullable|string',
            'status'            => 'nullable|in:PLANNED,COMPLETED,CANCELLED',
            'latitude'          => 'nullable|numeric',
            'longitude'         => 'nullable|numeric',
        ]);

        $validated['class_id'] = $class->id;
        $validated['lecturer_id'] = $class->lecturer_id;

        // Upload foto jika ada
        if ($request->hasFile('photo')) {
            $request->validate(['photo' => 'image|mimes:jpeg,png,webp|max:5120']);
            $validated['photo_path'] = $request->file('photo')->store('journals', 'public');
        }

        $journal = LectureJournal::updateOrCreate(
            ['class_id' => $class->id, 'meeting_number' => $validated['meeting_number']],
            $validated
        );

        return response()->json(['message' => 'Jurnal berhasil disimpan.', 'data' => $journal], 201);
    }

    /** Ambil data pertemuan dari RPS/RPKPS untuk auto-fill jurnal */
    public function getFromRps(ClassModel $class): JsonResponse
    {
        // Cari RPKPS berdasarkan course_id dan lecturer_id kelas ini
        $rpkps = \App\Models\Rpkps::where('course_id', $class->course_id)
            ->where('lecturer_id', $class->lecturer_id)
            ->whereIn('status', ['DISETUJUI', 'DIKUNCI'])
            ->latest()
            ->first();

        if (!$rpkps) {
            // Coba cari RPKPS apapun untuk MK ini yang sudah disetujui
            $rpkps = \App\Models\Rpkps::where('course_id', $class->course_id)
                ->whereIn('status', ['DISETUJUI', 'DIKUNCI'])
                ->latest()
                ->first();
        }

        if (!$rpkps) {
            return response()->json(['message' => 'Tidak ada RPS yang disetujui untuk mata kuliah ini.', 'plans' => []], 404);
        }

        $plans = $rpkps->weeklyPlans()->orderBy('week_number')->get()
            ->map(fn($w) => [
                'meeting_number' => $w->week_number,
                'topic' => $w->sub_cpmk ?? '',
                'description' => $w->learning_material ?? '',
                'learning_activity' => is_array($w->methods) ? implode(', ', $w->methods) : ($w->methods ?? ''),
            ]);

        return response()->json([
            'rpkps_code' => $rpkps->code,
            'plans' => $plans,
        ]);
    }

    // =============================================
    // PRESENSI
    // =============================================

    public function attendanceList(LectureJournal $journal): JsonResponse
    {
        $attendances = Attendance::where('journal_id', $journal->id)
            ->with('student:id,nim,name')
            ->get();
        return response()->json($attendances);
    }

    public function saveAttendance(Request $request, LectureJournal $journal): JsonResponse
    {
        // Mahasiswa tidak bisa simpan presensi
        if (auth()->user()->hasRole('MAHASISWA')) {
            return response()->json(['message' => 'Tidak diizinkan.'], 403);
        }

        $validated = $request->validate([
            'attendances'             => 'required|array',
            'attendances.*.student_id'=> 'required|exists:students,id',
            'attendances.*.status'    => 'required|in:HADIR,IZIN,SAKIT,ALFA',
            'attendances.*.method'    => 'nullable|string',
        ]);

        DB::transaction(function () use ($journal, $validated) {
            foreach ($validated['attendances'] as $a) {
                Attendance::updateOrCreate(
                    ['journal_id' => $journal->id, 'student_id' => $a['student_id']],
                    ['status' => $a['status'], 'method' => $a['method'] ?? 'MANUAL', 'checked_in_at' => now()]
                );
            }
        });

        return response()->json(['message' => 'Presensi berhasil disimpan.']);
    }

    // =============================================
    // MATERI
    // =============================================

    public function materialList(ClassModel $class): JsonResponse
    {
        return response()->json(
            LectureMaterial::where('class_id', $class->id)->orderByDesc('created_at')->get()
        );
    }

    public function storeMaterial(Request $request, ClassModel $class): JsonResponse
    {
        $validated = $request->validate([
            'journal_id'   => 'nullable|exists:lecture_journals,id',
            'title'        => 'required|string|max:255',
            'description'  => 'nullable|string',
            'file_url'     => 'nullable|string|max:500',
            'is_published' => 'boolean',
        ]);
        $validated['class_id'] = $class->id;

        $material = LectureMaterial::create($validated);
        return response()->json(['message' => 'Materi berhasil ditambahkan.', 'data' => $material], 201);
    }

    public function destroyMaterial(LectureMaterial $material): JsonResponse
    {
        $material->delete();
        return response()->json(['message' => 'Materi berhasil dihapus.']);
    }

    // =============================================
    // TUGAS
    // =============================================

    public function assignmentList(ClassModel $class): JsonResponse
    {
        return response()->json(
            Assignment::where('class_id', $class->id)
                ->withCount('submissions')
                ->orderByDesc('created_at')->get()
        );
    }

    public function storeAssignment(Request $request, ClassModel $class): JsonResponse
    {
        $validated = $request->validate([
            'title'        => 'required|string|max:255',
            'description'  => 'nullable|string',
            'instructions' => 'nullable|string',
            'type'         => 'nullable|in:INDIVIDU,KELOMPOK',
            'due_date'     => 'nullable|date',
            'max_score'    => 'nullable|integer|min:1',
            'is_published' => 'boolean',
            'allow_late'   => 'boolean',
        ]);
        $validated['class_id'] = $class->id;

        $assignment = Assignment::create($validated);
        return response()->json(['message' => 'Tugas berhasil dibuat.', 'data' => $assignment], 201);
    }

    public function submitAssignment(Request $request, Assignment $assignment): JsonResponse
    {
        $request->validate([
            'content'  => 'nullable|string',
            'file_url' => 'nullable|string|max:500',
        ]);

        $student = auth()->user()->student;
        if (!$student) return response()->json(['message' => 'Akun bukan mahasiswa.'], 403);

        $submission = AssignmentSubmission::updateOrCreate(
            ['assignment_id' => $assignment->id, 'student_id' => $student->id],
            ['content' => $request->content, 'file_url' => $request->file_url, 'submitted_at' => now()]
        );

        return response()->json(['message' => 'Tugas berhasil dikumpulkan.', 'data' => $submission]);
    }

    public function gradeSubmission(Request $request, AssignmentSubmission $submission): JsonResponse
    {
        $request->validate(['score' => 'required|numeric|min:0', 'feedback' => 'nullable|string']);
        $submission->update([
            'score' => $request->score, 'feedback' => $request->feedback,
            'graded_at' => now(), 'graded_by' => auth()->id(),
        ]);
        return response()->json(['message' => 'Nilai berhasil disimpan.']);
    }

    // =============================================
    // PENGUMUMAN
    // =============================================

    public function announcementList(ClassModel $class): JsonResponse
    {
        return response()->json(
            ClassAnnouncement::where('class_id', $class->id)->with('user:id,name')->orderByDesc('created_at')->get()
        );
    }

    public function storeAnnouncement(Request $request, ClassModel $class): JsonResponse
    {
        $validated = $request->validate(['title' => 'required|string', 'content' => 'required|string']);
        $ann = ClassAnnouncement::create(array_merge($validated, ['class_id' => $class->id, 'user_id' => auth()->id()]));
        return response()->json(['message' => 'Pengumuman berhasil dibuat.', 'data' => $ann], 201);
    }

    public function destroyJournal(LectureJournal $journal): JsonResponse
    {
        $journal->attendances()->delete();
        $journal->delete();
        return response()->json(['message' => 'Jurnal pertemuan berhasil dihapus.']);
    }

    public function updateJournal(Request $request, LectureJournal $journal): JsonResponse
    {
        $validated = $request->validate([
            'meeting_number'    => 'sometimes|integer|min:1|max:16',
            'meeting_date'      => 'sometimes|date',
            'topic'             => 'sometimes|string|max:255',
            'description'       => 'nullable|string',
            'learning_activity' => 'nullable|string',
            'status'            => 'nullable|in:PLANNED,COMPLETED,CANCELLED',
        ]);
        $journal->update($validated);
        return response()->json(['message' => 'Jurnal berhasil diupdate.', 'data' => $journal->fresh()]);
    }

    public function destroyAssignment(Assignment $assignment): JsonResponse
    {
        $assignment->submissions()->delete();
        $assignment->delete();
        return response()->json(['message' => 'Tugas berhasil dihapus.']);
    }

    public function updateAssignment(Request $request, Assignment $assignment): JsonResponse
    {
        $validated = $request->validate([
            'title'        => 'sometimes|string|max:255',
            'description'  => 'nullable|string',
            'instructions' => 'nullable|string',
            'due_date'     => 'nullable|date',
            'max_score'    => 'nullable|integer|min:0',
            'is_published' => 'boolean',
        ]);
        $assignment->update($validated);
        return response()->json(['message' => 'Tugas berhasil diupdate.', 'data' => $assignment->fresh()]);
    }

    public function destroyAnnouncement(ClassAnnouncement $announcement): JsonResponse
    {
        $announcement->delete();
        return response()->json(['message' => 'Pengumuman berhasil dihapus.']);
    }

    public function updateAnnouncement(Request $request, ClassAnnouncement $announcement): JsonResponse
    {
        $validated = $request->validate([
            'title'   => 'sometimes|string|max:255',
            'content' => 'sometimes|string',
        ]);
        $announcement->update($validated);
        return response()->json(['message' => 'Pengumuman berhasil diupdate.', 'data' => $announcement->fresh()]);
    }
}
