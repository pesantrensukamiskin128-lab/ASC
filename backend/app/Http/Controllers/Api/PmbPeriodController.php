<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PmbPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PmbPeriodController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = PmbPeriod::with('academicYear')
            ->withCount('registrants')
            ->orderByDesc('registration_start')
            ->paginate($request->per_page ?? 15);

        return response()->json($data);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'academic_year_id'      => 'required|exists:academic_years,id',
            'name'                  => 'required|string|max:100',
            'registration_start'    => 'required|date',
            'registration_end'      => 'required|date|after:registration_start',
            'selection_date'        => 'nullable|date',
            'announcement_date'     => 'nullable|date',
            're_registration_start' => 'nullable|date',
            're_registration_end'   => 'nullable|date',
            'quota'                 => 'nullable|integer|min:0',
            'registration_fee'      => 'nullable|numeric|min:0',
            'is_active'             => 'boolean',
        ]);

        $period = PmbPeriod::create($validated);

        return response()->json(['message' => 'Periode PMB berhasil ditambahkan.', 'data' => $period->load('academicYear')], 201);
    }

    public function show(PmbPeriod $pmbPeriod): JsonResponse
    {
        return response()->json($pmbPeriod->load('academicYear'));
    }

    public function update(Request $request, PmbPeriod $pmbPeriod): JsonResponse
    {
        $validated = $request->validate([
            'academic_year_id'      => 'sometimes|exists:academic_years,id',
            'name'                  => 'sometimes|string|max:100',
            'registration_start'    => 'sometimes|date',
            'registration_end'      => 'sometimes|date',
            'selection_date'        => 'nullable|date',
            'announcement_date'     => 'nullable|date',
            're_registration_start' => 'nullable|date',
            're_registration_end'   => 'nullable|date',
            'quota'                 => 'nullable|integer|min:0',
            'registration_fee'      => 'nullable|numeric|min:0',
            'is_active'             => 'boolean',
        ]);

        $pmbPeriod->update($validated);

        return response()->json(['message' => 'Periode PMB berhasil diupdate.', 'data' => $pmbPeriod->fresh('academicYear')]);
    }

    public function destroy(PmbPeriod $pmbPeriod): JsonResponse
    {
        if ($pmbPeriod->registrants()->count() > 0) {
            return response()->json(['message' => 'Tidak dapat menghapus periode yang sudah memiliki pendaftar.'], 422);
        }
        $pmbPeriod->delete();
        return response()->json(['message' => 'Periode PMB berhasil dihapus.']);
    }

    public function all(): JsonResponse
    {
        return response()->json(
            PmbPeriod::select('id', 'name', 'is_active')->orderByDesc('registration_start')->get()
        );
    }
}
