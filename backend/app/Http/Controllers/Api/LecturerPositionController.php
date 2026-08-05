<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lecturer;
use App\Models\LecturerPosition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LecturerPositionController extends Controller
{
    /** Daftar jabatan yang tersedia */
    public function availablePositions(): JsonResponse
    {
        return response()->json(LecturerPosition::POSITIONS);
    }

    /** List jabatan per dosen */
    public function index(Lecturer $lecturer): JsonResponse
    {
        return response()->json(
            $lecturer->positions()->orderByDesc('is_active')->orderByDesc('start_date')->get()
        );
    }

    /** Tambah jabatan ke dosen */
    public function store(Request $request, Lecturer $lecturer): JsonResponse
    {
        $validated = $request->validate([
            'position_code'  => 'required|string|max:30',
            'scope_type'     => 'nullable|in:study_program,faculty,institution',
            'scope_id'       => 'nullable|integer',
            'start_date'     => 'nullable|date',
            'end_date'       => 'nullable|date',
            'decree_number'  => 'nullable|string|max:100',
            'is_active'      => 'boolean',
        ]);

        $positionName = LecturerPosition::POSITIONS[$validated['position_code']] ?? $validated['position_code'];

        $position = $lecturer->positions()->create(array_merge($validated, [
            'position_name' => $positionName,
        ]));

        return response()->json([
            'message' => "Jabatan {$positionName} berhasil ditambahkan.",
            'data'    => $position,
        ], 201);
    }

    /** Update jabatan */
    public function update(Request $request, Lecturer $lecturer, LecturerPosition $position): JsonResponse
    {
        $validated = $request->validate([
            'position_code'  => 'sometimes|string|max:30',
            'scope_type'     => 'nullable|in:study_program,faculty,institution',
            'scope_id'       => 'nullable|integer',
            'start_date'     => 'nullable|date',
            'end_date'       => 'nullable|date',
            'decree_number'  => 'nullable|string|max:100',
            'is_active'      => 'boolean',
        ]);

        if (isset($validated['position_code'])) {
            $validated['position_name'] = LecturerPosition::POSITIONS[$validated['position_code']] ?? $validated['position_code'];
        }

        $position->update($validated);

        return response()->json(['message' => 'Jabatan berhasil diupdate.', 'data' => $position->fresh()]);
    }

    /** Hapus jabatan */
    public function destroy(Lecturer $lecturer, LecturerPosition $position): JsonResponse
    {
        $position->delete();
        return response()->json(['message' => 'Jabatan berhasil dihapus.']);
    }
}
