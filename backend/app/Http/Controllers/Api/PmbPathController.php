<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PmbPath;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PmbPathController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(PmbPath::orderBy('name')->paginate(50));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code'         => 'required|string|max:20|unique:pmb_paths',
            'name'         => 'required|string|max:100',
            'description'  => 'nullable|string',
            'requirements' => 'nullable|string',
            'is_active'    => 'boolean',
        ]);

        $path = PmbPath::create($validated);
        return response()->json(['message' => 'Jalur seleksi berhasil ditambahkan.', 'data' => $path], 201);
    }

    public function update(Request $request, PmbPath $pmbPath): JsonResponse
    {
        $validated = $request->validate([
            'code'         => "sometimes|string|max:20|unique:pmb_paths,code,{$pmbPath->id}",
            'name'         => 'sometimes|string|max:100',
            'description'  => 'nullable|string',
            'requirements' => 'nullable|string',
            'is_active'    => 'boolean',
        ]);

        $pmbPath->update($validated);
        return response()->json(['message' => 'Jalur seleksi berhasil diupdate.', 'data' => $pmbPath->fresh()]);
    }

    public function destroy(PmbPath $pmbPath): JsonResponse
    {
        $pmbPath->delete();
        return response()->json(['message' => 'Jalur seleksi berhasil dihapus.']);
    }
}
