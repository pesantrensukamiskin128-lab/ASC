<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CourseLearningOutcome;
use App\Models\GraduateProfile;
use App\Models\Curriculum;
use App\Models\LearningOutcome;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CurriculumObeController extends Controller
{
    // =============================================
    // PROFIL LULUSAN
    // =============================================

    public function graduateProfiles(Curriculum $curriculum): JsonResponse
    {
        return response()->json($curriculum->graduateProfiles ?? GraduateProfile::where('curriculum_id', $curriculum->id)->orderBy('order')->get());
    }

    public function storeGraduateProfile(Request $request, Curriculum $curriculum): JsonResponse
    {
        $validated = $request->validate([
            'code'        => 'required|string|max:20',
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $profile = GraduateProfile::create(array_merge($validated, [
            'curriculum_id' => $curriculum->id,
            'order'         => GraduateProfile::where('curriculum_id', $curriculum->id)->count() + 1,
        ]));

        return response()->json(['message' => 'Profil lulusan berhasil ditambahkan.', 'data' => $profile], 201);
    }

    public function updateGraduateProfile(Request $request, Curriculum $curriculum, GraduateProfile $profile): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'sometimes|string|max:20', 'name' => 'sometimes|string', 'description' => 'nullable|string',
        ]);
        $profile->update($validated);
        return response()->json(['message' => 'Profil lulusan berhasil diupdate.', 'data' => $profile->fresh()]);
    }

    public function destroyGraduateProfile(Curriculum $curriculum, GraduateProfile $profile): JsonResponse
    {
        $profile->delete();
        return response()->json(['message' => 'Profil lulusan berhasil dihapus.']);
    }

    // =============================================
    // CPL (sudah ada di CurriculumController, tapi tambah detail)
    // =============================================

    /** Pemetaan CPL → Mata Kuliah (matriks) */
    public function cplCourseMatrix(Curriculum $curriculum): JsonResponse
    {
        $cpls = LearningOutcome::where('curriculum_id', $curriculum->id)
            ->orderBy('code')
            ->get();

        $courses = $curriculum->courses()->select('courses.id', 'courses.code', 'courses.name')->get();

        // Build matrix
        $matrix = [];
        foreach ($cpls as $cpl) {
            $row = ['cpl' => $cpl];
            $row['courses'] = [];
            foreach ($courses as $course) {
                $mapping = DB::table('cpl_course_mappings')
                    ->where('learning_outcome_id', $cpl->id)
                    ->where('course_id', $course->id)
                    ->first();
                $row['courses'][$course->id] = $mapping?->support_level ?? null;
            }
            $matrix[] = $row;
        }

        return response()->json(['matrix' => $matrix, 'courses' => $courses, 'cpls' => $cpls]);
    }

    /** Update pemetaan CPL-MK (centang/uncentang) */
    public function updateCplCourseMapping(Request $request, Curriculum $curriculum): JsonResponse
    {
        $validated = $request->validate([
            'mappings'                       => 'required|array',
            'mappings.*.learning_outcome_id' => 'required|exists:learning_outcomes,id',
            'mappings.*.course_id'           => 'required|exists:courses,id',
            'mappings.*.checked'             => 'required|boolean',
        ]);

        DB::transaction(function () use ($validated) {
            foreach ($validated['mappings'] as $m) {
                if ($m['checked']) {
                    DB::table('cpl_course_mappings')->updateOrInsert(
                        ['learning_outcome_id' => $m['learning_outcome_id'], 'course_id' => $m['course_id']],
                        ['support_level' => 'Tinggi', 'updated_at' => now(), 'created_at' => now()]
                    );
                } else {
                    DB::table('cpl_course_mappings')
                        ->where('learning_outcome_id', $m['learning_outcome_id'])
                        ->where('course_id', $m['course_id'])
                        ->delete();
                }
            }
        });

        return response()->json(['message' => 'Pemetaan CPL–Mata Kuliah berhasil disimpan.']);
    }

    // =============================================
    // CPMK (per mata kuliah per kurikulum)
    // =============================================

    public function courseLearningOutcomes(Curriculum $curriculum, int $courseId): JsonResponse
    {
        $cpmks = CourseLearningOutcome::where('curriculum_id', $curriculum->id)
            ->where('course_id', $courseId)
            ->with(['subCpmks', 'learningOutcomes'])
            ->orderBy('order')
            ->get();

        return response()->json($cpmks);
    }

    public function storeCpmk(Request $request, Curriculum $curriculum): JsonResponse
    {
        $validated = $request->validate([
            'course_id'   => 'required|exists:courses,id',
            'code'        => 'required|string|max:20',
            'description' => 'required|string',
            'cpl_ids'     => 'nullable|array',
            'cpl_ids.*'   => 'exists:learning_outcomes,id',
        ]);

        $cpmk = CourseLearningOutcome::create([
            'curriculum_id' => $curriculum->id,
            'course_id'     => $validated['course_id'],
            'code'          => $validated['code'],
            'description'   => $validated['description'],
            'order'         => CourseLearningOutcome::where('curriculum_id', $curriculum->id)->where('course_id', $validated['course_id'])->count() + 1,
        ]);

        if (!empty($validated['cpl_ids'])) {
            $cpmk->learningOutcomes()->sync($validated['cpl_ids']);
        }

        return response()->json(['message' => 'CPMK berhasil ditambahkan.', 'data' => $cpmk->load(['subCpmks', 'learningOutcomes'])], 201);
    }

    /** Matriks CPMK → CPL */
    public function cpmkCplMatrix(Curriculum $curriculum, int $courseId): JsonResponse
    {
        $cpmks = CourseLearningOutcome::where('curriculum_id', $curriculum->id)
            ->where('course_id', $courseId)
            ->with('learningOutcomes')
            ->orderBy('order')
            ->get();

        $cpls = LearningOutcome::where('curriculum_id', $curriculum->id)->orderBy('code')->get();

        return response()->json(['cpmks' => $cpmks, 'cpls' => $cpls]);
    }
}
