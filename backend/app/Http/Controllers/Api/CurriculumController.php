<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Curriculum;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CurriculumController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = Curriculum::with('studyProgram.faculty')
            ->when($request->study_program_id, fn($q) => $q->where('study_program_id', $request->study_program_id))
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->withCount('courses')
            ->paginate($request->per_page ?? 15);

        return response()->json($data);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'study_program_id' => 'required|exists:study_programs,id',
            'code'             => 'required|string|max:50|unique:curriculums',
            'name'             => 'required|string|max:255',
            'year'             => 'required|integer|digits:4',
            'description'      => 'nullable|string',
            'status'           => 'in:Draft,Aktif,Nonaktif',
        ]);

        $curriculum = Curriculum::create($validated);

        return response()->json([
            'message' => 'Kurikulum berhasil ditambahkan.',
            'data'    => $curriculum->load('studyProgram'),
        ], 201);
    }

    public function show(Curriculum $curriculum): JsonResponse
    {
        return response()->json(
            $curriculum->load(['studyProgram.faculty', 'learningOutcomes.graduateProfiles', 'curriculumCourses.course'])
        );
    }

    public function update(Request $request, Curriculum $curriculum): JsonResponse
    {
        $validated = $request->validate([
            'study_program_id' => 'sometimes|exists:study_programs,id',
            'code'             => "sometimes|string|max:50|unique:curriculums,code,{$curriculum->id}",
            'name'             => 'sometimes|string|max:255',
            'year'             => 'sometimes|integer|digits:4',
            'description'      => 'nullable|string',
            'status'           => 'in:Draft,Aktif,Nonaktif',
        ]);

        $curriculum->update($validated);

        return response()->json(['message' => 'Kurikulum berhasil diupdate.', 'data' => $curriculum->fresh('studyProgram')]);
    }

    public function destroy(Curriculum $curriculum): JsonResponse
    {
        $curriculum->delete();
        return response()->json(['message' => 'Kurikulum berhasil dihapus.']);
    }

    // Kelola mata kuliah dalam kurikulum
    public function syncCourses(Request $request, Curriculum $curriculum): JsonResponse
    {
        $request->validate([
            'courses'               => 'required|array',
            'courses.*.course_id'   => 'required|exists:courses,id',
            'courses.*.semester'    => 'required|integer|min:1|max:8',
            'courses.*.is_required' => 'boolean',
        ]);

        $sync = collect($request->courses)->mapWithKeys(fn($c) => [
            $c['course_id'] => ['semester' => $c['semester'], 'is_required' => $c['is_required'] ?? true],
        ])->toArray();

        $curriculum->courses()->sync($sync);

        return response()->json(['message' => 'Mata kuliah kurikulum berhasil disimpan.']);
    }

    // Kelola CPL
    public function storeLearningOutcome(Request $request, Curriculum $curriculum): JsonResponse
    {
        $validated = $request->validate([
            'code'                  => 'required|string|max:20',
            'category'              => 'nullable|string|max:50',
            'description'           => 'required|string',
            'graduate_profile_ids'  => 'nullable|array',
            'graduate_profile_ids.*'=> 'exists:graduate_profiles,id',
        ]);

        $lo = $curriculum->learningOutcomes()->create([
            'code'        => $validated['code'],
            'category'    => $validated['category'] ?? null,
            'description' => $validated['description'],
        ]);

        if (!empty($validated['graduate_profile_ids'])) {
            $lo->graduateProfiles()->sync($validated['graduate_profile_ids']);
        }

        return response()->json(['message' => 'CPL berhasil ditambahkan.', 'data' => $lo->load('graduateProfiles')], 201);
    }

    public function destroyLearningOutcome(Curriculum $curriculum, int $loId): JsonResponse
    {
        $curriculum->learningOutcomes()->findOrFail($loId)->delete();
        return response()->json(['message' => 'CPL berhasil dihapus.']);
    }

    public function updateLearningOutcome(Request $request, Curriculum $curriculum, int $loId): JsonResponse
    {
        $validated = $request->validate([
            'code'                  => 'sometimes|string|max:20',
            'category'              => 'nullable|string|max:50',
            'description'           => 'sometimes|string',
            'graduate_profile_ids'  => 'nullable|array',
            'graduate_profile_ids.*'=> 'exists:graduate_profiles,id',
        ]);

        $lo = $curriculum->learningOutcomes()->findOrFail($loId);
        $lo->update(collect($validated)->except('graduate_profile_ids')->toArray());

        if (array_key_exists('graduate_profile_ids', $validated)) {
            $lo->graduateProfiles()->sync($validated['graduate_profile_ids'] ?? []);
        }

        return response()->json(['message' => 'CPL berhasil diupdate.', 'data' => $lo->fresh('graduateProfiles')]);
    }
}
