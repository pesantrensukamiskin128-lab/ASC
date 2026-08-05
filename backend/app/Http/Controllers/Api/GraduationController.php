<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GraduationDocument;
use App\Models\GraduationPeriod;
use App\Models\GraduationRegistration;
use App\Models\GraduationVerification;
use App\Models\Invoice;
use App\Models\Thesis;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GraduationController extends Controller
{
    // === PERIODS ===
    public function periods(Request $request): JsonResponse
    {
        return response()->json(
            GraduationPeriod::with('academicYear')->withCount('registrations')->orderByDesc('graduation_date')->paginate($request->per_page ?? 15)
        );
    }

    public function storePeriod(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255', 'academic_year_id' => 'required|exists:academic_years,id',
            'registration_start' => 'required|date', 'registration_end' => 'required|date',
            'graduation_date' => 'required|date', 'venue' => 'nullable|string|max:255',
            'description' => 'nullable|string', 'is_active' => 'boolean',
        ]);
        $period = GraduationPeriod::create($validated);
        return response()->json(['message' => 'Periode wisuda berhasil dibuat.', 'data' => $period], 201);
    }

    public function updatePeriod(Request $request, GraduationPeriod $period): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255', 'registration_start' => 'nullable|date',
            'registration_end' => 'nullable|date', 'graduation_date' => 'nullable|date',
            'venue' => 'nullable|string|max:255', 'description' => 'nullable|string', 'is_active' => 'boolean',
        ]);
        $period->update($validated);
        return response()->json(['message' => 'Periode wisuda diupdate.', 'data' => $period->fresh()]);
    }

    public function destroyPeriod(GraduationPeriod $period): JsonResponse
    {
        if ($period->registrations()->count() > 0) return response()->json(['message' => 'Periode sudah memiliki pendaftar.'], 422);
        $period->delete();
        return response()->json(['message' => 'Periode wisuda dihapus.']);
    }

    // === REGISTRATIONS ===
    public function registrations(Request $request): JsonResponse
    {
        $user = auth()->user();
        $query = GraduationRegistration::with(['period', 'student.studyProgram', 'verifications'])
            ->when($request->period_id, fn($q) => $q->where('period_id', $request->period_id))
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->search, fn($q) => $q->whereHas('student', fn($q2) =>
                $q2->where('name', 'like', "%{$request->search}%")->orWhere('nim', 'like', "%{$request->search}%")));

        if ($user->hasRole('MAHASISWA') && $user->student) {
            $query->where('student_id', $user->student->id);
        }

        return response()->json($query->orderByDesc('created_at')->paginate($request->per_page ?? 15));
    }

    public function showRegistration(GraduationRegistration $registration): JsonResponse
    {
        return response()->json($registration->load(['period', 'student.studyProgram', 'verifications.verifier', 'documents']));
    }

    /** Mahasiswa mendaftar wisuda */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'period_id'       => 'required|exists:graduation_periods,id',
            'toga_size'       => 'nullable|string|max:10',
            'phone'           => 'nullable|string|max:20',
            'address_current' => 'nullable|string',
        ]);

        $user = auth()->user();
        $student = $user->student;
        if (!$student) return response()->json(['message' => 'Bukan mahasiswa.'], 403);

        // Cek sudah terdaftar
        if (GraduationRegistration::where('period_id', $validated['period_id'])->where('student_id', $student->id)->exists()) {
            return response()->json(['message' => 'Sudah terdaftar di periode ini.'], 422);
        }

        // Ambil data akademik
        $thesis = Thesis::where('student_id', $student->id)->where('status', 'LULUS')->first();

        $reg = DB::transaction(function () use ($validated, $student, $thesis) {
            $reg = GraduationRegistration::create([
                'period_id'     => $validated['period_id'],
                'student_id'    => $student->id,
                'status'        => 'SUBMITTED',
                'toga_size'     => $validated['toga_size'] ?? null,
                'phone'         => $validated['phone'] ?? $student->phone,
                'address_current' => $validated['address_current'] ?? null,
                'total_credits' => 0, // Akan dihitung saat verifikasi
                'gpa'           => 0,
                'thesis_title'  => $thesis?->title,
                'submitted_at'  => now(),
            ]);

            // Auto-create checklist verifikasi
            $requirements = [
                ['requirement' => 'Skripsi/Tugas Akhir Lulus', 'category' => 'AKADEMIK'],
                ['requirement' => 'Semua Mata Kuliah Lulus', 'category' => 'AKADEMIK'],
                ['requirement' => 'Minimum SKS Terpenuhi', 'category' => 'AKADEMIK'],
                ['requirement' => 'Bebas Tagihan Keuangan', 'category' => 'KEUANGAN'],
                ['requirement' => 'Bebas Pinjaman Perpustakaan', 'category' => 'PERPUSTAKAAN'],
                ['requirement' => 'Foto Wisuda', 'category' => 'ADMINISTRASI'],
                ['requirement' => 'Ijazah Terakhir (SMA)', 'category' => 'ADMINISTRASI'],
            ];

            foreach ($requirements as $req) {
                GraduationVerification::create(array_merge($req, ['registration_id' => $reg->id]));
            }

            return $reg;
        });

        return response()->json(['message' => 'Pendaftaran wisuda berhasil.', 'data' => $reg->load('verifications')], 201);
    }

    /** Verifikasi syarat */
    public function verify(Request $request, GraduationVerification $verification): JsonResponse
    {
        $validated = $request->validate([
            'is_fulfilled' => 'required|boolean',
            'notes'        => 'nullable|string',
        ]);

        $verification->update([
            'is_fulfilled' => $validated['is_fulfilled'],
            'verified_by'  => auth()->id(),
            'verified_at'  => now(),
            'notes'        => $validated['notes'] ?? null,
        ]);

        // Cek apakah semua verifikasi terpenuhi
        $reg = $verification->registration;
        $allFulfilled = $reg->verifications()->where('is_fulfilled', false)->doesntExist();
        if ($allFulfilled) {
            $reg->update(['status' => 'APPROVED']);
        }

        return response()->json(['message' => 'Verifikasi berhasil diupdate.']);
    }

    /** Auto verifikasi keuangan */
    public function autoVerifyFinance(GraduationRegistration $registration): JsonResponse
    {
        $hasUnpaid = Invoice::where('student_id', $registration->student_id)
            ->whereIn('status', ['UNPAID', 'PARTIAL', 'OVERDUE'])->exists();

        $verification = $registration->verifications()->where('category', 'KEUANGAN')->first();
        if ($verification) {
            $verification->update([
                'is_fulfilled' => !$hasUnpaid,
                'verified_by'  => auth()->id(),
                'verified_at'  => now(),
                'notes'        => $hasUnpaid ? 'Masih ada tagihan belum lunas.' : 'Semua tagihan lunas.',
            ]);
        }

        return response()->json(['message' => $hasUnpaid ? 'Mahasiswa masih memiliki tagihan.' : 'Keuangan clear.', 'is_clear' => !$hasUnpaid]);
    }

    /** Update status pendaftaran */
    public function updateRegistrationStatus(Request $request, GraduationRegistration $registration): JsonResponse
    {
        $request->validate(['status' => 'required|in:SUBMITTED,VERIFIKASI_AKADEMIK,VERIFIKASI_KEUANGAN,VERIFIKASI_PERPUSTAKAAN,APPROVED,REJECTED,WISUDA', 'notes' => 'nullable|string']);
        $registration->update(['status' => $request->status, 'notes' => $request->notes ?? $registration->notes]);

        if ($request->status === 'WISUDA') {
            // Update status mahasiswa ke LULUS
            $registration->student->recordStatus('Lulus', null, 'Wisuda');
        }

        return response()->json(['message' => 'Status berhasil diupdate.']);
    }

    /** Set predikat kelulusan */
    public function setPredicate(Request $request, GraduationRegistration $registration): JsonResponse
    {
        $request->validate(['predicate' => 'required|string|max:50', 'gpa' => 'nullable|numeric', 'total_credits' => 'nullable|integer']);
        $registration->update($request->only(['predicate', 'gpa', 'total_credits']));
        return response()->json(['message' => 'Predikat berhasil ditetapkan.']);
    }

    // === DASHBOARD ===
    public function dashboard(Request $request): JsonResponse
    {
        $periodId = $request->period_id;
        $query = GraduationRegistration::when($periodId, fn($q) => $q->where('period_id', $periodId));

        return response()->json([
            'total'       => (clone $query)->count(),
            'submitted'   => (clone $query)->where('status', 'SUBMITTED')->count(),
            'in_process'  => (clone $query)->whereIn('status', ['VERIFIKASI_AKADEMIK', 'VERIFIKASI_KEUANGAN', 'VERIFIKASI_PERPUSTAKAAN'])->count(),
            'approved'    => (clone $query)->where('status', 'APPROVED')->count(),
            'graduated'   => (clone $query)->where('status', 'WISUDA')->count(),
            'rejected'    => (clone $query)->where('status', 'REJECTED')->count(),
        ]);
    }
}
