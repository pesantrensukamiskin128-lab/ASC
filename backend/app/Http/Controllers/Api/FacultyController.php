<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Faculty;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FacultyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = Faculty::with('institution')
            ->when($request->search, fn($q) => $q->where('name', 'like', "%{$request->search}%")
                ->orWhere('code', 'like', "%{$request->search}%"))
            ->when($request->institution_id, fn($q) => $q->where('institution_id', $request->institution_id))
            ->withCount('studyPrograms')
            ->paginate($request->per_page ?? 15);

        return response()->json($data);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'institution_id' => 'required|exists:institutions,id',
            'code'           => 'required|string|max:20|unique:faculties',
            'name'           => 'required|string|max:255',
            'dean_name'      => 'nullable|string|max:255',
            'status'         => 'boolean',
        ]);

        $faculty = Faculty::create($validated);

        return response()->json(['message' => 'Fakultas berhasil ditambahkan.', 'data' => $faculty->load('institution')], 201);
    }

    public function show(Faculty $faculty): JsonResponse
    {
        return response()->json($faculty->load(['institution', 'studyPrograms']));
    }

    public function update(Request $request, Faculty $faculty): JsonResponse
    {
        $validated = $request->validate([
            'institution_id' => 'sometimes|exists:institutions,id',
            'code'           => "sometimes|string|max:20|unique:faculties,code,{$faculty->id}",
            'name'           => 'sometimes|string|max:255',
            'dean_name'      => 'nullable|string|max:255',
            'status'         => 'boolean',
        ]);

        $faculty->update($validated);

        return response()->json(['message' => 'Fakultas berhasil diupdate.', 'data' => $faculty->fresh('institution')]);
    }

    public function destroy(Faculty $faculty): JsonResponse
    {
        if ($faculty->studyPrograms()->count() > 0) {
            return response()->json(['message' => 'Tidak dapat menghapus fakultas yang masih memiliki program studi.'], 422);
        }

        $faculty->delete();

        return response()->json(['message' => 'Fakultas berhasil dihapus.']);
    }

    public function all(Request $request): JsonResponse
    {
        return response()->json(
            Faculty::where('status', true)
                ->when($request->institution_id, fn($q) => $q->where('institution_id', $request->institution_id))
                ->select('id', 'code', 'name', 'institution_id')
                ->get()
        );
    }
}
