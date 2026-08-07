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

        // Notifikasi ke semua Admin Umum
        $admins = \App\Models\User::role(['SUPER_ADMIN', 'ADMIN_UMUM'])->get();
        foreach ($admins as $admin) {
            \App\Models\AppNotification::send($admin->id, 'Pengajuan Surat Baru', auth()->user()->name . " mengajukan: \"{$validated['purpose']}\"", 'info', '/persuratan/pengajuan-masuk');
        }

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

        // Notifikasi ke pengaju
        $statusText = match ($validated['status']) {
            'DIPROSES' => 'sedang diproses',
            'SELESAI'  => 'sudah selesai',
            'DITOLAK'  => 'ditolak',
        };
        \App\Models\AppNotification::send($letterRequest->requested_by, 'Status Pengajuan Surat', "Pengajuan \"{$letterRequest->purpose}\" {$statusText}." . ($validated['admin_note'] ? " Catatan: {$validated['admin_note']}" : ''), $validated['status'] === 'DITOLAK' ? 'error' : 'success', '/persuratan/pengajuan');

        return response()->json(['message' => 'Pengajuan berhasil diproses.', 'data' => $letterRequest->fresh()]);
    }

    /** Edit pengajuan (hanya pemilik, status DIAJUKAN) */
    public function update(Request $request, LetterRequest $letterRequest): JsonResponse
    {
        $user = auth()->user();
        if ($letterRequest->requested_by !== $user->id && !$user->hasRole('SUPER_ADMIN')) {
            return response()->json(['message' => 'Tidak memiliki akses.'], 403);
        }
        if ($letterRequest->status !== 'DIAJUKAN') {
            return response()->json(['message' => 'Hanya pengajuan berstatus DIAJUKAN yang bisa diedit.'], 422);
        }

        $validated = $request->validate([
            'letter_type_id' => 'nullable|exists:letter_types,id',
            'purpose'        => 'required|string|max:500',
            'description'    => 'nullable|string',
        ]);

        $letterRequest->update($validated);
        return response()->json(['message' => 'Pengajuan berhasil diupdate.', 'data' => $letterRequest->fresh()]);
    }

    /** Hapus pengajuan (pemilik jika DIAJUKAN, admin bisa hapus semua) */
    public function destroy(LetterRequest $letterRequest): JsonResponse
    {
        $user = auth()->user();
        $isAdmin = $user->hasRole('SUPER_ADMIN') || $user->hasRole('ADMIN_UMUM') || $user->hasRole('ADMIN_AKADEMIK');

        if (!$isAdmin && $letterRequest->requested_by !== $user->id) {
            return response()->json(['message' => 'Tidak memiliki akses.'], 403);
        }
        if (!$isAdmin && $letterRequest->status !== 'DIAJUKAN') {
            return response()->json(['message' => 'Hanya pengajuan berstatus DIAJUKAN yang bisa dihapus.'], 422);
        }

        $letterRequest->delete();
        return response()->json(['message' => 'Pengajuan berhasil dihapus.']);
    }
}
