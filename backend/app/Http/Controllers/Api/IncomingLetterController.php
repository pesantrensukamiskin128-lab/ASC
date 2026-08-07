<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Disposition;
use App\Models\IncomingLetter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class IncomingLetterController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = IncomingLetter::with('creator')
            ->when($request->search, fn($q) => $q->where('subject', 'like', "%{$request->search}%")
                ->orWhere('sender', 'like', "%{$request->search}%"))
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->orderByDesc('received_date')
            ->paginate($request->per_page ?? 15);

        return response()->json($data);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'letter_number' => 'nullable|string|max:100',
            'sender'        => 'required|string|max:255',
            'subject'       => 'required|string|max:500',
            'letter_date'   => 'nullable|date',
            'received_date' => 'required|date',
            'notes'         => 'nullable|string',
            'file'          => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240',
        ]);

        $validated['created_by'] = auth()->id();

        if ($request->hasFile('file')) {
            $validated['file_path'] = $request->file('file')->store('incoming-letters', 'public');
        }
        unset($validated['file']);

        $letter = IncomingLetter::create($validated);

        return response()->json(['message' => 'Surat masuk berhasil dicatat.', 'data' => $letter], 201);
    }

    public function show(IncomingLetter $incomingLetter): JsonResponse
    {
        // Tandai dibaca
        if ($incomingLetter->status === 'BARU') {
            $incomingLetter->update(['status' => 'DIBACA']);
        }

        return response()->json($incomingLetter->load([
            'creator',
            'dispositions.creator',
            'dispositions.recipients',
        ]));
    }

    /** Buat disposisi untuk surat masuk */
    public function createDisposition(Request $request, IncomingLetter $incomingLetter): JsonResponse
    {
        $validated = $request->validate([
            'instruction'     => 'required|string',
            'notes'           => 'nullable|string',
            'recipient_ids'   => 'required|array|min:1',
            'recipient_ids.*' => 'exists:users,id',
        ]);

        $disposition = Disposition::create([
            'incoming_letter_id' => $incomingLetter->id,
            'created_by'         => auth()->id(),
            'instruction'        => $validated['instruction'],
            'notes'              => $validated['notes'] ?? null,
        ]);

        $disposition->recipients()->attach($validated['recipient_ids']);

        $incomingLetter->update(['status' => 'DIDISPOSISI']);

        // Notifikasi ke setiap penerima disposisi
        foreach ($validated['recipient_ids'] as $recipientId) {
            \App\Models\AppNotification::send($recipientId, 'Disposisi Baru', "Anda menerima disposisi: \"{$validated['instruction']}\" untuk surat dari {$incomingLetter->sender}.", 'warning', '/persuratan/disposisi');
        }

        return response()->json(['message' => 'Disposisi berhasil dibuat.', 'data' => $disposition->load('recipients')], 201);
    }

    public function destroy(IncomingLetter $incomingLetter): JsonResponse
    {
        if ($incomingLetter->file_path) {
            Storage::disk('public')->delete($incomingLetter->file_path);
        }
        $incomingLetter->delete();
        return response()->json(['message' => 'Surat masuk berhasil dihapus.']);
    }
}
