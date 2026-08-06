<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Penelitian;
use App\Models\PenelitianFunding;
use App\Models\PenelitianPeriod;
use App\Models\PenelitianReviewer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PenelitianController extends Controller
{
    // =========================================================
    // HELPERS
    // =========================================================

    private function getUserRole(): array
    {
        $user       = Auth::user();
        $roles      = $user->getRoleNames()->toArray();
        $isAdmin    = in_array('SUPER_ADMIN', $roles) || in_array('ADMIN_AKADEMIK', $roles);
        $isLp2m     = in_array('LP2M', $roles) || $isAdmin;
        $isKeuangan = in_array('ADMIN_KEUANGAN', $roles) || $isAdmin;

        // Cek jabatan Kaprodi via position system
        $isKaprodi = false;
        if ($user->lecturer) {
            $isKaprodi = \App\Models\LecturerPosition::where('lecturer_id', $user->lecturer->id)
                ->where('is_active', true)
                ->whereIn('position_code', ['KAPRODI', 'SEKPRODI'])
                ->exists();
        }
        $isKaprodi = $isKaprodi || $isAdmin;

        return [
            'user'       => $user,
            'isAdmin'    => $isAdmin,
            'isLp2m'     => $isLp2m,
            'isKaprodi'  => $isKaprodi,
            'isKeuangan' => $isKeuangan,
            'lecturerId' => $user->lecturer?->id,
        ];
    }

    private function isTeamMember(Penelitian $p, ?int $lecturerId): bool
    {
        if (!$lecturerId) return false;
        if ($p->ketua_id === $lecturerId) return true;
        return $p->members()->where('lecturer_id', $lecturerId)->exists();
    }

    private function notifyKetua(Penelitian $p, string $title, string $body, string $type = 'info'): void
    {
        if ($p->ketua?->user_id) {
            try {
                \App\Models\AppNotification::send($p->ketua->user_id, $title, $body, $type, '/penelitian/' . $p->id);
            } catch (\Throwable) {}
        }
    }

    // =========================================================
    // PERIODE HIBAH
    // =========================================================

    public function periods(): JsonResponse
    {
        return response()->json(PenelitianPeriod::orderByDesc('year')->get());
    }

    public function storePeriod(Request $request): JsonResponse
    {
        $v = $request->validate([
            'name'        => 'required|string|max:255',
            'type'        => 'required|in:penelitian,pengabdian',
            'year'        => 'required|integer|min:2000',
            'open_date'   => 'nullable|date',
            'close_date'  => 'nullable|date|after_or_equal:open_date',
            'is_active'   => 'boolean',
            'description' => 'nullable|string',
        ]);

        return response()->json(['message' => 'Periode berhasil dibuat.', 'data' => PenelitianPeriod::create($v)], 201);
    }

    public function updatePeriod(Request $request, PenelitianPeriod $period): JsonResponse
    {
        $v = $request->validate([
            'name'        => 'sometimes|string|max:255',
            'type'        => 'sometimes|in:penelitian,pengabdian',
            'year'        => 'sometimes|integer|min:2000',
            'open_date'   => 'nullable|date',
            'close_date'  => 'nullable|date',
            'is_active'   => 'boolean',
            'description' => 'nullable|string',
        ]);

        $period->update($v);
        return response()->json(['message' => 'Periode diperbarui.', 'data' => $period->fresh()]);
    }

    public function destroyPeriod(PenelitianPeriod $period): JsonResponse
    {
        $period->delete();
        return response()->json(['message' => 'Periode dihapus.']);
    }

    // =========================================================
    // STATS, INDEX, SHOW
    // =========================================================

    public function stats(): JsonResponse
    {
        ['isLp2m' => $isLp2m, 'isAdmin' => $isAdmin, 'lecturerId' => $lecturerId] = $this->getUserRole();

        $q = Penelitian::query();
        if (!$isLp2m && !$isAdmin && $lecturerId) {
            $q->where(fn($q) => $q
                ->where('ketua_id', $lecturerId)
                ->orWhereHas('members', fn($q2) => $q2->where('lecturer_id', $lecturerId)));
        }

        $statuses = ['draft','submitted','review_kaprodi','seleksi_reviewer','kontrak',
                     'pelaksanaan_1','monev','pelaksanaan_2','seminar','lpj','selesai','tidak_lolos'];
        $result = ['total' => (clone $q)->count()];
        foreach ($statuses as $s) {
            $result[$s] = (clone $q)->where('status', $s)->count();
        }

        return response()->json($result);
    }

    public function index(Request $request): JsonResponse
    {
        ['user' => $user, 'isAdmin' => $isAdmin, 'isLp2m' => $isLp2m,
         'isKaprodi' => $isKaprodi, 'lecturerId' => $lecturerId] = $this->getUserRole();

        $query = Penelitian::with(['ketua', 'period', 'studyProgram', 'fundings'])
            ->when($request->status,    fn($q) => $q->where('status', $request->status))
            ->when($request->type,      fn($q) => $q->where('type', $request->type))
            ->when($request->period_id, fn($q) => $q->where('period_id', $request->period_id))
            ->when($request->search,    fn($q) => $q->where('title', 'like', "%{$request->search}%"));

        if (!$isLp2m && !$isAdmin) {
            if ($isKaprodi && $user->lecturer?->study_program_id) {
                $spId = $user->lecturer->study_program_id;
                $query->where(fn($q) => $q
                    ->where('study_program_id', $spId)
                    ->orWhere('ketua_id', $lecturerId)
                    ->orWhereHas('members', fn($q2) => $q2->where('lecturer_id', $lecturerId)));
            } elseif ($lecturerId) {
                $query->where(fn($q) => $q
                    ->where('ketua_id', $lecturerId)
                    ->orWhereHas('members', fn($q2) => $q2->where('lecturer_id', $lecturerId))
                    ->orWhereHas('reviewers', fn($q2) => $q2->where('lecturer_id', $lecturerId)));
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        return response()->json($query->orderByDesc('created_at')->paginate($request->per_page ?? 15));
    }

    public function show(Penelitian $penelitian): JsonResponse
    {
        return response()->json($penelitian->load([
            'period', 'ketua', 'studyProgram',
            'members.lecturer', 'members.student',
            'reviewers.lecturer',
            'fundings',
        ]));
    }

    public function repository(Request $request): JsonResponse
    {
        $query = Penelitian::with(['ketua', 'studyProgram', 'period'])
            ->where('is_published', true)
            ->when($request->type,   fn($q) => $q->where('type', $request->type))
            ->when($request->search, fn($q) => $q->where('title', 'like', "%{$request->search}%")
                ->orWhere('keywords', 'like', "%{$request->search}%"));

        return response()->json($query->orderByDesc('published_at')->paginate($request->per_page ?? 12));
    }

    // =========================================================
    // STORE & UPDATE (Dosen)
    // =========================================================

    public function store(Request $request): JsonResponse
    {
        ['user' => $user, 'lecturerId' => $lecturerId] = $this->getUserRole();

        if (!$lecturerId) {
            return response()->json(['message' => 'Hanya dosen yang dapat membuat proposal.'], 403);
        }

        $v = $request->validate([
            'type'                => 'required|in:penelitian,pengabdian',
            'title'               => 'required|string|max:500',
            'period_id'           => 'nullable|exists:penelitian_periods,id',
            'abstract'            => 'nullable|string',
            'keywords'            => 'nullable|string|max:500',
            'proposal_link'       => 'nullable|string|max:1000',
            'dosen_members'       => 'nullable|array',
            'dosen_members.*'     => 'exists:lecturers,id',
            'mahasiswa_members'   => 'nullable|array',
            'mahasiswa_members.*' => 'exists:students,id',
        ]);

        $penelitian = DB::transaction(function () use ($v, $user, $lecturerId) {
            $p = Penelitian::create([
                'type'             => $v['type'],
                'title'            => $v['title'],
                'period_id'        => $v['period_id'] ?? null,
                'abstract'         => $v['abstract'] ?? null,
                'keywords'         => $v['keywords'] ?? null,
                'proposal_link'    => $v['proposal_link'] ?? null,
                'ketua_id'         => $lecturerId,
                'study_program_id' => $user->lecturer->study_program_id,
                'status'           => Penelitian::STATUS_DRAFT,
            ]);

            foreach ($v['dosen_members'] ?? [] as $lid) {
                if ($lid != $lecturerId) {
                    $p->members()->create(['member_type' => 'dosen', 'lecturer_id' => $lid]);
                }
            }
            foreach ($v['mahasiswa_members'] ?? [] as $sid) {
                $p->members()->create(['member_type' => 'mahasiswa', 'student_id' => $sid]);
            }

            return $p;
        });

        return response()->json([
            'message' => 'Proposal berhasil dibuat.',
            'data'    => $penelitian->load(['members.lecturer', 'members.student']),
        ], 201);
    }

    public function update(Request $request, Penelitian $penelitian): JsonResponse
    {
        ['lecturerId' => $lecturerId, 'isAdmin' => $isAdmin] = $this->getUserRole();

        if (!$isAdmin && !$this->isTeamMember($penelitian, $lecturerId)) {
            return response()->json(['message' => 'Bukan anggota tim.'], 403);
        }
        if (!in_array($penelitian->status, [Penelitian::STATUS_DRAFT, Penelitian::STATUS_SUBMITTED])) {
            return response()->json(['message' => 'Hanya draft yang dapat diedit.'], 422);
        }

        $v = $request->validate([
            'title'               => 'sometimes|string|max:500',
            'period_id'           => 'nullable|exists:penelitian_periods,id',
            'abstract'            => 'nullable|string',
            'keywords'            => 'nullable|string|max:500',
            'proposal_link'       => 'nullable|string|max:1000',
            'dosen_members'       => 'nullable|array',
            'dosen_members.*'     => 'exists:lecturers,id',
            'mahasiswa_members'   => 'nullable|array',
            'mahasiswa_members.*' => 'exists:students,id',
        ]);

        DB::transaction(function () use ($v, $penelitian) {
            $penelitian->update(collect($v)->except(['dosen_members', 'mahasiswa_members'])->toArray());

            if (isset($v['dosen_members'])) {
                $penelitian->members()->where('member_type', 'dosen')->delete();
                foreach ($v['dosen_members'] as $lid) {
                    if ($lid != $penelitian->ketua_id) {
                        $penelitian->members()->create(['member_type' => 'dosen', 'lecturer_id' => $lid]);
                    }
                }
            }
            if (isset($v['mahasiswa_members'])) {
                $penelitian->members()->where('member_type', 'mahasiswa')->delete();
                foreach ($v['mahasiswa_members'] as $sid) {
                    $penelitian->members()->create(['member_type' => 'mahasiswa', 'student_id' => $sid]);
                }
            }
        });

        return response()->json([
            'message' => 'Proposal diperbarui.',
            'data'    => $penelitian->fresh(['members.lecturer', 'members.student']),
        ]);
    }

    public function destroy(Penelitian $penelitian): JsonResponse
    {
        ['lecturerId' => $lecturerId, 'isAdmin' => $isAdmin] = $this->getUserRole();

        if (!$isAdmin && $penelitian->ketua_id !== $lecturerId) {
            return response()->json(['message' => 'Akses ditolak.'], 403);
        }
        if (!in_array($penelitian->status, [Penelitian::STATUS_DRAFT, Penelitian::STATUS_SUBMITTED])) {
            return response()->json(['message' => 'Hanya draft yang dapat dihapus.'], 422);
        }

        $penelitian->delete();
        return response()->json(['message' => 'Proposal dihapus.']);
    }

    // =========================================================
    // WORKFLOW
    // =========================================================

    public function submitToKaprodi(Request $request, Penelitian $penelitian): JsonResponse
    {
        ['lecturerId' => $lecturerId, 'isAdmin' => $isAdmin] = $this->getUserRole();

        if (!$isAdmin && !$this->isTeamMember($penelitian, $lecturerId)) {
            return response()->json(['message' => 'Bukan anggota tim.'], 403);
        }
        if (!in_array($penelitian->status, [Penelitian::STATUS_DRAFT, Penelitian::STATUS_SUBMITTED])) {
            return response()->json(['message' => 'Status tidak memungkinkan pengajuan.'], 422);
        }

        $request->validate(['proposal_link' => 'required|string|max:1000']);

        $penelitian->update([
            'status'        => Penelitian::STATUS_REVIEW_KAPRODI,
            'proposal_link' => $request->proposal_link,
            'submitted_at'  => now(),
        ]);

        return response()->json(['message' => 'Proposal diajukan ke Ka.Prodi.']);
    }

    public function reviewKaprodi(Request $request, Penelitian $penelitian): JsonResponse
    {
        ['isAdmin' => $isAdmin, 'isKaprodi' => $isKaprodi, 'user' => $user] = $this->getUserRole();

        if (!$isAdmin && !$isKaprodi) {
            return response()->json(['message' => 'Hanya Ka.Prodi.'], 403);
        }
        if ($penelitian->status !== Penelitian::STATUS_REVIEW_KAPRODI) {
            return response()->json(['message' => 'Status bukan review_kaprodi.'], 422);
        }

        $request->validate(['action' => 'required|in:diketahui,ditolak', 'kaprodi_note' => 'nullable|string']);

        $status = $request->action === 'diketahui' ? Penelitian::STATUS_SELEKSI_REVIEWER : Penelitian::STATUS_SUBMITTED;
        $penelitian->update([
            'status'              => $status,
            'kaprodi_note'        => $request->kaprodi_note,
            'kaprodi_reviewed_by' => $user->id,
            'kaprodi_reviewed_at' => now(),
        ]);

        return response()->json(['message' => 'Review Ka.Prodi berhasil.', 'data' => $penelitian->fresh()]);
    }

    public function assignReviewers(Request $request, Penelitian $penelitian): JsonResponse
    {
        ['isLp2m' => $isLp2m] = $this->getUserRole();
        if (!$isLp2m) return response()->json(['message' => 'Hanya LP2M.'], 403);

        $request->validate([
            'reviewer_ids'   => 'required|array|min:1|max:3',
            'reviewer_ids.*' => 'exists:lecturers,id',
            'stage'          => 'nullable|in:seleksi,monev',
        ]);

        $stage = $request->stage ?? 'seleksi';
        foreach ($request->reviewer_ids as $lid) {
            PenelitianReviewer::updateOrCreate(
                ['penelitian_id' => $penelitian->id, 'lecturer_id' => $lid, 'stage' => $stage],
                ['assigned_by' => auth()->id()]
            );
        }

        return response()->json(['message' => 'Reviewer ditugaskan.']);
    }

    public function submitReview(Request $request, Penelitian $penelitian): JsonResponse
    {
        ['lecturerId' => $lecturerId] = $this->getUserRole();
        $stage    = $request->stage ?? 'seleksi';
        $reviewer = PenelitianReviewer::where('penelitian_id', $penelitian->id)
            ->where('lecturer_id', $lecturerId)->where('stage', $stage)->first();

        if (!$reviewer) return response()->json(['message' => 'Anda tidak ditugaskan sebagai reviewer.'], 403);

        $request->validate([
            'score_orisinalitas' => 'required|integer|min:0|max:25',
            'score_metodologi'   => 'required|integer|min:0|max:25',
            'score_manfaat'      => 'required|integer|min:0|max:25',
            'score_kelayakan'    => 'required|integer|min:0|max:25',
            'catatan'            => 'nullable|string',
            'rekomendasi'        => 'required|in:lolos,tidak_lolos,revisi',
        ]);

        $total = $request->score_orisinalitas + $request->score_metodologi
               + $request->score_manfaat + $request->score_kelayakan;

        $reviewer->update([
            'score_orisinalitas' => $request->score_orisinalitas,
            'score_metodologi'   => $request->score_metodologi,
            'score_manfaat'      => $request->score_manfaat,
            'score_kelayakan'    => $request->score_kelayakan,
            'score_total'        => $total,
            'catatan'            => $request->catatan,
            'rekomendasi'        => $request->rekomendasi,
            'reviewed_at'        => now(),
        ]);

        return response()->json(['message' => 'Review disimpan.', 'data' => $reviewer->fresh()]);
    }

    public function setSeleksiResult(Request $request, Penelitian $penelitian): JsonResponse
    {
        ['isLp2m' => $isLp2m, 'user' => $user] = $this->getUserRole();
        if (!$isLp2m) return response()->json(['message' => 'Hanya LP2M.'], 403);

        $request->validate(['result' => 'required|in:lolos,tidak_lolos', 'lp2m_note' => 'nullable|string']);

        $status = $request->result === 'lolos' ? Penelitian::STATUS_KONTRAK : Penelitian::STATUS_TIDAK_LOLOS;
        $penelitian->update([
            'status'           => $status,
            'lp2m_note'        => $request->lp2m_note,
            'lp2m_reviewed_by' => $user->id,
            'lp2m_reviewed_at' => now(),
        ]);

        return response()->json(['message' => 'Hasil seleksi ditetapkan.', 'data' => $penelitian->fresh()]);
    }

    public function saveKontrak(Request $request, Penelitian $penelitian): JsonResponse
    {
        ['isLp2m' => $isLp2m] = $this->getUserRole();
        if (!$isLp2m) return response()->json(['message' => 'Hanya LP2M.'], 403);

        $request->validate([
            'contract_number' => 'required|string|max:255',
            'total_dana'      => 'required|numeric|min:0',
            'contract_link'   => 'nullable|string|max:1000',
            'contract_date'   => 'nullable|date',
        ]);

        $penelitian->update([
            'contract_number' => $request->contract_number,
            'total_dana'      => $request->total_dana,
            'contract_link'   => $request->contract_link,
            'contract_date'   => $request->contract_date ?? today(),
            'status'          => Penelitian::STATUS_PELAKSANAAN_1,
        ]);

        return response()->json(['message' => 'Kontrak disimpan.', 'data' => $penelitian->fresh()]);
    }

    public function uploadLaporanKemajuan(Request $request, Penelitian $penelitian): JsonResponse
    {
        ['lecturerId' => $lecturerId, 'isAdmin' => $isAdmin] = $this->getUserRole();
        if (!$isAdmin && !$this->isTeamMember($penelitian, $lecturerId))
            return response()->json(['message' => 'Bukan anggota tim.'], 403);

        $request->validate(['laporan_kemajuan_link' => 'required|string|max:1000']);
        $penelitian->update(['laporan_kemajuan_link' => $request->laporan_kemajuan_link, 'status' => Penelitian::STATUS_MONEV]);

        return response()->json(['message' => 'Laporan kemajuan diupload.']);
    }

    public function uploadRevisiKemajuan(Request $request, Penelitian $penelitian): JsonResponse
    {
        ['lecturerId' => $lecturerId, 'isAdmin' => $isAdmin] = $this->getUserRole();
        if (!$isAdmin && !$this->isTeamMember($penelitian, $lecturerId))
            return response()->json(['message' => 'Bukan anggota tim.'], 403);

        $request->validate(['laporan_kemajuan_revision_link' => 'required|string|max:1000']);
        $penelitian->update(['laporan_kemajuan_revision_link' => $request->laporan_kemajuan_revision_link, 'status' => Penelitian::STATUS_MONEV]);

        return response()->json(['message' => 'Revisi laporan kemajuan diupload.']);
    }

    public function setMonevResult(Request $request, Penelitian $penelitian): JsonResponse
    {
        ['isLp2m' => $isLp2m] = $this->getUserRole();
        if (!$isLp2m) return response()->json(['message' => 'Hanya LP2M.'], 403);

        $request->validate(['result' => 'required|in:lanjut,revisi', 'lp2m_note' => 'nullable|string']);
        $status = $request->result === 'lanjut' ? Penelitian::STATUS_PELAKSANAAN_2 : Penelitian::STATUS_REVISI_KEMAJUAN;
        $penelitian->update(['status' => $status, 'lp2m_note' => $request->lp2m_note]);

        return response()->json(['message' => 'Hasil monev disimpan.', 'data' => $penelitian->fresh()]);
    }

    public function uploadLaporanAkhir(Request $request, Penelitian $penelitian): JsonResponse
    {
        ['lecturerId' => $lecturerId, 'isAdmin' => $isAdmin] = $this->getUserRole();
        if (!$isAdmin && !$this->isTeamMember($penelitian, $lecturerId))
            return response()->json(['message' => 'Bukan anggota tim.'], 403);

        $request->validate(['laporan_akhir_link' => 'required|string|max:1000', 'paper_link' => 'nullable|string|max:1000']);
        $penelitian->update(['laporan_akhir_link' => $request->laporan_akhir_link, 'paper_link' => $request->paper_link, 'status' => Penelitian::STATUS_SEMINAR]);

        return response()->json(['message' => 'Laporan akhir diupload.']);
    }

    public function setSeminarResult(Request $request, Penelitian $penelitian): JsonResponse
    {
        ['isLp2m' => $isLp2m] = $this->getUserRole();
        if (!$isLp2m) return response()->json(['message' => 'Hanya LP2M.'], 403);

        $request->validate(['result' => 'required|in:diterima,revisi', 'lp2m_note' => 'nullable|string']);
        $status = $request->result === 'diterima' ? Penelitian::STATUS_LPJ : Penelitian::STATUS_REVISI_SEMINAR;
        $penelitian->update(['status' => $status, 'lp2m_note' => $request->lp2m_note]);

        return response()->json(['message' => 'Hasil seminar disimpan.', 'data' => $penelitian->fresh()]);
    }

    public function setSeminarDate(Request $request, Penelitian $penelitian): JsonResponse
    {
        ['isLp2m' => $isLp2m] = $this->getUserRole();
        if (!$isLp2m) return response()->json(['message' => 'Hanya LP2M.'], 403);

        $request->validate(['seminar_date' => 'required|date']);
        $penelitian->update(['seminar_date' => $request->seminar_date]);

        return response()->json(['message' => 'Jadwal seminar disimpan.']);
    }

    public function uploadLaporanFinal(Request $request, Penelitian $penelitian): JsonResponse
    {
        ['lecturerId' => $lecturerId, 'isAdmin' => $isAdmin] = $this->getUserRole();
        if (!$isAdmin && !$this->isTeamMember($penelitian, $lecturerId))
            return response()->json(['message' => 'Bukan anggota tim.'], 403);

        $request->validate(['laporan_final' => 'required|file|mimes:pdf|max:20480']);
        $path = $request->file('laporan_final')->store('penelitian-final', 'public');
        $penelitian->update(['laporan_final_path' => $path, 'status' => Penelitian::STATUS_LPJ]);

        return response()->json(['message' => 'Laporan final diupload.']);
    }

    public function uploadLpj(Request $request, Penelitian $penelitian): JsonResponse
    {
        ['lecturerId' => $lecturerId, 'isAdmin' => $isAdmin] = $this->getUserRole();
        if (!$isAdmin && !$this->isTeamMember($penelitian, $lecturerId))
            return response()->json(['message' => 'Bukan anggota tim.'], 403);

        $request->validate(['lpj_link' => 'required|string|max:1000']);
        $penelitian->update(['lpj_link' => $request->lpj_link, 'status' => Penelitian::STATUS_LPJ]);

        return response()->json(['message' => 'LPJ diupload.']);
    }

    public function uploadRevisiLpj(Request $request, Penelitian $penelitian): JsonResponse
    {
        ['lecturerId' => $lecturerId, 'isAdmin' => $isAdmin] = $this->getUserRole();
        if (!$isAdmin && !$this->isTeamMember($penelitian, $lecturerId))
            return response()->json(['message' => 'Bukan anggota tim.'], 403);

        $request->validate(['lpj_revision_link' => 'required|string|max:1000']);
        $penelitian->update(['lpj_revision_link' => $request->lpj_revision_link, 'status' => Penelitian::STATUS_LPJ]);

        return response()->json(['message' => 'Revisi LPJ diupload.']);
    }

    public function reviewLpj(Request $request, Penelitian $penelitian): JsonResponse
    {
        ['isLp2m' => $isLp2m] = $this->getUserRole();
        if (!$isLp2m) return response()->json(['message' => 'Hanya LP2M.'], 403);

        $request->validate(['action' => 'required|in:terima,revisi', 'lp2m_note' => 'nullable|string']);
        $status = $request->action === 'terima' ? Penelitian::STATUS_SELESAI : Penelitian::STATUS_REVISI_LPJ;
        $penelitian->update(['status' => $status, 'lp2m_note' => $request->lp2m_note]);

        return response()->json(['message' => 'Review LPJ berhasil.', 'data' => $penelitian->fresh()]);
    }

    public function uploadRevisiProposal(Request $request, Penelitian $penelitian): JsonResponse
    {
        ['lecturerId' => $lecturerId, 'isAdmin' => $isAdmin] = $this->getUserRole();
        if (!$isAdmin && !$this->isTeamMember($penelitian, $lecturerId))
            return response()->json(['message' => 'Bukan anggota tim.'], 403);

        $request->validate(['proposal_revision_link' => 'required|string|max:1000']);
        $penelitian->update(['proposal_revision_link' => $request->proposal_revision_link]);

        return response()->json(['message' => 'Link revisi proposal disimpan.']);
    }

    public function allocateFunding(Request $request, Penelitian $penelitian): JsonResponse
    {
        ['isKeuangan' => $isKeuangan] = $this->getUserRole();
        if (!$isKeuangan) return response()->json(['message' => 'Hanya bagian Keuangan.'], 403);

        $request->validate([
            'stage'      => 'required|in:1,2,3',
            'amount'     => 'required|numeric|min:0',
            'keterangan' => 'nullable|string',
        ]);

        PenelitianFunding::updateOrCreate(
            ['penelitian_id' => $penelitian->id, 'stage' => $request->stage],
            [
                'amount'       => $request->amount,
                'keterangan'   => $request->keterangan,
                'status'       => 'alokasi',
                'allocated_by' => auth()->id(),
                'allocated_at' => now(),
            ]
        );

        return response()->json(['message' => "Dana Tahap {$request->stage} dialokasikan."]);
    }

    public function disburseFunding(Request $request, Penelitian $penelitian, int $stage): JsonResponse
    {
        ['isKeuangan' => $isKeuangan] = $this->getUserRole();
        if (!$isKeuangan) return response()->json(['message' => 'Hanya bagian Keuangan.'], 403);

        $funding = PenelitianFunding::where('penelitian_id', $penelitian->id)
            ->where('stage', $stage)->firstOrFail();

        if ($funding->status === 'cair')
            return response()->json(['message' => 'Dana sudah dicairkan.'], 422);

        $funding->update([
            'status'        => 'cair',
            'disbursed_by'  => auth()->id(),
            'disbursed_at'  => now(),
        ]);

        $this->notifyKetua($penelitian, "Dana Tahap {$stage} Dicairkan",
            "Dana penelitian tahap {$stage} telah dicairkan.", 'success');

        return response()->json(['message' => "Dana Tahap {$stage} dicairkan."]);
    }

    public function publish(Request $request, Penelitian $penelitian): JsonResponse
    {
        ['isLp2m' => $isLp2m] = $this->getUserRole();
        if (!$isLp2m) return response()->json(['message' => 'Hanya LP2M.'], 403);

        if ($penelitian->status !== Penelitian::STATUS_SELESAI)
            return response()->json(['message' => 'Hanya yang berstatus selesai.'], 422);

        $penelitian->update([
            'is_published'   => true,
            'published_at'   => now(),
            'published_by'   => auth()->id(),
            'repository_url' => $request->repository_url,
        ]);

        $this->notifyKetua($penelitian, 'Penelitian Dipublikasikan',
            "Penelitian \"{$penelitian->title}\" telah dipublikasikan ke repository.", 'success');

        return response()->json(['message' => 'Penelitian dipublikasikan.']);
    }
}
