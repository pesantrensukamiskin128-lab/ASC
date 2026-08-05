<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Thesis;
use App\Models\ThesisDefense;
use App\Models\ThesisDefenseScore;
use App\Models\ThesisExaminer;
use App\Models\ThesisGuidance;
use App\Models\ThesisRevisionReview;
use App\Models\ThesisSeminarResult;
use App\Models\ThesisSupervisor;
use App\Models\ThesisTitleHistory;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ThesisController extends Controller
{
    // =========================================================
    // HELPERS
    // =========================================================

    private function getUserRole(): array
    {
        $user      = auth()->user();
        $isAdmin   = $user->hasRole('SUPER_ADMIN') || $user->hasRole('ADMIN_AKADEMIK');
        $isKaprodi = false;
        $isDosenWali = $user->hasRole('DOSEN_WALI');
        $lecturerId  = null;

        if ($user->lecturer) {
            $lecturerId = $user->lecturer->id;
            $isKaprodi  = \App\Models\LecturerPosition::where('lecturer_id', $lecturerId)
                ->where('is_active', true)
                ->whereIn('position_code', ['KAPRODI', 'SEKPRODI'])
                ->exists();
        }

        return compact('user', 'isAdmin', 'isKaprodi', 'isDosenWali', 'lecturerId');
    }

    private function notifyStudent(Thesis $thesis, string $title, string $msg, string $type = 'info'): void
    {
        $thesis->loadMissing('student');
        if ($thesis->student?->user_id) {
            \App\Models\AppNotification::send(
                $thesis->student->user_id, $title, $msg, $type, '/skripsi/' . $thesis->id
            );
        }
    }

    // =========================================================
    // INDEX & SHOW
    // =========================================================

    public function index(Request $request): JsonResponse
    {
        ['user' => $user, 'isAdmin' => $isAdmin, 'isKaprodi' => $isKaprodi,
         'isDosenWali' => $isDosenWali, 'lecturerId' => $lecturerId] = $this->getUserRole();

        $query = Thesis::with(['student.studyProgram', 'supervisors.lecturer', 'studyProgram'])
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->study_program_id, fn($q) => $q->where('study_program_id', $request->study_program_id))
            ->when($request->search, fn($q) => $q->where('title', 'like', "%{$request->search}%")
                ->orWhereHas('student', fn($q2) => $q2->where('name', 'like', "%{$request->search}%")
                    ->orWhere('nim', 'like', "%{$request->search}%")));

        if ($user->hasRole('MAHASISWA') && $user->student) {
            $query->where('student_id', $user->student->id);
        } elseif ($isAdmin || $user->hasRole('LP2M')) {
            // semua skripsi
        } elseif ($isKaprodi && $user->lecturer?->study_program_id) {
            $query->where('study_program_id', $user->lecturer->study_program_id);
        } elseif ($isDosenWali) {
            // dosen wali: lihat pengajuan judul baru + yang ia bimbing/uji
            $query->where(fn($q) => $q
                ->whereIn('status', [Thesis::STATUS_PENGAJUAN_JUDUL, Thesis::STATUS_JUDUL_DITOLAK])
                ->orWhereHas('supervisors', fn($q2) => $q2->where('lecturer_id', $lecturerId))
                ->orWhereHas('examiners', fn($q2) => $q2->where('lecturer_id', $lecturerId))
            );
        } elseif ($lecturerId) {
            // dosen biasa: hanya yang ia bimbing/uji
            $query->where(fn($q) => $q
                ->whereHas('supervisors', fn($q2) => $q2->where('lecturer_id', $lecturerId))
                ->orWhereHas('examiners', fn($q2) => $q2->where('lecturer_id', $lecturerId))
            );
        } else {
            $query->whereRaw('1 = 0');
        }

        return response()->json($query->orderByDesc('created_at')->paginate($request->per_page ?? 15));
    }

    public function show(Thesis $thesis): JsonResponse
    {
        return response()->json($thesis->load([
            'student.studyProgram', 'studyProgram', 'supervisors.lecturer',
            'examiners.lecturer', 'guidances.supervisor',
            'defenses.scores.examiner', 'titleHistories',
            'revisionReviews.examiner', 'seminarResults.recordedBy',
        ]));
    }

    // =========================================================
    // STORE (mahasiswa buat draft)
    // =========================================================

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id'       => 'required|exists:students,id',
            'title'            => 'required|string|max:500',
            'title_english'    => 'nullable|string|max:500',
            'type'             => 'nullable|in:SKRIPSI,TESIS,DISERTASI,TUGAS_AKHIR',
            'proposal_file_url'=> 'nullable|string|max:1000',
        ]);

        $student = \App\Models\Student::findOrFail($validated['student_id']);

        $existing = Thesis::where('student_id', $validated['student_id'])
            ->whereNotIn('status', [Thesis::STATUS_JUDUL_DITOLAK, Thesis::STATUS_GAGAL])
            ->first();
        if ($existing) {
            return response()->json([
                'message' => 'Mahasiswa sudah memiliki pengajuan skripsi yang aktif.',
                'data'    => $existing,
            ], 422);
        }

        $thesis = Thesis::create([
            'student_id'        => $validated['student_id'],
            'study_program_id'  => $student->study_program_id,
            'title'             => $validated['title'],
            'title_english'     => $validated['title_english'] ?? null,
            'type'              => $validated['type'] ?? 'SKRIPSI',
            'proposal_file_url' => $validated['proposal_file_url'] ?? null,
            'status'            => Thesis::STATUS_DRAFT,
            'submission_date'   => now()->toDateString(),
        ]);

        return response()->json(['message' => 'Draft skripsi berhasil dibuat.', 'data' => $thesis], 201);
    }

    // Mahasiswa update draft (judul, abstrak, kata kunci, link proposal)
    public function updateDraft(Request $request, Thesis $thesis): JsonResponse
    {
        if ($thesis->status !== Thesis::STATUS_DRAFT) {
            return response()->json(['message' => 'Hanya skripsi berstatus DRAFT yang bisa diedit.'], 422);
        }

        $user = auth()->user();
        if ($user->hasRole('MAHASISWA') && $user->student?->id !== $thesis->student_id) {
            return response()->json(['message' => 'Bukan skripsi Anda.'], 403);
        }

        $validated = $request->validate([
            'title'            => 'sometimes|string|max:500',
            'title_english'    => 'nullable|string|max:500',
            'abstract'         => 'nullable|string',
            'keywords'         => 'nullable|string',
            'research_field'   => 'nullable|string|max:255',
            'proposal_file_url'=> 'nullable|string|max:1000',
            'submission_link'  => 'nullable|string|max:1000',
        ]);

        $thesis->update($validated);
        return response()->json(['message' => 'Draft berhasil diupdate.', 'data' => $thesis->fresh()]);
    }

    // Mahasiswa submit draft → PENGAJUAN_JUDUL
    public function submitToKaprodi(Request $request, Thesis $thesis): JsonResponse
    {
        if ($thesis->status !== Thesis::STATUS_DRAFT) {
            return response()->json(['message' => 'Hanya skripsi berstatus DRAFT yang bisa diajukan.'], 422);
        }

        $user = auth()->user();
        if ($user->hasRole('MAHASISWA') && $user->student?->id !== $thesis->student_id) {
            return response()->json(['message' => 'Bukan skripsi Anda.'], 403);
        }

        $request->validate(['submission_link' => 'nullable|string|max:1000']);

        $thesis->update([
            'status'          => Thesis::STATUS_PENGAJUAN_JUDUL,
            'submission_link' => $request->submission_link ?? $thesis->submission_link,
            'submission_date' => now()->toDateString(),
        ]);

        $this->notifyStudent($thesis, 'Pengajuan Judul Terkirim', 'Judul skripsi Anda telah diajukan ke Ka.Prodi / Dosen Pembimbing Akademik.', 'info');

        return response()->json(['message' => 'Pengajuan judul berhasil dikirim.']);
    }

    // =========================================================
    // REVIEW JUDUL (Kaprodi / Dosen Wali)
    // =========================================================

    public function reviewTitle(Request $request, Thesis $thesis): JsonResponse
    {
        ['isAdmin' => $isAdmin, 'isKaprodi' => $isKaprodi, 'isDosenWali' => $isDosenWali] = $this->getUserRole();

        if (!$isAdmin && !$isKaprodi && !$isDosenWali) {
            return response()->json(['message' => 'Tidak memiliki akses untuk mereview judul.'], 403);
        }

        if ($thesis->status !== Thesis::STATUS_PENGAJUAN_JUDUL) {
            return response()->json(['message' => 'Status skripsi bukan PENGAJUAN_JUDUL.'], 422);
        }

        $request->validate([
            'action'     => 'required|in:approve,reject',
            'admin_note' => 'nullable|string',
        ]);

        if ($request->action === 'approve') {
            $thesis->update([
                'status'       => Thesis::STATUS_SEMINAR_PROPOSAL,
                'approval_date'=> now()->toDateString(),
                'admin_note'   => $request->admin_note,
            ]);
            $this->notifyStudent($thesis, 'Judul Skripsi Disetujui ✓',
                'Judul skripsi Anda telah disetujui. Selanjutnya akan dijadwalkan Seminar Proposal.' .
                ($request->admin_note ? " Catatan: {$request->admin_note}" : ''), 'success');
        } else {
            $thesis->update([
                'status'     => Thesis::STATUS_JUDUL_DITOLAK,
                'admin_note' => $request->admin_note,
            ]);
            $this->notifyStudent($thesis, 'Judul Skripsi Ditolak',
                'Judul skripsi Anda ditolak. Silakan ajukan ulang.' .
                ($request->admin_note ? " Alasan: {$request->admin_note}" : ''), 'error');
        }

        return response()->json(['message' => 'Review judul berhasil.', 'data' => $thesis->fresh()]);
    }

    // Mahasiswa yang ditolak bisa reset ke draft dan ajukan ulang
    public function resubmit(Request $request, Thesis $thesis): JsonResponse
    {
        if ($thesis->status !== Thesis::STATUS_JUDUL_DITOLAK) {
            return response()->json(['message' => 'Hanya skripsi berstatus JUDUL_DITOLAK yang bisa diajukan ulang.'], 422);
        }

        $user = auth()->user();
        if ($user->hasRole('MAHASISWA') && $user->student?->id !== $thesis->student_id) {
            return response()->json(['message' => 'Bukan skripsi Anda.'], 403);
        }

        $thesis->update(['status' => Thesis::STATUS_DRAFT, 'admin_note' => null]);
        return response()->json(['message' => 'Skripsi dikembalikan ke Draft. Silakan edit dan ajukan ulang.']);
    }

    // =========================================================
    // SEMINAR PROPOSAL (Kaprodi menunjuk penguji & catat hasil)
    // =========================================================

    public function scheduleSeminar(Request $request, Thesis $thesis): JsonResponse
    {
        ['isAdmin' => $isAdmin, 'isKaprodi' => $isKaprodi] = $this->getUserRole();
        if (!$isAdmin && !$isKaprodi) {
            return response()->json(['message' => 'Hanya Kaprodi yang dapat menjadwalkan seminar.'], 403);
        }

        $request->validate([
            'seminar_date' => 'required|date',
            'room'         => 'nullable|string|max:100',
            'examiner_ids' => 'required|array|min:1',
            'examiner_ids.*' => 'exists:lecturers,id',
        ]);

        DB::transaction(function () use ($request, $thesis) {
            // Hapus penguji seminar sebelumnya jika ada
            $thesis->examiners()->whereIn('role', ['PENGUJI_1', 'PENGUJI_2', 'KETUA_PENGUJI', 'SEKRETARIS'])->delete();

            $roles = ['KETUA_PENGUJI', 'PENGUJI_1', 'PENGUJI_2', 'SEKRETARIS'];
            foreach ($request->examiner_ids as $i => $lecturerId) {
                $thesis->examiners()->create(['lecturer_id' => $lecturerId, 'role' => $roles[$i] ?? 'PENGUJI_1']);
                // Notifikasi ke penguji
                NotificationService::thesisExaminerAssigned($lecturerId, $thesis->student?->name ?? '-', $thesis->title, $roles[$i] ?? 'PENGUJI_1');
            }

            ThesisSeminarResult::create([
                'thesis_id'    => $thesis->id,
                'seminar_type' => 'PROPOSAL',
                'seminar_date' => $request->seminar_date,
                'room'         => $request->room,
                'recorded_by'  => auth()->id(),
            ]);
        });

        $this->notifyStudent($thesis, 'Seminar Proposal Dijadwalkan',
            "Seminar proposal Anda dijadwalkan pada " . date('d F Y', strtotime($request->seminar_date)) .
            ($request->room ? " di ruang {$request->room}" : '') . '.', 'info');

        return response()->json(['message' => 'Seminar proposal berhasil dijadwalkan.']);
    }

    // Penguji input nilai seminar
    public function inputSeminarScore(Request $request, Thesis $thesis): JsonResponse
    {
        ['lecturerId' => $lecturerId] = $this->getUserRole();

        $request->validate([
            'score' => 'required|numeric|min:0|max:100',
            'notes' => 'nullable|string',
            'type'  => 'nullable|in:SEMINAR_PROPOSAL,SIDANG_AKHIR',
        ]);

        $type = $request->type ?? 'SEMINAR_PROPOSAL';

        $review = ThesisRevisionReview::updateOrCreate(
            ['thesis_id' => $thesis->id, 'examiner_id' => $lecturerId, 'type' => $type],
            ['score' => $request->score, 'notes' => $request->notes, 'reviewed_at' => now()]
        );

        return response()->json(['message' => 'Nilai berhasil disimpan.', 'data' => $review]);
    }

    // Kaprodi catat hasil seminar proposal
    public function recordSeminarResult(Request $request, Thesis $thesis): JsonResponse
    {
        ['isAdmin' => $isAdmin, 'isKaprodi' => $isKaprodi] = $this->getUserRole();
        if (!$isAdmin && !$isKaprodi) {
            return response()->json(['message' => 'Hanya Kaprodi yang dapat mencatat hasil seminar.'], 403);
        }

        $request->validate([
            'result'      => 'required|in:DISETUJUI,REVISI,TIDAK_LULUS',
            'notes'       => 'nullable|string',
            'seminar_type'=> 'nullable|in:PROPOSAL,SIDANG',
        ]);

        $seminarType = $request->seminar_type ?? 'PROPOSAL';

        ThesisSeminarResult::where('thesis_id', $thesis->id)
            ->where('seminar_type', $seminarType)
            ->update(['result' => $request->result, 'notes' => $request->notes, 'recorded_by' => auth()->id()]);

        if ($seminarType === 'PROPOSAL') {
            if ($request->result === 'DISETUJUI') {
                $thesis->update(['status' => Thesis::STATUS_PENUNJUKAN_PEMBIMBING]);
                $this->notifyStudent($thesis, 'Seminar Proposal Lulus ✓',
                    'Seminar proposal Anda disetujui. Selanjutnya Ka.Prodi akan menunjuk dosen pembimbing.', 'success');
            } elseif ($request->result === 'REVISI') {
                $thesis->update(['status' => Thesis::STATUS_REVISI_PROPOSAL]);
                $this->notifyStudent($thesis, 'Revisi Proposal Diperlukan',
                    'Proposal Anda perlu direvisi. ' . ($request->notes ? "Catatan: {$request->notes}" : ''), 'warning');
            } else {
                $this->notifyStudent($thesis, 'Seminar Proposal Tidak Lulus',
                    'Seminar proposal Anda tidak lulus. Hubungi Ka.Prodi untuk informasi selanjutnya.', 'error');
            }
        } elseif ($seminarType === 'SIDANG') {
            if ($request->result === 'DISETUJUI') {
                $thesis->update(['status' => Thesis::STATUS_SELESAI, 'completion_date' => now()->toDateString()]);
                $this->notifyStudent($thesis, '🎓 Sidang Lulus!', 'Selamat! Anda dinyatakan lulus sidang munaqosyah.', 'success');
            } elseif ($request->result === 'REVISI') {
                $thesis->update(['status' => Thesis::STATUS_REVISI_SIDANG]);
                $this->notifyStudent($thesis, 'Revisi Sidang Diperlukan',
                    'Anda lulus dengan revisi. Silakan lakukan revisi sesuai catatan penguji.', 'warning');
            } else {
                $this->notifyStudent($thesis, 'Sidang Tidak Lulus',
                    'Sidang munaqosyah Anda tidak lulus. Hubungi Ka.Prodi untuk informasi selanjutnya.', 'error');
            }
        }

        return response()->json(['message' => 'Hasil seminar berhasil dicatat.', 'data' => $thesis->fresh()]);
    }

    // =========================================================
    // REVISI PROPOSAL (mahasiswa upload, penguji periksa)
    // =========================================================

    public function uploadRevisionLink(Request $request, Thesis $thesis): JsonResponse
    {
        $user = auth()->user();
        if ($user->hasRole('MAHASISWA') && $user->student?->id !== $thesis->student_id) {
            return response()->json(['message' => 'Bukan skripsi Anda.'], 403);
        }

        $allowedStatuses = [Thesis::STATUS_REVISI_PROPOSAL, Thesis::STATUS_PEMERIKSAAN_REVISI,
                            Thesis::STATUS_BIMBINGAN, Thesis::STATUS_REVISI_SIDANG];
        if (!in_array($thesis->status, $allowedStatuses)) {
            return response()->json(['message' => 'Status tidak memungkinkan upload revisi.'], 422);
        }

        $request->validate(['revision_link' => 'required|string|max:1000']);
        $thesis->update([
            'revision_link' => $request->revision_link,
            'status'        => in_array($thesis->status, [Thesis::STATUS_REVISI_PROPOSAL])
                               ? Thesis::STATUS_PEMERIKSAAN_REVISI
                               : $thesis->status,
        ]);

        return response()->json(['message' => 'Link revisi berhasil dikirim.']);
    }

    // Penguji periksa revisi proposal
    public function reviewRevision(Request $request, Thesis $thesis): JsonResponse
    {
        ['isAdmin' => $isAdmin, 'isKaprodi' => $isKaprodi, 'lecturerId' => $lecturerId] = $this->getUserRole();

        $request->validate([
            'result' => 'required|in:PERLU_REVISI,SELESAI,SIAP_SIDANG',
            'notes'  => 'nullable|string',
        ]);

        ThesisRevisionReview::updateOrCreate(
            ['thesis_id' => $thesis->id, 'examiner_id' => $lecturerId, 'type' => 'SEMINAR_PROPOSAL'],
            ['revision_result' => $request->result, 'notes' => $request->notes, 'reviewed_at' => now()]
        );

        if ($request->result === 'SELESAI') {
            $thesis->update(['status' => Thesis::STATUS_PENUNJUKAN_PEMBIMBING]);
            $this->notifyStudent($thesis, 'Revisi Proposal Selesai ✓',
                'Revisi proposal Anda telah diterima. Ka.Prodi akan menunjuk dosen pembimbing.', 'success');
        } elseif ($request->result === 'SIAP_SIDANG') {
            $thesis->update(['status' => Thesis::STATUS_SIDANG]);
            $this->notifyStudent($thesis, 'Siap Sidang Munaqosyah', 'Dosen pembimbing menyatakan skripsi Anda siap untuk sidang.', 'success');
        } else {
            $thesis->update(['status' => Thesis::STATUS_REVISI_PROPOSAL]);
            $this->notifyStudent($thesis, 'Revisi Masih Diperlukan',
                'Masih ada revisi yang perlu dilakukan. ' . ($request->notes ? "Catatan: {$request->notes}" : ''), 'warning');
        }

        return response()->json(['message' => 'Review revisi berhasil.']);
    }

    // =========================================================
    // PENUNJUKAN PEMBIMBING (Kaprodi)
    // =========================================================

    public function assignSupervisors(Request $request, Thesis $thesis): JsonResponse
    {
        ['isAdmin' => $isAdmin, 'isKaprodi' => $isKaprodi] = $this->getUserRole();
        if (!$isAdmin && !$isKaprodi) {
            return response()->json(['message' => 'Hanya Kaprodi yang dapat menunjuk dosen pembimbing.'], 403);
        }

        $allowedStatuses = [Thesis::STATUS_PENUNJUKAN_PEMBIMBING, Thesis::STATUS_BIMBINGAN];
        if (!$isAdmin && !in_array($thesis->status, $allowedStatuses)) {
            return response()->json(['message' => 'Pembimbing hanya bisa ditunjuk setelah mahasiswa lulus seminar proposal.'], 422);
        }

        $request->validate([
            'supervisors'         => 'required|array|min:1|max:3',
            'supervisors.*.id'    => 'required|exists:lecturers,id',
            'supervisors.*.role'  => 'required|in:PEMBIMBING_1,PEMBIMBING_2,PEMBIMBING_3',
        ]);

        DB::transaction(function () use ($request, $thesis) {
            $thesis->supervisors()->delete();
            foreach ($request->supervisors as $s) {
                $thesis->supervisors()->create(['lecturer_id' => $s['id'], 'role' => $s['role']]);
                NotificationService::thesisSupervisorAssigned($s['id'], $thesis->student?->name ?? '-', $thesis->title, $s['role']);
            }
            $thesis->update([
                'status'                 => Thesis::STATUS_BIMBINGAN,
                'supervisor_assigned_by' => auth()->id(),
                'supervisor_assigned_at' => now(),
            ]);
        });

        $this->notifyStudent($thesis, 'Dosen Pembimbing Ditunjuk ✓',
            'Ka.Prodi telah menunjuk dosen pembimbing skripsi Anda. Silakan mulai proses bimbingan.', 'success');

        return response()->json(['message' => 'Dosen pembimbing berhasil ditunjuk.', 'data' => $thesis->fresh(['supervisors.lecturer'])]);
    }

    // =========================================================
    // BIMBINGAN (mahasiswa & dosen pembimbing)
    // =========================================================

    public function storeGuidance(Request $request, Thesis $thesis): JsonResponse
    {
        $user = auth()->user();
        $validated = $request->validate([
            'guidance_date'       => 'required|date',
            'topic'               => 'required|string',
            'discussion'          => 'nullable|string',
            'suggestion'          => 'nullable|string',
            'student_note'        => 'nullable|string',
            'chapter_reviewed'    => 'nullable|string|max:100',
            'progress_percentage' => 'nullable|integer|min:0|max:100',
            'revision_link'       => 'nullable|string|max:1000',
        ]);

        $lecturerId = $user->lecturer?->id ?? $thesis->supervisors()->first()?->lecturer_id;

        $g = $thesis->guidances()->create(array_merge($validated, [
            'supervisor_id' => $lecturerId,
            'status'        => 'SELESAI',
        ]));

        return response()->json(['message' => 'Bimbingan berhasil dicatat.', 'data' => $g->load('supervisor')], 201);
    }

    // Dosen pembimbing nyatakan siap sidang
    public function declareReadyForDefense(Thesis $thesis): JsonResponse
    {
        ['lecturerId' => $lecturerId, 'isAdmin' => $isAdmin] = $this->getUserRole();

        // Cek apakah user adalah pembimbing skripsi ini
        $isSupervisor = $thesis->supervisors()->where('lecturer_id', $lecturerId)->exists();
        if (!$isAdmin && !$isSupervisor) {
            return response()->json(['message' => 'Anda bukan pembimbing skripsi ini.'], 403);
        }

        if ($thesis->status !== Thesis::STATUS_BIMBINGAN) {
            return response()->json(['message' => 'Status skripsi harus BIMBINGAN untuk menyatakan siap sidang.'], 422);
        }

        $thesis->update(['status' => Thesis::STATUS_SIDANG]);
        $this->notifyStudent($thesis, 'Skripsi Siap Sidang ✓',
            'Dosen pembimbing menyatakan skripsi Anda siap untuk sidang munaqosyah.', 'success');

        return response()->json(['message' => 'Skripsi dinyatakan siap sidang.']);
    }

    // =========================================================
    // SIDANG MUNAQOSYAH (Kaprodi jadwalkan, penguji nilai)
    // =========================================================

    public function scheduleDefense(Request $request, Thesis $thesis): JsonResponse
    {
        ['isAdmin' => $isAdmin, 'isKaprodi' => $isKaprodi] = $this->getUserRole();
        if (!$isAdmin && !$isKaprodi) {
            return response()->json(['message' => 'Hanya Kaprodi yang dapat menjadwalkan sidang.'], 403);
        }

        $request->validate([
            'defense_date'   => 'required|date',
            'defense_time'   => 'nullable|date_format:H:i',
            'room'           => 'nullable|string|max:100',
            'examiner_ids'   => 'required|array|min:1',
            'examiner_ids.*' => 'exists:lecturers,id',
        ]);

        DB::transaction(function () use ($request, $thesis) {
            // Hapus penguji sidang sebelumnya
            $thesis->examiners()->whereIn('role', ['KETUA_PENGUJI', 'PENGUJI_1', 'PENGUJI_2', 'SEKRETARIS'])->delete();

            $roles = ['KETUA_PENGUJI', 'PENGUJI_1', 'PENGUJI_2', 'SEKRETARIS'];
            foreach ($request->examiner_ids as $i => $lecturerId) {
                $thesis->examiners()->create(['lecturer_id' => $lecturerId, 'role' => $roles[$i] ?? 'PENGUJI_1']);
                NotificationService::thesisExaminerAssigned($lecturerId, $thesis->student?->name ?? '-', $thesis->title, $roles[$i] ?? 'PENGUJI_1');
            }

            $thesis->defenses()->create([
                'type'         => 'SIDANG_AKHIR',
                'defense_date' => $request->defense_date,
                'defense_time' => $request->defense_time,
                'room'         => $request->room,
            ]);
        });

        $this->notifyStudent($thesis, 'Sidang Munaqosyah Dijadwalkan',
            "Sidang munaqosyah Anda dijadwalkan pada " . date('d F Y', strtotime($request->defense_date)) .
            ($request->room ? " di ruang {$request->room}" : '') . '.', 'info');

        return response()->json(['message' => 'Sidang berhasil dijadwalkan.']);
    }

    // =========================================================
    // UPLOAD SKRIPSI FINAL & PUBLIKASI
    // =========================================================

    public function uploadFinal(Request $request, Thesis $thesis): JsonResponse
    {
        $user = auth()->user();
        if ($user->hasRole('MAHASISWA') && $user->student?->id !== $thesis->student_id) {
            return response()->json(['message' => 'Bukan skripsi Anda.'], 403);
        }

        $allowedStatuses = [Thesis::STATUS_SELESAI, Thesis::STATUS_REVISI_SIDANG, Thesis::STATUS_BIMBINGAN];
        if (!in_array($thesis->status, $allowedStatuses)) {
            return response()->json(['message' => 'Status tidak memungkinkan upload skripsi final.'], 422);
        }

        $request->validate([
            'file'     => 'required|file|mimes:pdf|max:30720', // 30MB
            'abstract' => 'nullable|string',
            'keywords' => 'nullable|string',
        ]);

        $path = $request->file('file')->store('thesis-final', 'public');

        $thesis->update([
            'final_pdf_path' => $path,
            'abstract'       => $request->abstract ?? $thesis->abstract,
            'keywords'       => $request->keywords ?? $thesis->keywords,
            'status'         => Thesis::STATUS_SELESAI,
        ]);

        return response()->json(['message' => 'Skripsi final berhasil diupload.', 'path' => $path]);
    }

    public function publish(Request $request, Thesis $thesis): JsonResponse
    {
        ['isAdmin' => $isAdmin] = $this->getUserRole();

        // Hanya admin/LP2M yang bisa publikasi
        if (!$isAdmin && !auth()->user()->hasPermission('skripsi.publish')) {
            return response()->json(['message' => 'Tidak memiliki akses publikasi.'], 403);
        }

        if ($thesis->status !== Thesis::STATUS_SELESAI) {
            return response()->json(['message' => 'Hanya skripsi berstatus SELESAI yang dapat dipublikasikan.'], 422);
        }

        $request->validate([
            'repository_url'   => 'nullable|string|max:500',
            'cover_image'      => 'nullable|file|mimes:jpg,jpeg,png|max:5120',
        ]);

        $coverPath = $thesis->cover_image_path;
        if ($request->hasFile('cover_image')) {
            $coverPath = $request->file('cover_image')->store('thesis-covers', 'public');
        }

        $thesis->update([
            'status'         => Thesis::STATUS_DIPUBLIKASIKAN,
            'is_published'   => true,
            'published_at'   => now(),
            'published_by'   => auth()->id(),
            'repository_url' => $request->repository_url,
            'cover_image_path' => $coverPath,
        ]);

        $this->notifyStudent($thesis, '🎉 Skripsi Dipublikasikan',
            'Skripsi Anda telah dipublikasikan ke repository institusi.', 'success');

        return response()->json(['message' => 'Skripsi berhasil dipublikasikan.']);
    }

    // =========================================================
    // LEGACY / UTILS (dipertahankan untuk kompatibilitas)
    // =========================================================

    public function updateTitle(Request $request, Thesis $thesis): JsonResponse
    {
        $request->validate(['title' => 'required|string|max:500', 'reason' => 'nullable|string']);
        ThesisTitleHistory::create([
            'thesis_id' => $thesis->id, 'old_title' => $thesis->title,
            'new_title' => $request->title, 'reason' => $request->reason, 'approved_by' => auth()->id(),
        ]);
        $thesis->update(['title' => $request->title]);
        return response()->json(['message' => 'Judul berhasil diubah.']);
    }

    public function addExaminer(Request $request, Thesis $thesis): JsonResponse
    {
        $validated = $request->validate([
            'lecturer_id' => 'required|exists:lecturers,id',
            'role'        => 'required|in:KETUA_PENGUJI,PENGUJI_1,PENGUJI_2,SEKRETARIS',
        ]);
        if ($thesis->examiners()->where('lecturer_id', $validated['lecturer_id'])->exists()) {
            return response()->json(['message' => 'Dosen sudah terdaftar sebagai penguji.'], 422);
        }
        $e = $thesis->examiners()->create($validated);
        $thesis->loadMissing('student');
        NotificationService::thesisExaminerAssigned($validated['lecturer_id'], $thesis->student?->name ?? '-', $thesis->title, $validated['role']);
        return response()->json(['message' => 'Penguji ditambahkan.', 'data' => $e->load('lecturer')], 201);
    }

    public function removeExaminer(Thesis $thesis, ThesisExaminer $examiner): JsonResponse
    {
        $examiner->delete();
        return response()->json(['message' => 'Penguji dihapus.']);
    }

    public function removeSupervisor(Thesis $thesis, ThesisSupervisor $supervisor): JsonResponse
    {
        ['isAdmin' => $isAdmin, 'isKaprodi' => $isKaprodi] = $this->getUserRole();
        if (!$isAdmin && !$isKaprodi) {
            return response()->json(['message' => 'Hanya Kaprodi yang dapat menghapus pembimbing.'], 403);
        }
        $supervisor->delete();
        return response()->json(['message' => 'Pembimbing dihapus.']);
    }

    public function updateDefenseResult(Request $request, ThesisDefense $defense): JsonResponse
    {
        $validated = $request->validate([
            'result'            => 'required|in:LULUS,LULUS_DENGAN_REVISI,TIDAK_LULUS,DITUNDA',
            'notes'             => 'nullable|string',
            'revision_deadline' => 'nullable|date',
        ]);
        $defense->update($validated);
        return response()->json(['message' => 'Hasil sidang berhasil disimpan.', 'data' => $defense->fresh()]);
    }

    public function storeDefenseScore(Request $request, ThesisDefense $defense): JsonResponse
    {
        $validated = $request->validate([
            'scores'               => 'required|array',
            'scores.*.examiner_id' => 'required|exists:lecturers,id',
            'scores.*.component'   => 'required|string|max:100',
            'scores.*.score'       => 'required|numeric|min:0|max:100',
            'scores.*.notes'       => 'nullable|string',
        ]);
        foreach ($validated['scores'] as $s) {
            ThesisDefenseScore::updateOrCreate(
                ['defense_id' => $defense->id, 'examiner_id' => $s['examiner_id'], 'component' => $s['component']],
                ['score' => $s['score'], 'notes' => $s['notes'] ?? null]
            );
        }
        $avg = $defense->scores()->avg('score');
        $defense->thesis->update(['final_score' => round($avg, 2)]);
        return response()->json(['message' => 'Nilai sidang berhasil disimpan.', 'average' => round($avg, 2)]);
    }

    public function dashboard(Request $request): JsonResponse
    {
        ['user' => $user, 'isAdmin' => $isAdmin, 'isKaprodi' => $isKaprodi, 'lecturerId' => $lecturerId] = $this->getUserRole();
        $query = Thesis::query();

        if (!$isAdmin) {
            if ($isKaprodi && $user->lecturer?->study_program_id) {
                $query->where('study_program_id', $user->lecturer->study_program_id);
            } elseif ($lecturerId) {
                $query->whereHas('supervisors', fn($q) => $q->where('lecturer_id', $lecturerId));
            }
        }

        $counts = [];
        $statuses = [
            Thesis::STATUS_DRAFT, Thesis::STATUS_PENGAJUAN_JUDUL, Thesis::STATUS_JUDUL_DITOLAK,
            Thesis::STATUS_SEMINAR_PROPOSAL, Thesis::STATUS_REVISI_PROPOSAL,
            Thesis::STATUS_PEMERIKSAAN_REVISI, Thesis::STATUS_PENUNJUKAN_PEMBIMBING,
            Thesis::STATUS_BIMBINGAN, Thesis::STATUS_SIDANG, Thesis::STATUS_REVISI_SIDANG,
            Thesis::STATUS_SELESAI, Thesis::STATUS_DIPUBLIKASIKAN, Thesis::STATUS_GAGAL,
        ];
        foreach ($statuses as $status) {
            $counts[strtolower($status)] = (clone $query)->where('status', $status)->count();
        }
        $counts['total'] = (clone $query)->count();

        return response()->json($counts);
    }
}
