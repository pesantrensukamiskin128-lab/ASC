<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AcademicYearController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(
            AcademicYear::withCount('semesters')
                ->orderByDesc('start_date')
                ->paginate($request->per_page ?? 15)
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'       => 'required|string|max:100',
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after:start_date',
            'is_active'  => 'boolean',
        ]);

        $ay = AcademicYear::create($validated);

        if (!empty($validated['is_active'])) {
            $ay->setAsActive();
        }

        return response()->json([
            'message' => 'Tahun akademik berhasil ditambahkan.',
            'data'    => $ay,
        ], 201);
    }

    public function show(AcademicYear $academicYear): JsonResponse
    {
        return response()->json($academicYear->load('semesters'));
    }

    public function update(Request $request, AcademicYear $academicYear): JsonResponse
    {
        $validated = $request->validate([
            'name'       => 'sometimes|string|max:100',
            'start_date' => 'sometimes|date',
            'end_date'   => 'sometimes|date|after:start_date',
            'is_active'  => 'boolean',
        ]);

        $academicYear->update($validated);

        if (!empty($validated['is_active'])) {
            $academicYear->setAsActive();
        }

        return response()->json([
            'message' => 'Tahun akademik berhasil diupdate.',
            'data'    => $academicYear->fresh(),
        ]);
    }

    public function destroy(AcademicYear $academicYear): JsonResponse
    {
        if ($academicYear->is_active) {
            return response()->json(['message' => 'Tidak dapat menghapus tahun akademik yang sedang aktif.'], 422);
        }

        $academicYear->delete();

        return response()->json(['message' => 'Tahun akademik berhasil dihapus.']);
    }

    public function activate(AcademicYear $academicYear): JsonResponse
    {
        $academicYear->setAsActive();

        return response()->json([
            'message' => "{$academicYear->name} berhasil diaktifkan.",
            'data'    => $academicYear->fresh(),
        ]);
    }

    public function all(): JsonResponse
    {
        return response()->json(
            AcademicYear::orderByDesc('start_date')
                ->select('id', 'name', 'is_active')
                ->get()
        );
    }
}
