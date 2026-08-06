<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StudyProgram;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudyProgramController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = StudyProgram::with(['faculty.institution', 'headLecturer'])
            ->when($request->search, fn($q) => $q->where('name', 'like', "%{$request->search}%")
                ->orWhere('code', 'like', "%{$request->search}%"))
            ->when($request->faculty_id, fn($q) => $q->where('faculty_id', $request->faculty_id))
            ->when($request->level, fn($q) => $q->where('level', $request->level))
            ->withCount('students')
            ->paginate($request->per_page ?? 15);

        return response()->json($data);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'faculty_id'       => 'required|exists:faculties,id',
            'code'             => 'required|string|max:20|unique:study_programs',
            'name'             => 'required|string|max:255',
            'degree'           => 'nullable|string|max:20',
            'level'            => 'nullable|in:D3,S1,S2,S3,Profesi',
            'accreditation'    => 'nullable|string|max:20',
            'head_lecturer_id' => 'nullable|exists:lecturers,id',
            'status'           => 'boolean',
        ]);

        $prodi = StudyProgram::create($validated);

        return response()->json([
            'message' => 'Program studi berhasil ditambahkan.',
            'data'    => $prodi->load('faculty'),
        ], 201);
    }

    public function show(StudyProgram $studyProgram): JsonResponse
    {
        return response()->json($studyProgram->load(['faculty.institution', 'headLecturer']));
    }

    public function update(Request $request, StudyProgram $studyProgram): JsonResponse
    {
        $validated = $request->validate([
            'faculty_id'       => 'sometimes|exists:faculties,id',
            'code'             => "sometimes|string|max:20|unique:study_programs,code,{$studyProgram->id}",
            'name'             => 'sometimes|string|max:255',
            'degree'           => 'nullable|string|max:20',
            'level'            => 'nullable|in:D3,S1,S2,S3,Profesi',
            'accreditation'    => 'nullable|string|max:20',
            'head_lecturer_id' => 'nullable|exists:lecturers,id',
            'status'           => 'boolean',
        ]);

        $studyProgram->update($validated);

        return response()->json([
            'message' => 'Program studi berhasil diupdate.',
            'data'    => $studyProgram->fresh('faculty'),
        ]);
    }

    public function destroy(Request $request, StudyProgram $studyProgram): JsonResponse
    {
        if ($studyProgram->students()->count() > 0) {
            return response()->json(['message' => 'Tidak dapat menghapus prodi yang masih memiliki mahasiswa aktif.'], 422);
        }

        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($studyProgram) {
                // Hapus semua data terkait yang punya FK ke study_programs tanpa cascade
                \App\Models\Classes::where('study_program_id', $studyProgram->id)->delete();
                $studyProgram->courses()->delete();

                // Tabel lain yang mungkin punya FK (nullable — set null)
                \Illuminate\Support\Facades\DB::table('theses')
                    ->where('study_program_id', $studyProgram->id)->delete();
                \Illuminate\Support\Facades\DB::table('alumni')
                    ->where('study_program_id', $studyProgram->id)->delete();
                \Illuminate\Support\Facades\DB::table('applicant_choices')
                    ->where('study_program_id', $studyProgram->id)->delete();
                \Illuminate\Support\Facades\DB::table('lecturers')
                    ->where('study_program_id', $studyProgram->id)
                    ->update(['study_program_id' => null]);

                $studyProgram->delete();
            });

            return response()->json(['message' => 'Program studi beserta data terkait berhasil dihapus.']);
        } catch (\Illuminate\Database\QueryException $e) {
            \Illuminate\Support\Facades\Log::error('Gagal hapus prodi: ' . $e->getMessage());
            return response()->json([
                'message' => 'Gagal menghapus prodi. Masih ada data terkait yang tidak bisa dihapus otomatis: ' . $this->extractFkTable($e->getMessage()),
            ], 422);
        }
    }

    /** Extract nama tabel dari error FK constraint */
    private function extractFkTable(string $message): string
    {
        if (preg_match('/REFERENCES `(\w+)`/', $message, $m)) {
            return 'tabel ' . $m[1];
        }
        if (preg_match('/a foreign key constraint fails \(`[^`]+`\.`(\w+)`/', $message, $m)) {
            return 'tabel ' . $m[1];
        }
        return 'tabel terkait';
    }

    public function all(Request $request): JsonResponse
    {
        return response()->json(
            StudyProgram::where('status', true)
                ->when($request->faculty_id, fn($q) => $q->where('faculty_id', $request->faculty_id))
                ->select('id', 'code', 'name', 'level', 'faculty_id')
                ->get()
        );
    }
}
