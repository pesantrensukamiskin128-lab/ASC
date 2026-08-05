<?php

namespace App\Http\Controllers\Api;

use App\Exports\AlumniExport;
use App\Http\Controllers\Controller;
use App\Models\Alumni;
use App\Models\AlumniEmployment;
use App\Models\AlumniFurtherStudy;
use App\Models\TracerStudy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class AlumniController extends Controller
{
    // === ALUMNI ===
    public function index(Request $request): JsonResponse
    {
        $data = Alumni::with(['studyProgram', 'latestEmployment'])
            ->when($request->study_program_id, fn($q) => $q->where('study_program_id', $request->study_program_id))
            ->when($request->graduation_year, fn($q) => $q->where('graduation_year', $request->graduation_year))
            ->when($request->search, fn($q) => $q->where('name', 'like', "%{$request->search}%")->orWhere('nim', 'like', "%{$request->search}%"))
            ->orderByDesc('graduation_year')
            ->paginate($request->per_page ?? 20);
        return response()->json($data);
    }

    public function show(Alumni $alumni): JsonResponse
    {
        return response()->json($alumni->load(['studyProgram', 'employments', 'tracerStudies.period', 'furtherStudies']));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id'       => 'nullable|exists:students,id',
            'study_program_id' => 'required|exists:study_programs,id',
            'nim'              => 'required|string|max:20',
            'name'             => 'required|string|max:255',
            'email'            => 'nullable|email',
            'phone'            => 'nullable|string|max:20',
            'entry_year'       => 'required|integer',
            'graduation_year'  => 'required|integer',
            'graduation_date'  => 'nullable|string',
            'gpa'              => 'nullable|numeric|min:0|max:4',
            'thesis_title'     => 'nullable|string',
            'predicate'        => 'nullable|string|max:50',
            'address'          => 'nullable|string',
            'city'             => 'nullable|string|max:100',
            'province'         => 'nullable|string|max:100',
        ]);
        $alumni = Alumni::create($validated);
        return response()->json(['message' => 'Data alumni berhasil ditambahkan.', 'data' => $alumni], 201);
    }

    public function update(Request $request, Alumni $alumni): JsonResponse
    {
        $validated = $request->validate([
            'name'     => 'sometimes|string|max:255',
            'email'    => 'nullable|email',
            'phone'    => 'nullable|string|max:20',
            'address'  => 'nullable|string',
            'city'     => 'nullable|string|max:100',
            'province' => 'nullable|string|max:100',
            'is_active'=> 'boolean',
        ]);
        $alumni->update($validated);
        return response()->json(['message' => 'Data alumni diupdate.', 'data' => $alumni->fresh()]);
    }

    public function destroy(Alumni $alumni): JsonResponse
    {
        $alumni->employments()->delete();
        $alumni->tracerStudies()->delete();
        $alumni->furtherStudies()->delete();
        $alumni->delete();
        return response()->json(['message' => 'Data alumni dihapus.']);
    }

    // === EMPLOYMENT ===
    public function storeEmployment(Request $request, Alumni $alumni): JsonResponse
    {
        $validated = $request->validate([
            'company_name' => 'required|string|max:255', 'position' => 'required|string|max:255',
            'industry' => 'nullable|string|max:100', 'city' => 'nullable|string|max:100',
            'start_date' => 'nullable|date', 'end_date' => 'nullable|date',
            'is_current' => 'boolean', 'salary_range' => 'nullable|numeric', 'description' => 'nullable|string',
        ]);
        if ($validated['is_current'] ?? false) $alumni->employments()->update(['is_current' => false]);
        $emp = $alumni->employments()->create($validated);
        return response()->json(['message' => 'Riwayat pekerjaan ditambahkan.', 'data' => $emp], 201);
    }

    public function destroyEmployment(Alumni $alumni, AlumniEmployment $employment): JsonResponse
    {
        $employment->delete();
        return response()->json(['message' => 'Riwayat pekerjaan dihapus.']);
    }

    // === FURTHER STUDY ===
    public function storeFurtherStudy(Request $request, Alumni $alumni): JsonResponse
    {
        $validated = $request->validate([
            'institution' => 'required|string|max:255', 'program' => 'required|string|max:255',
            'degree' => 'required|string|max:20', 'entry_year' => 'nullable|integer',
            'graduation_year' => 'nullable|integer', 'is_current' => 'boolean',
        ]);
        $fs = $alumni->furtherStudies()->create($validated);
        return response()->json(['message' => 'Pendidikan lanjut ditambahkan.', 'data' => $fs], 201);
    }

    public function destroyFurtherStudy(Alumni $alumni, AlumniFurtherStudy $study): JsonResponse
    {
        $study->delete();
        return response()->json(['message' => 'Data dihapus.']);
    }

    // === TRACER STUDY ===
    public function tracerStudies(Request $request): JsonResponse
    {
        $data = TracerStudy::with(['alumni.studyProgram', 'period'])
            ->when($request->period_id, fn($q) => $q->where('period_id', $request->period_id))
            ->when($request->is_completed, fn($q) => $q->where('is_completed', true))
            ->orderByDesc('created_at')
            ->paginate($request->per_page ?? 20);
        return response()->json($data);
    }

    public function storeTracerStudy(Request $request, Alumni $alumni): JsonResponse
    {
        $validated = $request->validate([
            'period_id' => 'nullable|exists:academic_years,id',
            'employment_status' => 'nullable|in:BEKERJA,WIRAUSAHA,MELANJUTKAN_STUDI,BELUM_BEKERJA,LAINNYA',
            'months_to_first_job' => 'nullable|integer',
            'first_job_relevance' => 'nullable|string|max:50',
            'first_salary' => 'nullable|numeric', 'current_salary' => 'nullable|numeric',
            'competency_feedback' => 'nullable|string', 'curriculum_feedback' => 'nullable|string',
            'suggestion' => 'nullable|string', 'satisfaction_score' => 'nullable|integer|min:1|max:5',
        ]);
        $ts = $alumni->tracerStudies()->create(array_merge($validated, ['is_completed' => true, 'completed_at' => now()]));
        return response()->json(['message' => 'Tracer study berhasil disubmit.', 'data' => $ts], 201);
    }

    // === EXPORT ===
    public function export(Request $request)
    {
        $filename = 'alumni-' . now()->format('Ymd-His') . '.xlsx';
        return Excel::download(
            new AlumniExport($request->study_program_id, $request->graduation_year),
            $filename
        );
    }

    // === DASHBOARD ===
    public function dashboard(Request $request): JsonResponse
    {
        $query = Alumni::query();
        $total = (clone $query)->count();
        $byYear = Alumni::selectRaw('graduation_year, COUNT(*) as count')->groupBy('graduation_year')->orderByDesc('graduation_year')->limit(10)->get();
        $byProdi = Alumni::selectRaw('study_program_id, COUNT(*) as count')->with('studyProgram:id,code,name')->groupBy('study_program_id')->get();

        $tracerCompleted = TracerStudy::where('is_completed', true)->count();
        $employed = TracerStudy::where('is_completed', true)->where('employment_status', 'BEKERJA')->count();
        $entrepreneur = TracerStudy::where('is_completed', true)->where('employment_status', 'WIRAUSAHA')->count();
        $furtherStudy = TracerStudy::where('is_completed', true)->where('employment_status', 'MELANJUTKAN_STUDI')->count();

        return response()->json([
            'total_alumni' => $total,
            'by_year' => $byYear,
            'by_prodi' => $byProdi,
            'tracer_completed' => $tracerCompleted,
            'employed' => $employed,
            'entrepreneur' => $entrepreneur,
            'further_study' => $furtherStudy,
        ]);
    }
}
