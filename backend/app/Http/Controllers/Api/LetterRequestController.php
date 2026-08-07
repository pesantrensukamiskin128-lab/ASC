<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LetterRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LetterRequestController extends Controller
{
    /** Daftar pengajuan — admin lihat semua, user lihat miliknya */
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();
        $query = LetterRequest::with(['requester', 'letterType', 'processor']);

        if (!$user->hasRole('SUPER_ADMIN') && !$user->hasRole('ADMIN_UMUM') && !$user->hasRole('ADMIN_AKADEMIK')) {
            $query->where('requested_by', $user->id);
        }

        $data = $query
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->orderByDesc('created_at')
            ->paginate($request->per_page ?? 15);

        return response()->json($data);
    }

    /** Mahasiswa/Dosen mengajukan pembuatan surat */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'letter_type_id' => 'nullable|exists:letter_types,id',
            'purpose'        => 'required|string|max:500',
            'description'    => 'nullable|string',
        ]);

        $validated['requested_by'] = auth()->id();

        $lr = LetterRequest::create($validated);

        return response()->json(['message' => 'Pengajuan surat berhasil dikirim.', 'data' => $lr->load('letterType')], 201);
    }

    /** Admin proses pengajuan (terima/tolak) */
    public function process(Request $request, LetterRequest $letterRequest): JsonResponse
    {
        $validated = $request->validate([
            'status'     => 'required|in:DIPROSES,SELESAI,DITOLAK',
            'admin_note' => 'nullable|string',
        ]);

        $letterRequest->update([
            'status'       => $validated['status'],
            'admin_note'   => $validated['admin_note'] ?? null,
            'processed_by' => auth()->id(),
        ]);

        return response()->json(['message' => 'Pengajuan berhasil diproses.', 'data' => $letterRequest->fresh()]);
    }
}
