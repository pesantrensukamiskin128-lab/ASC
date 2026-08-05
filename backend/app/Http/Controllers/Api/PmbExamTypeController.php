<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PmbExamType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PmbExamTypeController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(PmbExamType::orderBy('name')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code'          => 'required|string|max:30|unique:pmb_exam_types',
            'name'          => 'required|string|max:100',
            'weight'        => 'nullable|integer|min:0|max:100',
            'passing_grade' => 'nullable|integer|min:0|max:100',
            'is_active'     => 'boolean',
        ]);

        $type = PmbExamType::create($validated);
        return response()->json(['message' => 'Jenis ujian berhasil ditambahkan.', 'data' => $type], 201);
    }

    public function update(Request $request, PmbExamType $pmbExamType): JsonResponse
    {
        $validated = $request->validate([
            'code'          => "sometimes|string|max:30|unique:pmb_exam_types,code,{$pmbExamType->id}",
            'name'          => 'sometimes|string|max:100',
            'weight'        => 'nullable|integer|min:0|max:100',
            'passing_grade' => 'nullable|integer|min:0|max:100',
            'is_active'     => 'boolean',
        ]);

        $pmbExamType->update($validated);
        return response()->json(['message' => 'Jenis ujian berhasil diupdate.', 'data' => $pmbExamType->fresh()]);
    }

    public function destroy(PmbExamType $pmbExamType): JsonResponse
    {
        $pmbExamType->delete();
        return response()->json(['message' => 'Jenis ujian berhasil dihapus.']);
    }
}
