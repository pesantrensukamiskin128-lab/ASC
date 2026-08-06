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

        // Hapus relasi yang menghalangi (mata kuliah, kelas) sebelum hapus prodi
        // Kelas yang merujuk prodi ini
        \App\Models\Classes::where('study_program_id', $studyProgram->id)->delete();

        // Mata kuliah milik prodi ini
        $studyProgram->courses()->delete();

        $studyProgram->delete();

        return response()->json(['message' => 'Program studi beserta mata kuliah terkait berhasil dihapus.']);
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
