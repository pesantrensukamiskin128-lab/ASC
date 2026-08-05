<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TransferApproval;
use App\Models\TransferCourseConversion;
use App\Models\TransferCreditApplication;
use App\Models\TransferDocument;
use App\Models\TransferEvaluation;
use App\Models\TransferredGrade;
use App\Models\TransferSourceCourse;
use App\Models\TransferSourceInstitution;
use App\Models\StudentAcademicPlacement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransferCreditController extends Controller
{
    // === APPLICATIONS ===

    public function index(Request $request): JsonResponse
    {
        $data = TransferCreditApplication::with(['student.studyProgram', 'sourceInstitution'])
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->search, fn($q) => $q->whereHas('student', fn($q2) =>
                $q2->where('name', 'like', "%{$request->search}%")->orWhere('nim', 'like', "%{$request->search}%")))
            ->orderByDesc('created_at')
            ->paginate($request->per_page ?? 15);
        return response()->json($data);
    }

    public function show(TransferCreditApplication $application): JsonResponse
    {
        return response()->json($application->load([
            'student.studyProgram', 'sourceInstitution', 'documents.verifier',
            'sourceCourses.conversion.targetCourse', 'conversions.sourceCourse', 'conversions.targetCourse',
            'evaluations.evaluator', 'approvals.approver', 'transferredGrades.targetCourse', 'placement',
        ]));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id'             => 'required|exists:students,id',
            'transfer_type'          => 'required|in:EXTERNAL,INTERNAL,RPL',
            'source_institution_name'=> 'nullable|string|max:255',
            'source_institution_id'  => 'nullable|exists:transfer_source_institutions,id',
            'source_study_program'   => 'nullable|string|max:255',
            'source_degree'          => 'nullable|string|max:20',
            'source_student_number'  => 'nullable|string|max:50',
            'source_total_credits'   => 'nullable|integer',
            'source_gpa'             => 'nullable|numeric|min:0|max:4',
            'source_semesters'       => 'nullable|integer',
        ]);

        $institutionId = $validated['source_institution_id'] ?? null;
        if (!$institutionId && !empty($validated['source_institution_name'])) {
            $inst = TransferSourceInstitution::create(['name' => $validated['source_institution_name']]);
            $institutionId = $inst->id;
        }

        $app = TransferCreditApplication::create([
            'student_id'            => $validated['student_id'],
            'source_institution_id' => $institutionId,
            'source_study_program'  => $validated['source_study_program'] ?? null,
            'source_degree'         => $validated['source_degree'] ?? null,
            'source_student_number' => $validated['source_student_number'] ?? null,
            'source_total_credits'  => $validated['source_total_credits'] ?? null,
            'source_gpa'            => $validated['source_gpa'] ?? null,
            'source_semesters'      => $validated['source_semesters'] ?? null,
            'transfer_type'         => $validated['transfer_type'],
            'application_date'      => now()->toDateString(),
            'status'                => 'DRAFT',
            'submitted_by'          => auth()->id(),
        ]);

        return response()->json(['message' => 'Aplikasi transfer berhasil dibuat.', 'data' => $app->load('sourceInstitution')], 201);
    }

    public function submit(TransferCreditApplication $application): JsonResponse
    {
        if ($application->status !== 'DRAFT') return response()->json(['message' => 'Sudah disubmit.'], 422);
        if ($application->sourceCourses()->count() === 0) return response()->json(['message' => 'Tambahkan mata kuliah asal terlebih dahulu.'], 422);

        $application->update(['status' => 'SUBMITTED']);

        // Create approval chain
        $chain = [
            ['level' => 1, 'role' => 'ADMIN_AKADEMIK'],
            ['level' => 2, 'role' => 'EVALUATOR'],
            ['level' => 3, 'role' => 'KAPRODI'],
            ['level' => 4, 'role' => 'WAKIL_KETUA_AKADEMIK'],
        ];
        foreach ($chain as $c) {
            TransferApproval::create([
                'application_id' => $application->id, 'approver_id' => auth()->id(),
                'approval_level' => $c['level'], 'approval_role' => $c['role'], 'status' => 'PENDING',
            ]);
        }

        return response()->json(['message' => 'Aplikasi berhasil disubmit.', 'data' => $application->fresh('approvals')]);
    }

    // === SOURCE COURSES ===

    public function addSourceCourse(Request $request, TransferCreditApplication $application): JsonResponse
    {
        $validated = $request->validate([
            'course_code'    => 'nullable|string|max:30',
            'course_name'    => 'required|string|max:255',
            'credits'        => 'required|numeric|min:0.5|max:12',
            'grade_letter'   => 'nullable|string|max:5',
            'grade_numeric'  => 'nullable|numeric|min:0|max:4',
            'semester_taken' => 'nullable|string|max:50',
            'year_taken'     => 'nullable|string|max:20',
        ]);

        $course = $application->sourceCourses()->create($validated);
        return response()->json(['message' => 'Mata kuliah asal ditambahkan.', 'data' => $course], 201);
    }

    public function removeSourceCourse(TransferCreditApplication $application, TransferSourceCourse $course): JsonResponse
    {
        $course->conversion()?->delete();
        $course->delete();
        return response()->json(['message' => 'Mata kuliah asal dihapus.']);
    }

    /** Import mata kuliah asal dari Excel */
    public function importSourceCourses(Request $request, TransferCreditApplication $application): JsonResponse
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,xls,csv|max:5120']);

        if ($application->status !== 'DRAFT') {
            return response()->json(['message' => 'Hanya bisa import saat status DRAFT.'], 422);
        }

        try {
            \Maatwebsite\Excel\Facades\Excel::import(
                new \App\Imports\TransferSourceCourseImport($application->id),
                $request->file('file')
            );

            $count = $application->sourceCourses()->count();
            return response()->json(['message' => "Import berhasil. Total {$count} mata kuliah.", 'count' => $count]);
        } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
            $failures = collect($e->failures())->map(fn($f) => "Baris {$f->row()}: {$f->errors()[0]}")->take(5);
            return response()->json(['message' => 'Validasi gagal.', 'errors' => $failures], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal import: ' . $e->getMessage()], 422);
        }
    }

    /** Download template Excel */
    public function templateSourceCourses(): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        return \Maatwebsite\Excel\Facades\Excel::download(new class implements \Maatwebsite\Excel\Concerns\FromArray, \Maatwebsite\Excel\Concerns\WithHeadings {
            public function array(): array { return [['MK-101', 'Pengantar Ilmu Hukum', 3, 'A', 4.00, '1', '2024'], ['MK-202', 'Hukum Perdata', 3, 'B+', 3.50, '2', '2024']]; }
            public function headings(): array { return ['kode', 'mata_kuliah', 'sks', 'nilai', 'bobot', 'semester', 'tahun']; }
        }, 'template-transfer-matakuliah.xlsx');
    }

    // === CONVERSIONS (PEMETAAN) ===

    public function mapConversion(Request $request, TransferCreditApplication $application): JsonResponse
    {
        $validated = $request->validate([
            'source_course_id'      => 'required|exists:transfer_source_courses,id',
            'target_course_id'      => 'required|exists:courses,id',
            'recognized_credits'    => 'required|numeric|min:0',
            'converted_grade'       => 'nullable|string|max:5',
            'converted_grade_point' => 'nullable|numeric|min:0|max:4',
            'conversion_type'       => 'required|in:DIRECT,PARTIAL,COMBINATION,ELECTIVE,REJECTED',
            'notes'                 => 'nullable|string',
        ]);

        $source = TransferSourceCourse::findOrFail($validated['source_course_id']);
        $targetCourse = \App\Models\Course::findOrFail($validated['target_course_id']);

        $conversion = TransferCourseConversion::updateOrCreate(
            ['application_id' => $application->id, 'source_course_id' => $validated['source_course_id']],
            [
                'target_course_id'      => $validated['target_course_id'],
                'source_credits'        => $source->credits,
                'target_credits'        => $targetCourse->credits,
                'recognized_credits'    => $validated['recognized_credits'],
                'source_grade'          => $source->grade_letter,
                'source_grade_point'    => $source->grade_numeric,
                'converted_grade'       => $validated['converted_grade'] ?? $source->grade_letter,
                'converted_grade_point' => $validated['converted_grade_point'] ?? $source->grade_numeric,
                'conversion_type'       => $validated['conversion_type'],
                'status'                => 'PENDING',
                'notes'                 => $validated['notes'] ?? null,
            ]
        );

        return response()->json(['message' => 'Pemetaan konversi berhasil.', 'data' => $conversion->load(['sourceCourse', 'targetCourse'])]);
    }

    public function removeConversion(TransferCreditApplication $application, TransferCourseConversion $conversion): JsonResponse
    {
        $conversion->delete();
        return response()->json(['message' => 'Konversi dihapus.']);
    }

    // === DOCUMENTS ===

    public function addDocument(Request $request, TransferCreditApplication $application): JsonResponse
    {
        $validated = $request->validate([
            'document_type' => 'required|string|max:50',
            'name'          => 'required|string|max:255',
            'file_url'      => 'nullable|string|max:500',
        ]);

        $doc = $application->documents()->create($validated);
        return response()->json(['message' => 'Dokumen ditambahkan.', 'data' => $doc], 201);
    }

    public function verifyDocument(Request $request, TransferDocument $document): JsonResponse
    {
        $request->validate(['is_verified' => 'required|boolean', 'notes' => 'nullable|string']);
        $document->update([
            'is_verified'        => $request->is_verified,
            'verified_by'        => auth()->id(),
            'verified_at'        => now(),
            'verification_notes' => $request->notes ?? null,
        ]);
        return response()->json(['message' => 'Verifikasi dokumen berhasil.']);
    }

    // === EVALUATION ===

    public function evaluate(Request $request, TransferCreditApplication $application): JsonResponse
    {
        $validated = $request->validate([
            'notes'          => 'nullable|string',
            'recommendation' => 'required|in:ACCEPT,ACCEPT_WITH_CONDITIONS,REJECT',
        ]);

        $conversions = $application->conversions;
        $evaluation = TransferEvaluation::create([
            'application_id'          => $application->id,
            'evaluator_id'            => auth()->id(),
            'evaluation_date'         => now()->toDateString(),
            'total_source_credits'    => $application->sourceCourses()->sum('credits'),
            'total_recognized_credits'=> $conversions->where('conversion_type', '!=', 'REJECTED')->sum('recognized_credits'),
            'total_rejected_credits'  => $conversions->where('conversion_type', 'REJECTED')->sum('source_credits'),
            'notes'                   => $validated['notes'] ?? null,
            'recommendation'          => $validated['recommendation'],
        ]);

        $application->update(['status' => 'ACADEMIC_EVALUATION']);
        return response()->json(['message' => 'Evaluasi berhasil disimpan.', 'data' => $evaluation]);
    }

    // === APPROVAL ===

    public function approve(Request $request, TransferCreditApplication $application): JsonResponse
    {
        $validated = $request->validate([
            'approval_role' => 'required|string',
            'action'        => 'required|in:approve,reject',
            'notes'         => 'nullable|string',
        ]);

        $approval = $application->approvals()->where('approval_role', $validated['approval_role'])->where('status', 'PENDING')->first();
        if (!$approval) return response()->json(['message' => 'Tidak ada approval pending untuk role ini.'], 422);

        if ($validated['action'] === 'approve') {
            $approval->update(['status' => 'APPROVED', 'approver_id' => auth()->id(), 'notes' => $validated['notes'], 'approved_at' => now()]);

            // Check if all approved
            $allApproved = $application->approvals()->where('status', '!=', 'APPROVED')->doesntExist();
            if ($allApproved) {
                $application->update(['status' => 'APPROVED']);
            }
        } else {
            $approval->update(['status' => 'REJECTED', 'approver_id' => auth()->id(), 'notes' => $validated['notes'], 'approved_at' => now()]);
            $application->update(['status' => 'REJECTED']);
        }

        return response()->json(['message' => 'Approval berhasil.', 'data' => $application->fresh('approvals')]);
    }

    // === FINALIZE ===

    public function finalize(TransferCreditApplication $application): JsonResponse
    {
        if ($application->status !== 'APPROVED') return response()->json(['message' => 'Belum disetujui.'], 422);

        DB::transaction(function () use ($application) {
            $conversions = $application->conversions()->where('status', '!=', 'REJECTED')->with('sourceCourse')->get();

            foreach ($conversions as $conv) {
                $conv->update(['status' => 'APPROVED']);
                TransferredGrade::create([
                    'student_id'                   => $application->student_id,
                    'application_id'               => $application->id,
                    'source_course_id'             => $conv->source_course_id,
                    'target_course_id'             => $conv->target_course_id,
                    'recognized_credits'           => $conv->recognized_credits,
                    'grade_letter'                 => $conv->converted_grade,
                    'grade_point'                  => $conv->converted_grade_point ?? 0,
                    'semester_label'               => 'Transfer',
                    'is_included_in_gpa'           => false,
                    'is_included_in_transcript'    => true,
                    'is_included_in_total_credits' => true,
                ]);
            }

            // Placement recommendation
            $totalRecognized = $conversions->sum('recognized_credits');
            $recommendedSem = max(1, intval($totalRecognized / 20) + 1);
            StudentAcademicPlacement::updateOrCreate(
                ['student_id' => $application->student_id, 'application_id' => $application->id],
                ['recommended_semester' => $recommendedSem]
            );

            $application->update(['status' => 'FINALIZED']);
        });

        return response()->json(['message' => 'Transfer nilai berhasil difinalisasi. Nilai telah masuk transkrip.', 'data' => $application->fresh(['transferredGrades.targetCourse', 'placement'])]);
    }

    // === INSTITUTIONS ===

    public function institutions(): JsonResponse
    {
        return response()->json(TransferSourceInstitution::orderBy('name')->get());
    }

    public function storeInstitution(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255', 'code' => 'nullable|string|max:50',
            'accreditation' => 'nullable|string|max:20', 'address' => 'nullable|string',
            'city' => 'nullable|string|max:100', 'province' => 'nullable|string|max:100',
        ]);
        $inst = TransferSourceInstitution::create($validated);
        return response()->json(['message' => 'PT Asal berhasil ditambahkan.', 'data' => $inst], 201);
    }

    // === DASHBOARD ===

    public function dashboard(): JsonResponse
    {
        return response()->json([
            'total'                  => TransferCreditApplication::count(),
            'draft'                  => TransferCreditApplication::where('status', 'DRAFT')->count(),
            'submitted'              => TransferCreditApplication::where('status', 'SUBMITTED')->count(),
            'document_verification'  => TransferCreditApplication::where('status', 'DOCUMENT_VERIFICATION')->count(),
            'academic_evaluation'    => TransferCreditApplication::where('status', 'ACADEMIC_EVALUATION')->count(),
            'approved'               => TransferCreditApplication::where('status', 'APPROVED')->count(),
            'finalized'              => TransferCreditApplication::where('status', 'FINALIZED')->count(),
            'rejected'               => TransferCreditApplication::where('status', 'REJECTED')->count(),
        ]);
    }
}
