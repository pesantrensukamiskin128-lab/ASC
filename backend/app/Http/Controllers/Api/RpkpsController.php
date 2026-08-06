<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Rpkps;
use App\Models\RpkpsApproval;
use App\Models\RpkpsCpmk;
use App\Models\RpkpsWeeklyPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RpkpsController extends Controller
{
    /** List RPKPS (dengan filter) */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $isSuperAdmin = $user->hasRole('SUPER_ADMIN') || $user->hasRole('ADMIN_AKADEMIK');
        $isMahasiswa  = $user->hasRole('MAHASISWA');

        $data = Rpkps::with(['course', 'lecturer', 'curriculum', 'academicYear'])
            ->when($request->search, fn($q) => $q->whereHas('course', fn($c) =>
                $c->where('name', 'like', "%{$request->search}%")
                  ->orWhere('code', 'like', "%{$request->search}%")))
            ->when($request->course_id, fn($q) => $q->where('course_id', $request->course_id))
            ->when($request->lecturer_id, fn($q) => $q->where('lecturer_id', $request->lecturer_id))
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->academic_year_id, fn($q) => $q->where('academic_year_id', $request->academic_year_id))
            // Mahasiswa: hanya lihat RPS yang sudah disetujui/dikunci
            ->when($isMahasiswa, fn($q) => $q->whereIn('status', ['DISETUJUI', 'DIKUNCI']))
            // Dosen/admin: filter berdasarkan role
            ->when(!$isSuperAdmin && !$isMahasiswa, function ($q) use ($user) {
                $lecturer = \App\Models\Lecturer::where('user_id', $user->id)->first();
                if (!$lecturer) {
                    $q->whereRaw('1 = 0');
                    return;
                }

                // Cek apakah Kaprodi/Sekprodi → bisa lihat semua RPS di prodinya
                $isKaprodi = \App\Models\LecturerPosition::where('lecturer_id', $lecturer->id)
                    ->where('is_active', true)
                    ->whereIn('position_code', ['KAPRODI', 'SEKPRODI'])
                    ->exists();

                if ($isKaprodi) {
                    $prodiId = $lecturer->study_program_id;
                    $q->whereHas('course', fn($cq) => $cq->where('study_program_id', $prodiId));
                } else {
                    $q->where('lecturer_id', $lecturer->id);
                }
            })
            ->orderByDesc('created_at')
            ->paginate($request->per_page ?? 15);

        return response()->json($data);
    }

    /** Mata kuliah yang bisa dibuat RPS oleh dosen (yang ditugaskan via kelas) */
    public function myAssignedCourses(Request $request): JsonResponse
    {
        $user = $request->user();
        $lecturer = \App\Models\Lecturer::where('user_id', $user->id)->first();

        if (!$lecturer) {
            return response()->json([]);
        }

        $courses = \App\Models\ClassModel::where('lecturer_id', $lecturer->id)
            ->where('is_active', true)
            ->with('course:id,code,name,credits')
            ->get()
            ->pluck('course')
            ->unique('id')
            ->values();

        return response()->json([
            'lecturer_id' => $lecturer->id,
            'courses' => $courses,
        ]);
    }

    /** Buat RPKPS baru */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'course_id'        => 'required|exists:courses,id',
            'curriculum_id'    => 'required|exists:curriculums,id',
            'academic_year_id' => 'required|exists:academic_years,id',
            'semester_id'      => 'nullable|exists:semesters,id',
            'lecturer_id'      => 'required|exists:lecturers,id',
            'coordinator_id'   => 'nullable|exists:lecturers,id',
            'course_description' => 'nullable|string',
            'course_urgency'     => 'nullable|string',
            'course_scope'       => 'nullable|string',
            'course_position'    => 'nullable|string',
            'prerequisites'      => 'nullable|string',
        ]);

        // Validasi: Dosen hanya bisa buat RPS untuk MK yang diampu
        $user = $request->user();
        $isSuperAdmin = $user->hasRole('SUPER_ADMIN') || $user->hasRole('ADMIN_AKADEMIK');

        if (!$isSuperAdmin) {
            $lecturer = \App\Models\Lecturer::where('user_id', $user->id)->first();
            if (!$lecturer || $lecturer->id != $validated['lecturer_id']) {
                return response()->json(['message' => 'Anda hanya bisa membuat RPKPS untuk diri sendiri.'], 403);
            }

            // Cek apakah dosen ditugaskan mengampu MK ini (via tabel classes)
            $isAssigned = \App\Models\ClassModel::where('lecturer_id', $lecturer->id)
                ->where('course_id', $validated['course_id'])
                ->where('is_active', true)
                ->exists();

            if (!$isAssigned) {
                return response()->json([
                    'message' => 'Anda belum ditugaskan mengampu mata kuliah ini. Hubungi Kaprodi untuk penugasan.',
                ], 403);
            }
        }

        $validated['code'] = Rpkps::generateCode($validated['course_id']);

        // Auto-fill semester aktif jika tidak diisi
        if (empty($validated['semester_id'])) {
            $activeSemester = \App\Models\Semester::where('is_active', true)->first();
            if ($activeSemester) {
                $validated['semester_id'] = $activeSemester->id;
            }
        }

        $rpkps = Rpkps::create($validated);

        // Otomatis sync CPL dari pemetaan kurikulum (cpl_course_mappings)
        $cplIds = DB::table('cpl_course_mappings')
            ->where('course_id', $validated['course_id'])
            ->whereIn('learning_outcome_id', function ($q) use ($validated) {
                $q->select('id')->from('learning_outcomes')
                    ->where('curriculum_id', $validated['curriculum_id']);
            })
            ->pluck('learning_outcome_id')
            ->toArray();

        if ($cplIds) {
            $rpkps->cpls()->sync($cplIds);
        }

        return response()->json(['message' => 'RPKPS berhasil dibuat.', 'data' => $rpkps->load(['course', 'lecturer', 'cpls'])], 201);
    }

    /** Detail RPKPS lengkap */
    public function show(Rpkps $rpkp): JsonResponse
    {
        return response()->json($rpkp->load([
            'course.studyProgram.faculty',
            'curriculum', 'academicYear', 'semester',
            'lecturer', 'coordinator', 'approvedByUser',
            'cpls', 'cpmks.subCpmks', 'cpmks.cpls',
            'learningMaterials', 'weeklyPlans',
            'assessments.rubrics', 'rubrics',
            'references', 'approvals.user',
        ]));
    }

    /** Update data deskripsi RPKPS */
    public function update(Request $request, Rpkps $rpkp): JsonResponse
    {
        if (in_array($rpkp->status, ['DIKUNCI', 'DIARSIPKAN'])) {
            return response()->json(['message' => 'RPKPS sudah dikunci, tidak bisa diubah.'], 422);
        }

        $validated = $request->validate([
            'course_description' => 'nullable|string',
            'course_urgency'     => 'nullable|string',
            'course_scope'       => 'nullable|string',
            'course_position'    => 'nullable|string',
            'prerequisites'      => 'nullable|string',
            'coordinator_id'     => 'nullable|exists:lecturers,id',
        ]);

        $rpkp->update($validated);
        return response()->json(['message' => 'RPKPS berhasil diupdate.', 'data' => $rpkp->fresh()]);
    }

    /** Sync CPL yang dipilih */
    public function syncCpls(Request $request, Rpkps $rpkp): JsonResponse
    {
        $request->validate(['cpl_ids' => 'required|array', 'cpl_ids.*' => 'exists:learning_outcomes,id']);
        $rpkp->cpls()->sync($request->cpl_ids);
        return response()->json(['message' => 'CPL berhasil disinkronkan.']);
    }

    /** CRUD CPMK */
    public function storeCpmk(Request $request, Rpkps $rpkp): JsonResponse
    {
        $validated = $request->validate([
            'code'        => 'required|string|max:20',
            'description' => 'required|string',
            'cpl_ids'     => 'nullable|array',
            'cpl_ids.*'   => 'exists:learning_outcomes,id',
            'sub_cpmks'   => 'nullable|array',
            'sub_cpmks.*.code'        => 'required|string|max:20',
            'sub_cpmks.*.description' => 'required|string',
        ]);

        $cpmk = $rpkp->cpmks()->create([
            'code'        => $validated['code'],
            'description' => $validated['description'],
            'order'       => $rpkp->cpmks()->count() + 1,
        ]);

        if (!empty($validated['cpl_ids'])) {
            $cpmk->cpls()->sync($validated['cpl_ids']);
        }

        if (!empty($validated['sub_cpmks'])) {
            foreach ($validated['sub_cpmks'] as $i => $sub) {
                $cpmk->subCpmks()->create(['code' => $sub['code'], 'description' => $sub['description'], 'order' => $i + 1]);
            }
        }

        return response()->json(['message' => 'CPMK berhasil ditambahkan.', 'data' => $cpmk->load(['subCpmks', 'cpls'])], 201);
    }

    public function updateCpmk(Request $request, Rpkps $rpkp, RpkpsCpmk $cpmk): JsonResponse
    {
        $validated = $request->validate([
            'code'        => 'sometimes|string|max:20',
            'description' => 'sometimes|string',
            'cpl_ids'     => 'nullable|array',
        ]);

        $cpmk->update(collect($validated)->except('cpl_ids')->toArray());
        if (isset($validated['cpl_ids'])) $cpmk->cpls()->sync($validated['cpl_ids']);

        return response()->json(['message' => 'CPMK berhasil diupdate.', 'data' => $cpmk->fresh(['subCpmks', 'cpls'])]);
    }

    public function destroyCpmk(Rpkps $rpkp, RpkpsCpmk $cpmk): JsonResponse
    {
        $cpmk->delete();
        return response()->json(['message' => 'CPMK berhasil dihapus.']);
    }

    /** Batch save weekly plans (16 minggu sekaligus) */
    public function saveWeeklyPlans(Request $request, Rpkps $rpkp): JsonResponse
    {
        $validated = $request->validate([
            'plans'                      => 'required|array',
            'plans.*.week_number'        => 'required|integer|min:1|max:16',
            'plans.*.sub_cpmk'           => 'nullable|string',
            'plans.*.indicators'         => 'nullable|string',
            'plans.*.learning_material'  => 'nullable|string',
            'plans.*.methods'            => 'nullable|array',
            'plans.*.lecturer_activity'  => 'nullable|string',
            'plans.*.student_activity'   => 'nullable|string',
            'plans.*.assessment_form'    => 'nullable|string',
            'plans.*.assessment_criteria'=> 'nullable|string',
            'plans.*.media'             => 'nullable|string',
            'plans.*.duration'          => 'nullable|string',
            'plans.*.weight'            => 'nullable|integer|min:0',
        ]);

        DB::transaction(function () use ($rpkp, $validated) {
            foreach ($validated['plans'] as $plan) {
                RpkpsWeeklyPlan::updateOrCreate(
                    ['rpkps_id' => $rpkp->id, 'week_number' => $plan['week_number']],
                    $plan
                );
            }
        });

        return response()->json(['message' => 'Rencana mingguan berhasil disimpan.']);
    }

    /** Save assessments (komponen penilaian) */
    public function saveAssessments(Request $request, Rpkps $rpkp): JsonResponse
    {
        $validated = $request->validate([
            'assessments'              => 'required|array',
            'assessments.*.name'       => 'required|string',
            'assessments.*.weight'     => 'required|integer|min:0|max:100',
            'assessments.*.description'=> 'nullable|string',
        ]);

        // Validasi total bobot = 100
        $total = collect($validated['assessments'])->sum('weight');
        if ($total !== 100) {
            return response()->json(['message' => "Total bobot harus 100%. Saat ini: {$total}%."], 422);
        }

        DB::transaction(function () use ($rpkp, $validated) {
            $rpkp->assessments()->delete();
            foreach ($validated['assessments'] as $i => $a) {
                $rpkp->assessments()->create(array_merge($a, ['order' => $i + 1]));
            }
        });

        return response()->json(['message' => 'Komponen asesmen berhasil disimpan.']);
    }

    /** Save references */
    public function saveReferences(Request $request, Rpkps $rpkp): JsonResponse
    {
        $validated = $request->validate([
            'references'             => 'required|array',
            'references.*.type'      => 'required|in:Utama,Pendukung',
            'references.*.category'  => 'required|string',
            'references.*.title'     => 'required|string',
            'references.*.author'    => 'nullable|string',
            'references.*.year'      => 'nullable|string',
            'references.*.publisher' => 'nullable|string',
            'references.*.isbn_doi'  => 'nullable|string',
            'references.*.url'       => 'nullable|string',
        ]);

        DB::transaction(function () use ($rpkp, $validated) {
            $rpkp->references()->delete();
            foreach ($validated['references'] as $i => $r) {
                $rpkp->references()->create(array_merge($r, ['order' => $i + 1]));
            }
        });

        return response()->json(['message' => 'Referensi berhasil disimpan.']);
    }

    // === WORKFLOW ===

    /** Ajukan RPKPS (DRAFT → DIAJUKAN) */
    public function submit(Rpkps $rpkp): JsonResponse
    {
        if ($rpkp->status !== 'DRAFT' && $rpkp->status !== 'REVISI') {
            return response()->json(['message' => 'RPKPS tidak dalam status yang bisa diajukan.'], 422);
        }
        $rpkp->update(['status' => 'DIAJUKAN']);
        $rpkp->approvals()->create(['user_id' => auth()->id(), 'action' => 'DIAJUKAN']);

        // Kirim notifikasi ke Kaprodi
        $rpkp->loadMissing(['course', 'lecturer']);
        $prodiId = $rpkp->course?->study_program_id;
        if ($prodiId) {
            // Cari Kaprodi prodi ini
            $kaprodiPosition = \App\Models\LecturerPosition::where('position_code', 'KAPRODI')
                ->where('scope_type', 'study_program')
                ->where('scope_id', $prodiId)
                ->where('is_active', true)
                ->first();

            if ($kaprodiPosition) {
                $kaprodiLecturer = \App\Models\Lecturer::find($kaprodiPosition->lecturer_id);
                if ($kaprodiLecturer?->user_id) {
                    \App\Models\AppNotification::send(
                        $kaprodiLecturer->user_id,
                        'Pengajuan RPKPS Baru',
                        "Dosen {$rpkp->lecturer?->full_name} mengajukan RPKPS untuk MK {$rpkp->course?->name}. Mohon direview.",
                        'warning',
                        '/rps/' . $rpkp->id
                    );
                }
            }
        }

        return response()->json(['message' => 'RPKPS berhasil diajukan untuk validasi.']);
    }

    /** Kaprodi: Setujui */
    public function approve(Request $request, Rpkps $rpkp): JsonResponse
    {
        $rpkp->update([
            'status'      => 'DISETUJUI',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);
        $rpkp->approvals()->create(['user_id' => auth()->id(), 'action' => 'DISETUJUI', 'note' => $request->note]);

        // Notifikasi ke dosen pembuat RPS
        $rpkp->loadMissing(['course', 'lecturer']);
        if ($rpkp->lecturer?->user_id) {
            \App\Models\AppNotification::send(
                $rpkp->lecturer->user_id,
                'RPKPS Disetujui ✓',
                "RPKPS untuk MK {$rpkp->course?->name} telah disetujui oleh Kaprodi.",
                'success',
                '/rps/' . $rpkp->id
            );
        }

        return response()->json(['message' => 'RPKPS berhasil disetujui.']);
    }

    /** Kaprodi: Minta revisi */
    public function revise(Request $request, Rpkps $rpkp): JsonResponse
    {
        $request->validate(['note' => 'required|string']);
        $rpkp->update(['status' => 'REVISI', 'revision_note' => $request->note]);
        $rpkp->approvals()->create(['user_id' => auth()->id(), 'action' => 'REVISI', 'note' => $request->note]);

        // Notifikasi ke dosen pembuat RPS
        $rpkp->loadMissing(['course', 'lecturer']);
        if ($rpkp->lecturer?->user_id) {
            \App\Models\AppNotification::send(
                $rpkp->lecturer->user_id,
                'RPKPS Perlu Revisi',
                "RPKPS untuk MK {$rpkp->course?->name} dikembalikan untuk revisi. Catatan: \"{$request->note}\"",
                'warning',
                '/rps/' . $rpkp->id
            );
        }

        return response()->json(['message' => 'RPKPS dikembalikan untuk revisi.']);
    }

    /** Kunci RPKPS (setelah disetujui) */
    public function lock(Rpkps $rpkp): JsonResponse
    {
        if ($rpkp->status !== 'DISETUJUI') {
            return response()->json(['message' => 'Hanya RPKPS yang disetujui yang bisa dikunci.'], 422);
        }
        $rpkp->update(['status' => 'DIKUNCI']);
        $rpkp->approvals()->create(['user_id' => auth()->id(), 'action' => 'DIKUNCI']);
        return response()->json(['message' => 'RPKPS berhasil dikunci.']);
    }

    /** Duplikasi ke semester baru */
    public function duplicate(Request $request, Rpkps $rpkp): JsonResponse
    {
        $request->validate([
            'academic_year_id' => 'required|exists:academic_years,id',
            'semester_id'      => 'nullable|exists:semesters,id',
        ]);

        $new = $rpkp->duplicate($request->academic_year_id, $request->semester_id);

        return response()->json(['message' => 'RPKPS berhasil diduplikasi.', 'data' => $new->load(['course', 'lecturer'])], 201);
    }

    /** Statistik RPKPS (untuk dashboard) */
    public function statistics(Request $request): JsonResponse
    {
        $query = Rpkps::query()
            ->when($request->academic_year_id, fn($q) => $q->where('academic_year_id', $request->academic_year_id));

        $stats = [
            'total'             => (clone $query)->count(),
            'draft'             => (clone $query)->where('status', 'DRAFT')->count(),
            'diajukan'          => (clone $query)->where('status', 'DIAJUKAN')->count(),
            'dalam_pemeriksaan' => (clone $query)->where('status', 'DALAM_PEMERIKSAAN')->count(),
            'revisi'            => (clone $query)->where('status', 'REVISI')->count(),
            'disetujui'         => (clone $query)->where('status', 'DISETUJUI')->count(),
            'dikunci'           => (clone $query)->where('status', 'DIKUNCI')->count(),
        ];

        return response()->json($stats);
    }

    /** Hapus RPKPS (hanya DRAFT) */
    public function destroy(Rpkps $rpkp): JsonResponse
    {
        if ($rpkp->status !== 'DRAFT') {
            return response()->json(['message' => 'Hanya RPKPS berstatus DRAFT yang bisa dihapus.'], 422);
        }
        $rpkp->delete();
        return response()->json(['message' => 'RPKPS berhasil dihapus.']);
    }

    /** Download PDF RPKPS dengan format standar dan QR code */
    public function downloadPdf(Rpkps $rpkp)
    {
        $rpkp->load([
            'course.studyProgram.faculty',
            'curriculum', 'academicYear', 'semester',
            'lecturer', 'coordinator',
            'cpls', 'cpmks.subCpmks',
            'learningMaterials', 'weeklyPlans',
            'assessments', 'references',
        ]);

        $institution = \App\Models\Institution::first();
        $logoPath = $institution?->logo_path
            ? storage_path('app/public/' . $institution->logo_path)
            : null;

        // Cari Kaprodi
        $prodiId = $rpkp->course?->study_program_id;
        $kaprodi = null;
        if ($prodiId) {
            $kaprodiPosition = \App\Models\LecturerPosition::where('position_code', 'KAPRODI')
                ->where('scope_type', 'study_program')
                ->where('scope_id', $prodiId)
                ->where('is_active', true)
                ->first();
            if ($kaprodiPosition) {
                $kaprodi = \App\Models\Lecturer::find($kaprodiPosition->lecturer_id);
            }
        }

        // QR Code data — URL verifikasi frontend (ukuran kecil untuk tanda tangan)
        $verifyUrl = rtrim(config('app.frontend_url'), '/') . "/verify/rpkps/{$rpkp->verification_code}";
        $qrDosenUrl = "https://api.qrserver.com/v1/create-qr-code/?size=60x60&data=" . urlencode($verifyUrl . '?signer=dosen');
        $qrKaprodiUrl = "https://api.qrserver.com/v1/create-qr-code/?size=60x60&data=" . urlencode($verifyUrl . '?signer=kaprodi');

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.rpkps', [
            'rpkps' => $rpkp,
            'institution' => $institution,
            'logoPath' => $logoPath,
            'kaprodi' => $kaprodi,
            'qrDosenUrl' => $qrDosenUrl,
            'qrKaprodiUrl' => $qrKaprodiUrl,
            'verifyUrl' => $verifyUrl,
        ])->setPaper('a4', 'landscape')
          ->setOption('isRemoteEnabled', true);

        $filename = "RPKPS-{$rpkp->course?->code}-{$rpkp->code}.pdf";
        return $pdf->download($filename);
    }
}
