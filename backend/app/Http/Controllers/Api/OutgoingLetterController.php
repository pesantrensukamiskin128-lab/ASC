<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LetterType;
use App\Models\OutgoingLetter;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OutgoingLetterController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            $query = OutgoingLetter::with(['letterType', 'creator', 'reviewer', 'signer']);

            // Admin Umum & Super Admin lihat semua
            if (!$user->hasRole('SUPER_ADMIN') && !$user->hasRole('ADMIN_UMUM')) {
                $query->where(function ($q) use ($user) {
                    $q->where('reviewer_id', $user->id)
                      ->orWhere('signer_id', $user->id)
                      ->orWhereHas('internalRecipients', fn($r) => $r->where('user_id', $user->id));
                });
            }

            $data = $query
                ->when($request->status, fn($q) => $q->where('status', $request->status))
                ->when($request->search, fn($q) => $q->where('subject', 'like', "%{$request->search}%"))
                ->orderByDesc('created_at')
                ->paginate($request->per_page ?? 15);

            return response()->json($data);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('OutgoingLetter index error: ' . $e->getMessage());
            return response()->json(['message' => 'Gagal memuat data: ' . $e->getMessage()], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'letter_type_id'      => 'required|exists:letter_types,id',
            'subject'             => 'required|string|max:500',
            'recipient'           => 'required|string',
            'attachment_note'     => 'nullable|string|max:255',
            'city'                => 'nullable|string|max:100',
            'letter_date'         => 'required|date',
            'body'                => 'required|string',
            'appendix_body'       => 'nullable|string',
            'reviewer_id'         => 'nullable|exists:users,id',
            'signer_id'           => 'required|exists:users,id',
            'external_recipients' => 'nullable|string',
        ]);

        try {
            $validated['created_by'] = auth()->id();
            $validated['status'] = 'DRAFT';

            $letter = OutgoingLetter::create($validated);

            return response()->json(['message' => 'Surat berhasil dibuat.', 'data' => $letter->load(['letterType', 'reviewer', 'signer'])], 201);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('OutgoingLetter store error: ' . $e->getMessage());
            return response()->json(['message' => 'Gagal membuat surat: ' . $e->getMessage()], 500);
        }
    }

    public function show(OutgoingLetter $outgoingLetter): JsonResponse
    {
        return response()->json($outgoingLetter->load([
            'letterType', 'creator', 'reviewer', 'signer', 'internalRecipients',
        ]));
    }

    public function update(Request $request, OutgoingLetter $outgoingLetter): JsonResponse
    {
        if (!in_array($outgoingLetter->status, ['DRAFT', 'REVISI_PEMERIKSA', 'REVISI_PENANDATANGAN'])) {
            return response()->json(['message' => 'Surat hanya bisa diedit saat status Draft/Revisi.'], 422);
        }

        $validated = $request->validate([
            'letter_type_id'      => 'sometimes|exists:letter_types,id',
            'subject'             => 'sometimes|string|max:500',
            'recipient'           => 'sometimes|string',
            'attachment_note'     => 'nullable|string|max:255',
            'city'                => 'nullable|string|max:100',
            'letter_date'         => 'sometimes|date',
            'body'                => 'sometimes|string',
            'appendix_body'       => 'nullable|string',
            'reviewer_id'         => 'nullable|exists:users,id',
            'signer_id'           => 'sometimes|exists:users,id',
            'external_recipients' => 'nullable|string',
        ]);

        $outgoingLetter->update($validated);

        return response()->json(['message' => 'Surat berhasil diupdate.', 'data' => $outgoingLetter->fresh()]);
    }

    /** Admin kirim surat ke pemeriksa atau langsung ke penandatangan */
    public function send(OutgoingLetter $outgoingLetter): JsonResponse
    {
        if (!in_array($outgoingLetter->status, ['DRAFT', 'REVISI_PEMERIKSA', 'REVISI_PENANDATANGAN'])) {
            return response()->json(['message' => 'Surat tidak dalam status yang bisa dikirim.'], 422);
        }

        $outgoingLetter->status = $outgoingLetter->reviewer_id
            ? 'MENUNGGU_PEMERIKSA'
            : 'MENUNGGU_PENANDATANGAN';
        $outgoingLetter->revision_note = null;
        $outgoingLetter->save();

        return response()->json(['message' => 'Surat berhasil dikirim.', 'data' => $outgoingLetter->fresh()]);
    }

    /** Pemeriksa approve surat */
    public function review(Request $request, OutgoingLetter $outgoingLetter): JsonResponse
    {
        if ($outgoingLetter->status !== 'MENUNGGU_PEMERIKSA') {
            return response()->json(['message' => 'Surat tidak dalam status menunggu pemeriksaan.'], 422);
        }

        $action = $request->input('action'); // 'approve' | 'revise'

        if ($action === 'revise') {
            $request->validate(['revision_note' => 'required|string']);
            $outgoingLetter->update([
                'status'        => 'REVISI_PEMERIKSA',
                'revision_note' => $request->revision_note,
            ]);
            return response()->json(['message' => 'Surat dikembalikan untuk revisi.', 'data' => $outgoingLetter->fresh()]);
        }

        $outgoingLetter->update([
            'status'      => 'MENUNGGU_PENANDATANGAN',
            'reviewed_at' => now(),
        ]);

        return response()->json(['message' => 'Surat diperiksa dan diteruskan ke penandatangan.', 'data' => $outgoingLetter->fresh()]);
    }

    /** Penandatangan TTD surat */
    public function sign(Request $request, OutgoingLetter $outgoingLetter): JsonResponse
    {
        if ($outgoingLetter->status !== 'MENUNGGU_PENANDATANGAN') {
            return response()->json(['message' => 'Surat tidak dalam status menunggu tanda tangan.'], 422);
        }

        $action = $request->input('action'); // 'sign' | 'revise'

        if ($action === 'revise') {
            $request->validate(['revision_note' => 'required|string']);
            $outgoingLetter->update([
                'status'        => 'REVISI_PENANDATANGAN',
                'revision_note' => $request->revision_note,
            ]);
            return response()->json(['message' => 'Surat dikembalikan untuk revisi.', 'data' => $outgoingLetter->fresh()]);
        }

        // Generate nomor surat & tandatangani
        $outgoingLetter->update([
            'status'        => 'DITANDATANGANI',
            'signed_at'     => now(),
            'letter_number' => $outgoingLetter->generateNumber(),
        ]);

        return response()->json(['message' => 'Surat berhasil ditandatangani.', 'data' => $outgoingLetter->fresh()]);
    }

    /** Admin kirim surat ke penerima internal / eksternal */
    public function distribute(Request $request, OutgoingLetter $outgoingLetter): JsonResponse
    {
        if ($outgoingLetter->status !== 'DITANDATANGANI') {
            return response()->json(['message' => 'Surat belum ditandatangani.'], 422);
        }

        $validated = $request->validate([
            'internal_recipient_ids'   => 'nullable|array',
            'internal_recipient_ids.*' => 'exists:users,id',
            'external_recipients'      => 'nullable|string',
        ]);

        if (!empty($validated['internal_recipient_ids'])) {
            $outgoingLetter->internalRecipients()->syncWithoutDetaching($validated['internal_recipient_ids']);
        }

        if (isset($validated['external_recipients'])) {
            $outgoingLetter->external_recipients = $validated['external_recipients'];
        }

        $outgoingLetter->status = 'TERKIRIM';
        $outgoingLetter->sent_at = now();
        $outgoingLetter->save();

        return response()->json(['message' => 'Surat berhasil didistribusikan.', 'data' => $outgoingLetter->fresh()]);
    }

    /** Daftar jenis surat */
    public function letterTypes(): JsonResponse
    {
        try {
            return response()->json(LetterType::where('is_active', true)->get());
        } catch (\Exception $e) {
            return response()->json(['message' => 'Tabel letter_types belum tersedia: ' . $e->getMessage()], 500);
        }
    }

    /** Daftar user yang bisa jadi penandatangan (punya jabatan struktural) */
    public function signers(): JsonResponse
    {
        try {
            $signers = \App\Models\LecturerPosition::where('is_active', true)
                ->whereIn('position_code', ['KETUA','REKTOR','WK1','WR1','WK2','WR2','WK3','WR3','DEKAN','WADEK1','KAPRODI'])
                ->with('lecturer.user')
                ->get()
                ->map(fn($p) => [
                    'user_id'       => $p->lecturer?->user?->id,
                    'name'          => $p->lecturer?->display_name,
                    'position_name' => $p->position_name,
                    'position_code' => $p->position_code,
                ])
                ->filter(fn($s) => $s['user_id'] !== null)
                ->values();

            return response()->json($signers);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function destroy(OutgoingLetter $outgoingLetter): JsonResponse
    {
        if (!in_array($outgoingLetter->status, ['DRAFT', 'REVISI_PEMERIKSA', 'REVISI_PENANDATANGAN'])) {
            return response()->json(['message' => 'Hanya surat berstatus Draft/Revisi yang bisa dihapus.'], 422);
        }
        $outgoingLetter->delete();
        return response()->json(['message' => 'Surat berhasil dihapus.']);
    }
}
