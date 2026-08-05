<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Building;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BuildingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = Building::with('institution')
            ->when($request->search, fn($q) => $q->where('name', 'like', "%{$request->search}%"))
            ->withCount('rooms')
            ->paginate($request->per_page ?? 15);

        return response()->json($data);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'institution_id' => 'required|exists:institutions,id',
            'code'           => 'required|string|max:20|unique:buildings',
            'name'           => 'required|string|max:255',
            'floors'         => 'nullable|integer|min:1',
            'address'        => 'nullable|string',
            'status'         => 'boolean',
        ]);

        $building = Building::create($validated);

        return response()->json(['message' => 'Gedung berhasil ditambahkan.', 'data' => $building->load('institution')], 201);
    }

    public function show(Building $building): JsonResponse
    {
        return response()->json($building->load(['institution', 'rooms']));
    }

    public function update(Request $request, Building $building): JsonResponse
    {
        $validated = $request->validate([
            'institution_id' => 'sometimes|exists:institutions,id',
            'code'           => "sometimes|string|max:20|unique:buildings,code,{$building->id}",
            'name'           => 'sometimes|string|max:255',
            'floors'         => 'nullable|integer|min:1',
            'address'        => 'nullable|string',
            'status'         => 'boolean',
        ]);

        $building->update($validated);

        return response()->json(['message' => 'Gedung berhasil diupdate.', 'data' => $building->fresh('institution')]);
    }

    public function destroy(Building $building): JsonResponse
    {
        if ($building->rooms()->count() > 0) {
            return response()->json(['message' => 'Tidak dapat menghapus gedung yang masih memiliki ruangan.'], 422);
        }

        $building->delete();
        return response()->json(['message' => 'Gedung berhasil dihapus.']);
    }

    public function all(): JsonResponse
    {
        return response()->json(
            Building::where('status', true)->select('id', 'code', 'name', 'floors')->get()
        );
    }
}
