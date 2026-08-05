<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PmbRegistrant;
use App\Models\PmbExamScore;
use App\Models\PmbExamType;
use App\Models\PmbSelectionResult;
use App\Models\PmbReRegistration;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class PmbRegistrantController extends Controller
{
    /** List pendaftar (admin view) */
    public function index(Request $request): JsonResponse
    {
        $data = PmbRegistrant::with(['period', 'path', 'studyProgramChoice1', 'acceptedProgram'])
            ->when($request->search, fn($q) => $q
                ->where('full_name', 'like', "%{$request->search}%")
                ->orWhere('registration_number', 'like', "%{$request->search}%"))
            ->when($request->period_id, fn($q) => $q->where('pmb_period_id', $request->period_id))
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->path_id, fn($q) => $q->where('pmb_path_id', $request->path_id))
            ->orderByDesc('created_at')
            ->paginate($request->per_page ?? 15);

        return response()->json($data);
    }

    /** Detail pendaftar */
    public function show(PmbRegistrant $pmbRegistrant): JsonResponse
    {
        return response()->json(
            $pmbRegistrant->load([
                'period', 'path', 'user',
                'studyProgramChoice1', 'studyProgramChoice2', 'studyProgramChoice3',
                'acceptedProgram', 'examScores.examType',
                'selectionResult', 'reRegistration',
            ])
        );
    }

    /** Verifikasi berkas */
    public function verify(Request $request, PmbRegistrant $pmbRegistrant): JsonResponse
    {
        $request->validate(['status' => 'required|in:TERVERIFIKASI,MENUNGGU_VERIFIKASI', 'admin_note' => 'nullable|string']);

        $pmbRegistrant->update([
            'status'      => $request->status,
            'admin_note'  => $request->admin_note,
            'verified_by' => auth()->id(),
            'verified_at' => $request->status === 'TERVERIFIKASI' ? now() : null,
        ]);

        return response()->json(['message' => 'Status verifikasi berhasil diupdate.', 'data' => $pmbRegistrant->fresh()]);
    }

    /** Set status mengikuti seleksi */
    public function setSelectionStatus(PmbRegistrant $pmbRegistrant): JsonResponse
    {
        if ($pmbRegistrant->status !== 'TERVERIFIKASI') {
            return response()->json(['message' => 'Pendaftar belum terverifikasi.'], 422);
        }

        $pmbRegistrant->update(['status' => 'MENGIKUTI_SELEKSI']);
        return response()->json(['message' => 'Status berhasil diubah.', 'data' => $pmbRegistrant->fresh()]);
    }

    /** Input nilai ujian */
    public function inputScore(Request $request, PmbRegistrant $pmbRegistrant): JsonResponse
    {
        $validated = $request->validate([
            'scores'            => 'required|array',
            'scores.*.exam_type_id' => 'required|exists:pmb_exam_types,id',
            'scores.*.score'        => 'required|numeric|min:0|max:100',
            'scores.*.note'         => 'nullable|string',
        ]);

        foreach ($validated['scores'] as $s) {
            PmbExamScore::updateOrCreate(
                ['registrant_id' => $pmbRegistrant->id, 'exam_type_id' => $s['exam_type_id']],
                ['score' => $s['score'], 'note' => $s['note'] ?? null, 'scored_by' => auth()->id()]
            );
        }

        return response()->json(['message' => 'Nilai berhasil disimpan.']);
    }

    /** Hitung nilai akhir dan tentukan kelulusan */
    public function calculateResult(PmbRegistrant $pmbRegistrant): JsonResponse
    {
        $scores    = $pmbRegistrant->examScores()->with('examType')->get();
        $examTypes = PmbExamType::where('is_active', true)->get();

        if ($scores->isEmpty()) {
            return response()->json(['message' => 'Belum ada nilai yang diinput.'], 422);
        }

        $totalWeight = $examTypes->sum('weight') ?: 100;
        $finalScore  = 0;
        $allPassed   = true;

        foreach ($scores as $score) {
            $weight = $score->examType->weight;
            $finalScore += ($score->score * $weight / $totalWeight);

            if ($score->score < $score->examType->passing_grade) {
                $allPassed = false;
            }
        }

        $finalScore   = round($finalScore, 2);
        $recommendation = $allPassed ? 'LULUS' : 'TIDAK_LULUS';

        PmbSelectionResult::updateOrCreate(
            ['registrant_id' => $pmbRegistrant->id],
            [
                'final_score'    => $finalScore,
                'recommendation' => $recommendation,
                'decided_by'     => auth()->id(),
                'decided_at'     => now(),
            ]
        );

        return response()->json([
            'message'        => 'Perhitungan selesai.',
            'final_score'    => $finalScore,
            'recommendation' => $recommendation,
        ]);
    }

    /** Tetapkan kelulusan (admin final decision) */
    public function setFinalStatus(Request $request, PmbRegistrant $pmbRegistrant): JsonResponse
    {
        $validated = $request->validate([
            'final_status'       => 'required|in:LULUS,TIDAK_LULUS',
            'accepted_program_id' => 'nullable|exists:study_programs,id',
            'note'               => 'nullable|string',
        ]);

        DB::transaction(function () use ($pmbRegistrant, $validated) {
            $result = PmbSelectionResult::firstOrCreate(
                ['registrant_id' => $pmbRegistrant->id],
                ['final_score' => 0, 'decided_by' => auth()->id(), 'decided_at' => now()]
            );
            $result->update([
                'final_status' => $validated['final_status'],
                'note'         => $validated['note'] ?? null,
                'decided_by'   => auth()->id(),
                'decided_at'   => now(),
            ]);

            $newStatus = $validated['final_status'] === 'LULUS' ? 'LULUS' : 'TIDAK_LULUS';
            $pmbRegistrant->update([
                'status'              => $newStatus,
                'accepted_program_id' => $validated['final_status'] === 'LULUS'
                    ? ($validated['accepted_program_id'] ?? $pmbRegistrant->choice_1)
                    : null,
            ]);
        });

        return response()->json([
            'message' => 'Kelulusan berhasil ditetapkan.',
            'data'    => $pmbRegistrant->fresh(['selectionResult', 'acceptedProgram']),
        ]);
    }

    /** Proses daftar ulang → jadi mahasiswa */
    public function processReRegistration(Request $request, PmbRegistrant $pmbRegistrant): JsonResponse
    {
        if ($pmbRegistrant->status !== 'LULUS' && $pmbRegistrant->status !== 'DAFTAR_ULANG') {
            return response()->json(['message' => 'Pendaftar belum dinyatakan lulus.'], 422);
        }

        $validated = $request->validate([
            'nim'  => 'required|string|max:20|unique:students,nim',
            'note' => 'nullable|string',
        ]);

        $result = DB::transaction(function () use ($pmbRegistrant, $validated) {
            // Update status pendaftar
            $pmbRegistrant->update(['status' => 'MAHASISWA_BARU']);

            // Simpan data daftar ulang
            $reReg = PmbReRegistration::updateOrCreate(
                ['registrant_id' => $pmbRegistrant->id],
                [
                    'is_completed'  => true,
                    'nim'           => $validated['nim'],
                    'completed_at'  => now(),
                    'processed_by'  => auth()->id(),
                    'note'          => $validated['note'] ?? null,
                ]
            );

            // Buat data mahasiswa
            $user = $pmbRegistrant->user;
            $user->update(['username' => $validated['nim']]);
            $user->syncRoles(['MAHASISWA']);

            $student = Student::create([
                'user_id'          => $user->id,
                'study_program_id' => $pmbRegistrant->accepted_program_id ?? $pmbRegistrant->choice_1,
                'nim'              => $validated['nim'],
                'name'             => $pmbRegistrant->full_name,
                'gender'           => $pmbRegistrant->gender,
                'birth_place'      => $pmbRegistrant->birth_place,
                'birth_date'       => $pmbRegistrant->birth_date,
                'email'            => $pmbRegistrant->email,
                'phone'            => $pmbRegistrant->phone,
                'entry_year'       => now()->year,
                'status'           => 'Aktif',
                'current_semester' => 1,
            ]);

            // Profil mahasiswa
            $student->profile()->create([
                'religion'    => $pmbRegistrant->religion,
                'nik'         => $pmbRegistrant->nik,
                'nationality' => 'Indonesia',
                'photo_path'  => $pmbRegistrant->photo_path,
            ]);

            // Alamat
            if ($pmbRegistrant->address) {
                $student->addresses()->create([
                    'type'        => 'Domisili',
                    'address'     => $pmbRegistrant->address,
                    'village'     => $pmbRegistrant->village,
                    'district'    => $pmbRegistrant->district,
                    'city'        => $pmbRegistrant->city,
                    'province'    => $pmbRegistrant->province,
                    'postal_code' => $pmbRegistrant->postal_code,
                ]);
            }

            // Orang tua
            if ($pmbRegistrant->father_name) {
                $student->parents()->create([
                    'relation'   => 'Ayah',
                    'name'       => $pmbRegistrant->father_name,
                    'occupation' => $pmbRegistrant->father_occupation,
                    'phone'      => $pmbRegistrant->father_phone,
                ]);
            }
            if ($pmbRegistrant->mother_name) {
                $student->parents()->create([
                    'relation'   => 'Ibu',
                    'name'       => $pmbRegistrant->mother_name,
                    'occupation' => $pmbRegistrant->mother_occupation,
                    'phone'      => $pmbRegistrant->mother_phone,
                ]);
            }
            if ($pmbRegistrant->guardian_name) {
                $student->parents()->create([
                    'relation'   => 'Wali',
                    'name'       => $pmbRegistrant->guardian_name,
                    'occupation' => $pmbRegistrant->guardian_occupation,
                    'phone'      => $pmbRegistrant->guardian_phone,
                ]);
            }

            // Riwayat pendidikan
            if ($pmbRegistrant->school_name) {
                $student->educationHistories()->create([
                    'level'               => 'SMA/MA/SMK',
                    'institution_name'    => $pmbRegistrant->school_name,
                    'institution_address' => $pmbRegistrant->school_address,
                    'graduation_year'     => $pmbRegistrant->graduation_year,
                    'diploma_number'      => $pmbRegistrant->diploma_number,
                ]);
            }

            // Dokumen dari PMB
            if ($pmbRegistrant->photo_path) {
                $student->documents()->create(['type' => 'FOTO', 'name' => 'Pas Foto', 'file_path' => $pmbRegistrant->photo_path]);
            }
            if ($pmbRegistrant->diploma_link) {
                $student->documents()->create(['type' => 'IJAZAH', 'name' => 'Ijazah', 'file_url' => $pmbRegistrant->diploma_link, 'document_number' => $pmbRegistrant->diploma_number]);
            }
            if ($pmbRegistrant->family_card_link) {
                $student->documents()->create(['type' => 'KK', 'name' => 'Kartu Keluarga', 'file_url' => $pmbRegistrant->family_card_link]);
            }
            if ($pmbRegistrant->identity_link) {
                $student->documents()->create(['type' => 'KTP', 'name' => 'KTP/Identitas', 'file_url' => $pmbRegistrant->identity_link]);
            }

            // Status history pertama
            $student->recordStatus('Aktif', null, 'Mahasiswa baru dari PMB');

            return ['re_registration' => $reReg, 'student' => $student];
        });

        return response()->json([
            'message' => 'Daftar ulang berhasil. Mahasiswa baru telah dibuat.',
            'data'    => $result,
        ]);
    }

    /** Hapus pendaftar */
    public function destroy(PmbRegistrant $pmbRegistrant): JsonResponse
    {
        $pmbRegistrant->examScores()->delete();
        $pmbRegistrant->selectionResult()?->delete();
        $pmbRegistrant->reRegistration()?->delete();
        $pmbRegistrant->delete();

        return response()->json(['message' => 'Data pendaftar berhasil dihapus.']);
    }

    /** Statistik PMB per periode */
    public function statistics(Request $request): JsonResponse
    {
        $periodId = $request->period_id;

        $stats = PmbRegistrant::when($periodId, fn($q) => $q->where('pmb_period_id', $periodId))
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN status = 'DRAFT' THEN 1 ELSE 0 END) as draft,
                SUM(CASE WHEN status = 'SUBMITTED' THEN 1 ELSE 0 END) as submitted,
                SUM(CASE WHEN status = 'MENUNGGU_VERIFIKASI' THEN 1 ELSE 0 END) as menunggu_verifikasi,
                SUM(CASE WHEN status = 'TERVERIFIKASI' THEN 1 ELSE 0 END) as terverifikasi,
                SUM(CASE WHEN status = 'MENGIKUTI_SELEKSI' THEN 1 ELSE 0 END) as mengikuti_seleksi,
                SUM(CASE WHEN status = 'LULUS' THEN 1 ELSE 0 END) as lulus,
                SUM(CASE WHEN status = 'TIDAK_LULUS' THEN 1 ELSE 0 END) as tidak_lulus,
                SUM(CASE WHEN status = 'DAFTAR_ULANG' THEN 1 ELSE 0 END) as daftar_ulang,
                SUM(CASE WHEN status = 'MAHASISWA_BARU' THEN 1 ELSE 0 END) as mahasiswa_baru
            ")->first();

        return response()->json($stats);
    }
}
