<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Concentration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConcentrationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = Concentration::with('studyProgram')
            ->when($request->study_program_id, fn($q) => $q->where('study_program_id', $request->study_program_id))
            ->when($request->search, fn($q) => $q->where('name', 'like', "%{$request->search}%"))
            ->paginate($request->per_page ?? 15);

        return response()->json($data);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'study_program_id' => 'required|exists:study_programs,id',
            'code'             => 'required|string|max:20|unique:concentrations',
            'name'             => 'required|string|max:255',
            'description'      => 'nullable|string',
            'status'           => 'boolean',
        ]);

        $data = Concentration::create($validated);

        return response()->json(['message' => 'Konsentrasi berhasil ditambahkan.', 'data' => $data->load('studyProgram')], 201);
    }

    public function show(Concentration $concentration): JsonResponse
    {
        return response()->json($concentration->load('studyProgram'));
    }

    public function update(Request $request, Concentration $concentration): JsonResponse
    {
        $validated = $request->validate([
            'study_program_id' => 'sometimes|exists:study_programs,id',
            'code'             => "sometimes|string|max:20|unique:concentrations,code,{$concentration->id}",
            'name'             => 'sometimes|string|max:255',
            'description'      => 'nullable|string',
            'status'           => 'boolean',
        ]);

        $concentration->update($validated);

        return response()->json(['message' => 'Konsentrasi berhasil diupdate.', 'data' => $concentration->fresh('studyProgram')]);
    }

    public function destroy(Concentration $concentration): JsonResponse
    {
        $concentration->delete();
        return response()->json(['message' => 'Konsentrasi berhasil dihapus.']);
    }

    public function all(Request $request): JsonResponse
    {
        return response()->json(
            Concentration::where('status', true)
                ->when($request->study_program_id, fn($q) => $q->where('study_program_id', $request->study_program_id))
                ->select('id', 'code', 'name', 'study_program_id')
                ->get()
        );
    }
}
