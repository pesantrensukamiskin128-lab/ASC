<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClassOffering;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClassOfferingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = ClassOffering::with(['course.studyProgram', 'lecturer', 'room.building', 'academicYear'])
            ->when($request->academic_year_id, fn($q) => $q->where('academic_year_id', $request->academic_year_id))
            ->when($request->study_program_id, fn($q) => $q->whereHas('course', fn($q2) => $q2->where('study_program_id', $request->study_program_id)))
            ->when($request->course_id, fn($q) => $q->where('course_id', $request->course_id))
            ->when($request->search, fn($q) => $q->where('class_code', 'like', "%{$request->search}%")
                ->orWhereHas('course', fn($q2) => $q2->where('name', 'like', "%{$request->search}%")))
            ->paginate($request->per_page ?? 20);

        return response()->json($data);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'course_id'        => 'required|exists:courses,id',
            'academic_year_id' => 'required|exists:academic_years,id',
            'lecturer_id'      => 'required|exists:lecturers,id',
            'room_id'          => 'nullable|exists:rooms,id',
            'class_code'       => 'required|string|max:20',
            'max_students'     => 'nullable|integer|min:1',
            'day'              => 'nullable|in:Senin,Selasa,Rabu,Kamis,Jumat,Sabtu',
            'start_time'       => 'nullable|date_format:H:i',
            'end_time'         => 'nullable|date_format:H:i',
            'is_active'        => 'boolean',
        ]);

        // Cek duplikat
        $exists = ClassOffering::where('course_id', $validated['course_id'])
            ->where('academic_year_id', $validated['academic_year_id'])
            ->where('class_code', $validated['class_code'])
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'Kelas dengan kode tersebut sudah ada untuk mata kuliah ini.'], 422);
        }

        $offering = ClassOffering::create($validated);

        return response()->json([
            'message' => 'Kelas berhasil ditambahkan.',
            'data'    => $offering->load(['course', 'lecturer', 'room']),
        ], 201);
    }

    public function show(ClassOffering $classOffering): JsonResponse
    {
        return response()->json($classOffering->load(['course.studyProgram', 'lecturer', 'room.building', 'academicYear']));
    }

    public function update(Request $request, ClassOffering $classOffering): JsonResponse
    {
        $validated = $request->validate([
            'lecturer_id'  => 'sometimes|exists:lecturers,id',
            'room_id'      => 'nullable|exists:rooms,id',
            'max_students' => 'nullable|integer|min:1',
            'day'          => 'nullable|in:Senin,Selasa,Rabu,Kamis,Jumat,Sabtu',
            'start_time'   => 'nullable|date_format:H:i',
            'end_time'     => 'nullable|date_format:H:i',
            'is_active'    => 'boolean',
        ]);

        $classOffering->update($validated);

        return response()->json(['message' => 'Kelas berhasil diupdate.', 'data' => $classOffering->fresh(['course', 'lecturer'])]);
    }

    public function destroy(ClassOffering $classOffering): JsonResponse
    {
        if ($classOffering->krsDetails()->count() > 0) {
            return response()->json(['message' => 'Tidak dapat menghapus kelas yang sudah ada mahasiswa terdaftar.'], 422);
        }

        $classOffering->delete();
        return response()->json(['message' => 'Kelas berhasil dihapus.']);
    }

    public function all(Request $request): JsonResponse
    {
        return response()->json(
            ClassOffering::with(['course:id,code,name,credits', 'lecturer:id,name'])
                ->where('is_active', true)
                ->when($request->academic_year_id, fn($q) => $q->where('academic_year_id', $request->academic_year_id))
                ->when($request->study_program_id, fn($q) => $q->whereHas('course', fn($q2) => $q2->where('study_program_id', $request->study_program_id)))
                ->get(['id', 'class_code', 'course_id', 'lecturer_id', 'max_students', 'enrolled_count', 'day', 'start_time', 'end_time'])
        );
    }
}
