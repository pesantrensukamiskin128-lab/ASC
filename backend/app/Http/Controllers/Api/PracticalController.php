<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PracticalAssessment;
use App\Models\PracticalAttendance;
use App\Models\PracticalGroup;
use App\Models\PracticalLocation;
use App\Models\PracticalLogbook;
use App\Models\PracticalParticipant;
use App\Models\PracticalProgram;
use App\Models\PracticalReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PracticalController extends Controller
{
    // === PROGRAMS ===
    public function programs(Request $request): JsonResponse
    {
        $data = PracticalProgram::with(['semester', 'studyProgram', 'coordinator'])
            ->withCount('participants')
            ->when($request->program_type, fn($q) => $q->where('program_type', $request->program_type))
            ->when($request->semester_id, fn($q) => $q->where('semester_id', $request->semester_id))
            ->when($request->search, fn($q) => $q->where('name', 'like', "%{$request->search}%"))
            ->orderByDesc('created_at')
            ->paginate($request->per_page ?? 15);
        return response()->json($data);
    }

    public function showProgram(PracticalProgram $program): JsonResponse
    {
        return response()->json($program->load(['semester', 'studyProgram', 'coordinator', 'locations.supervisor', 'groups.supervisor', 'groups.location']));
    }

    public function storeProgram(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'               => 'required|string|max:255',
            'program_type'       => 'required|in:KKN,PPL,MAGANG,PRAKTIKUM,PKL',
            'semester_id'        => 'required|exists:semesters,id',
            'study_program_id'   => 'nullable|exists:study_programs,id',
            'description'        => 'nullable|string',
            'registration_start' => 'nullable|date',
            'registration_end'   => 'nullable|date',
            'start_date'         => 'nullable|date',
            'end_date'           => 'nullable|date',
            'min_credits'        => 'nullable|integer|min:0',
            'credit_value'       => 'nullable|integer|min:0',
            'coordinator_id'     => 'nullable|exists:lecturers,id',
            'is_active'          => 'boolean',
        ]);
        $program = PracticalProgram::create($validated);
        return response()->json(['message' => 'Program berhasil dibuat.', 'data' => $program], 201);
    }

    public function updateProgram(Request $request, PracticalProgram $program): JsonResponse
    {
        $validated = $request->validate([
            'name'               => 'sometimes|string|max:255',
            'description'        => 'nullable|string',
            'registration_start' => 'nullable|date',
            'registration_end'   => 'nullable|date',
            'start_date'         => 'nullable|date',
            'end_date'           => 'nullable|date',
            'min_credits'        => 'nullable|integer|min:0',
            'credit_value'       => 'nullable|integer|min:0',
            'coordinator_id'     => 'nullable|exists:lecturers,id',
            'is_active'          => 'boolean',
        ]);
        $program->update($validated);
        return response()->json(['message' => 'Program berhasil diupdate.', 'data' => $program->fresh()]);
    }

    public function destroyProgram(PracticalProgram $program): JsonResponse
    {
        if ($program->participants()->count() > 0) return response()->json(['message' => 'Program sudah memiliki peserta.'], 422);
        $program->locations()->delete(); $program->groups()->delete(); $program->delete();
        return response()->json(['message' => 'Program berhasil dihapus.']);
    }

    // === LOCATIONS ===
    public function storeLocation(Request $request, PracticalProgram $program): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255', 'address' => 'nullable|string', 'city' => 'nullable|string|max:100',
            'contact_person' => 'nullable|string', 'contact_phone' => 'nullable|string|max:20',
            'capacity' => 'nullable|integer', 'supervisor_id' => 'nullable|exists:lecturers,id',
        ]);
        $loc = $program->locations()->create($validated);
        return response()->json(['message' => 'Lokasi berhasil ditambahkan.', 'data' => $loc], 201);
    }

    public function destroyLocation(PracticalProgram $program, PracticalLocation $location): JsonResponse
    {
        $location->delete();
        return response()->json(['message' => 'Lokasi berhasil dihapus.']);
    }

    // === GROUPS ===
    public function storeGroup(Request $request, PracticalProgram $program): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100', 'location_id' => 'nullable|exists:practical_locations,id',
            'supervisor_id' => 'nullable|exists:lecturers,id', 'notes' => 'nullable|string',
        ]);
        $group = $program->groups()->create($validated);
        return response()->json(['message' => 'Kelompok berhasil dibuat.', 'data' => $group->load(['location', 'supervisor'])], 201);
    }

    public function destroyGroup(PracticalProgram $program, PracticalGroup $group): JsonResponse
    {
        $group->participants()->update(['group_id' => null]);
        $group->delete();
        return response()->json(['message' => 'Kelompok berhasil dihapus.']);
    }

    // === PARTICIPANTS ===
    public function participants(Request $request, PracticalProgram $program): JsonResponse
    {
        $data = $program->participants()->with(['student.studyProgram', 'group', 'location', 'supervisor'])
            ->withCount(['logbooks', 'attendances', 'assessments'])
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->paginate($request->per_page ?? 20);
        return response()->json($data);
    }

    public function registerParticipant(Request $request, PracticalProgram $program): JsonResponse
    {
        $validated = $request->validate([
            'student_id'    => 'required|exists:students,id',
            'group_id'      => 'nullable|exists:practical_groups,id',
            'location_id'   => 'nullable|exists:practical_locations,id',
            'supervisor_id' => 'nullable|exists:lecturers,id',
        ]);
        if ($program->participants()->where('student_id', $validated['student_id'])->exists()) {
            return response()->json(['message' => 'Mahasiswa sudah terdaftar.'], 422);
        }
        $p = $program->participants()->create(array_merge($validated, ['status' => 'TERDAFTAR']));
        return response()->json(['message' => 'Peserta berhasil didaftarkan.', 'data' => $p->load('student')], 201);
    }

    /** Mahasiswa daftar sendiri ke program */
    public function selfRegister(Request $request, PracticalProgram $program): JsonResponse
    {
        $user    = auth()->user();
        $student = $user->student;

        if (!$student) {
            return response()->json(['message' => 'Akun bukan mahasiswa.'], 403);
        }
        if (!$program->is_active) {
            return response()->json(['message' => 'Program ini tidak aktif.'], 422);
        }
        // Cek periode pendaftaran
        $now = now()->toDateString();
        if ($program->registration_start && $now < $program->registration_start) {
            return response()->json(['message' => 'Pendaftaran belum dibuka.'], 422);
        }
        if ($program->registration_end && $now > $program->registration_end) {
            return response()->json(['message' => 'Pendaftaran sudah ditutup.'], 422);
        }
        if ($program->participants()->where('student_id', $student->id)->exists()) {
            return response()->json(['message' => 'Anda sudah terdaftar di program ini.'], 422);
        }

        $p = $program->participants()->create(['student_id' => $student->id, 'status' => 'TERDAFTAR']);
        return response()->json(['message' => 'Berhasil mendaftar ke program.', 'data' => $p], 201);
    }

    /** Mahasiswa lihat program yang sudah diikuti */
    public function myPrograms(Request $request): JsonResponse
    {
        $student = auth()->user()->student;
        if (!$student) return response()->json([]);

        $data = \App\Models\PracticalParticipant::where('student_id', $student->id)
            ->with(['program.semester', 'group', 'location', 'supervisor'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json($data);
    }

    public function updateParticipant(Request $request, PracticalParticipant $participant): JsonResponse
    {
        $validated = $request->validate([
            'group_id' => 'nullable|exists:practical_groups,id', 'location_id' => 'nullable|exists:practical_locations,id',
            'supervisor_id' => 'nullable|exists:lecturers,id', 'status' => 'nullable|in:TERDAFTAR,AKTIF,SELESAI,MENGUNDURKAN_DIRI,GAGAL',
        ]);
        $participant->update($validated);
        return response()->json(['message' => 'Peserta berhasil diupdate.', 'data' => $participant->fresh()]);
    }

    public function removeParticipant(PracticalParticipant $participant): JsonResponse
    {
        $participant->logbooks()->delete(); $participant->attendances()->delete();
        $participant->assessments()->delete(); $participant->reports()->delete();
        $participant->delete();
        return response()->json(['message' => 'Peserta berhasil dihapus.']);
    }

    // === LOGBOOK ===
    public function logbooks(PracticalParticipant $participant): JsonResponse
    {
        return response()->json($participant->logbooks()->orderByDesc('activity_date')->get());
    }

    public function storeLogbook(Request $request, PracticalParticipant $participant): JsonResponse
    {
        $validated = $request->validate([
            'activity_date' => 'required|date', 'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i', 'activity' => 'required|string',
            'result' => 'nullable|string', 'notes' => 'nullable|string',
        ]);
        $log = $participant->logbooks()->create(array_merge($validated, ['status' => 'SUBMITTED']));
        return response()->json(['message' => 'Logbook berhasil ditambahkan.', 'data' => $log], 201);
    }

    public function approveLogbook(Request $request, PracticalLogbook $logbook): JsonResponse
    {
        $request->validate(['action' => 'required|in:approve,revision', 'notes' => 'nullable|string']);
        $logbook->update([
            'status' => $request->action === 'approve' ? 'APPROVED' : 'REVISION',
            'approved_by' => auth()->id(), 'notes' => $request->notes ?? $logbook->notes,
        ]);
        return response()->json(['message' => 'Logbook berhasil diproses.']);
    }

    // === ATTENDANCE ===
    public function attendances(PracticalParticipant $participant): JsonResponse
    {
        return response()->json($participant->attendances()->orderByDesc('attendance_date')->get());
    }

    public function storeAttendance(Request $request, PracticalParticipant $participant): JsonResponse
    {
        $validated = $request->validate([
            'attendance_date' => 'required|date', 'status' => 'required|in:HADIR,IZIN,SAKIT,ALPHA', 'notes' => 'nullable|string',
        ]);
        $att = PracticalAttendance::updateOrCreate(
            ['participant_id' => $participant->id, 'attendance_date' => $validated['attendance_date']],
            $validated
        );
        return response()->json(['message' => 'Presensi berhasil dicatat.', 'data' => $att]);
    }

    // === ASSESSMENTS ===
    public function assessments(PracticalParticipant $participant): JsonResponse
    {
        return response()->json($participant->assessments()->get());
    }

    public function storeAssessment(Request $request, PracticalParticipant $participant): JsonResponse
    {
        $validated = $request->validate([
            'component' => 'required|string|max:100', 'score' => 'required|numeric|min:0|max:100',
            'weight' => 'nullable|numeric|min:0', 'notes' => 'nullable|string',
        ]);
        $a = $participant->assessments()->create(array_merge($validated, ['assessed_by' => auth()->id()]));
        return response()->json(['message' => 'Nilai berhasil disimpan.', 'data' => $a], 201);
    }

    // === REPORTS ===
    public function storeReport(Request $request, PracticalParticipant $participant): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255', 'abstract' => 'nullable|string',
            'file_url' => 'nullable|string|max:500',
        ]);
        $r = $participant->reports()->create(array_merge($validated, ['status' => 'SUBMITTED', 'submitted_at' => now()]));
        return response()->json(['message' => 'Laporan berhasil disubmit.', 'data' => $r], 201);
    }

    public function reviewReport(Request $request, PracticalReport $report): JsonResponse
    {
        $request->validate(['action' => 'required|in:approve,revision', 'notes' => 'nullable|string']);
        $report->update([
            'status' => $request->action === 'approve' ? 'APPROVED' : 'REVISION',
            'reviewer_notes' => $request->notes, 'reviewed_by' => auth()->id(),
            'approved_at' => $request->action === 'approve' ? now() : null,
        ]);
        return response()->json(['message' => 'Laporan berhasil diproses.']);
    }
}
