<?php

namespace App\Http\Controllers\Api;

use App\Exports\CourseExport;
use App\Http\Controllers\Controller;
use App\Imports\CourseImport;
use App\Models\Course;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class CourseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = Course::with('studyProgram.faculty')
            ->when($request->search, fn($q) => $q->where('name', 'like', "%{$request->search}%")
                ->orWhere('code', 'like', "%{$request->search}%"))
            ->when($request->study_program_id, fn($q) => $q->where('study_program_id', $request->study_program_id))
            ->when($request->semester, fn($q) => $q->where('semester', $request->semester))
            ->when($request->type, fn($q) => $q->where('type', $request->type))
            ->paginate($request->per_page ?? 15);

        return response()->json($data);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'study_program_id' => 'required|exists:study_programs,id',
            'code'             => 'required|string|max:20|unique:courses',
            'name'             => 'required|string|max:255',
            'credits'          => 'required|integer|min:1|max:6',
            'semester'         => 'required|integer|min:1|max:8',
            'type'             => 'required|in:Wajib,Pilihan,Praktikum',
            'status'           => 'boolean',
        ]);

        $course = Course::create($validated);

        return response()->json([
            'message' => 'Mata kuliah berhasil ditambahkan.',
            'data'    => $course->load('studyProgram'),
        ], 201);
    }

    public function show(Course $course): JsonResponse
    {
        return response()->json($course->load('studyProgram.faculty'));
    }

    public function update(Request $request, Course $course): JsonResponse
    {
        $validated = $request->validate([
            'study_program_id' => 'sometimes|exists:study_programs,id',
            'code'             => "sometimes|string|max:20|unique:courses,code,{$course->id}",
            'name'             => 'sometimes|string|max:255',
            'credits'          => 'sometimes|integer|min:1|max:6',
            'semester'         => 'sometimes|integer|min:1|max:8',
            'type'             => 'sometimes|in:Wajib,Pilihan,Praktikum',
            'status'           => 'boolean',
        ]);

        $course->update($validated);

        return response()->json([
            'message' => 'Mata kuliah berhasil diupdate.',
            'data'    => $course->fresh('studyProgram'),
        ]);
    }

    public function destroy(Course $course): JsonResponse
    {
        $course->delete();

        return response()->json(['message' => 'Mata kuliah berhasil dihapus.']);
    }

    public function export(Request $request)
    {
        $filename = 'mata-kuliah-' . now()->format('Ymd-His') . '.xlsx';
        return Excel::download(
            new CourseExport($request->study_program_id, $request->semester),
            $filename
        );
    }

    public function import(Request $request): JsonResponse
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,xls,csv|max:5120']);

        $import = new CourseImport();
        Excel::import($import, $request->file('file'));

        $errors = collect($import->errors())->map(fn($e) => $e->getMessage())->values();

        return response()->json([
            'message' => 'Import selesai.' . ($errors->count() ? ' Beberapa baris dilewati.' : ''),
            'errors'  => $errors,
        ]);
    }

    public function all(Request $request): JsonResponse
    {
        return response()->json(
            Course::where('status', true)
                ->when($request->study_program_id, fn($q) => $q->where('study_program_id', $request->study_program_id))
                ->when($request->semester, fn($q) => $q->where('semester', $request->semester))
                ->select('id', 'code', 'name', 'credits', 'semester', 'type')
                ->get()
        );
    }
}
