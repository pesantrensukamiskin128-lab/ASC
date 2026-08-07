<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Disposition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DispositionController extends Controller
{
    /** Daftar disposisi yang diterima user saat ini */
    public function index(Request $request): JsonResponse
    {
        $userId = auth()->id();

        $dispositions = Disposition::with(['incomingLetter', 'creator'])
            ->whereHas('recipients', fn($q) => $q->where('user_id', $userId))
            ->orderByDesc('created_at')
            ->paginate($request->per_page ?? 15);

        // Tambah status baca per user
        $dispositions->getCollection()->transform(function ($d) use ($userId) {
            $pivot = DB::table('disposition_recipients')
                ->where('disposition_id', $d->id)
                ->where('user_id', $userId)
                ->first();
            $d->is_read = (bool) $pivot?->is_read;
            $d->my_response = $pivot?->response;
            $d->responded_at = $pivot?->responded_at;
            return $d;
        });

        return response()->json($dispositions);
    }

    /** Tandai disposisi sudah dibaca */
    public function markRead(Disposition $disposition): JsonResponse
    {
        DB::table('disposition_recipients')
            ->where('disposition_id', $disposition->id)
            ->where('user_id', auth()->id())
            ->update(['is_read' => true, 'read_at' => now()]);

        return response()->json(['message' => 'Disposisi ditandai sudah dibaca.']);
    }

    /** Jawab/tindak lanjut disposisi */
    public function respond(Request $request, Disposition $disposition): JsonResponse
    {
        $request->validate(['response' => 'required|string']);

        DB::table('disposition_recipients')
            ->where('disposition_id', $disposition->id)
            ->where('user_id', auth()->id())
            ->update([
                'response'     => $request->response,
                'responded_at' => now(),
                'is_read'      => true,
                'read_at'      => DB::raw('COALESCE(read_at, NOW())'),
            ]);

        // Notifikasi ke pembuat disposisi
        \App\Models\AppNotification::send($disposition->created_by, 'Disposisi Dijawab', auth()->user()->name . " menjawab disposisi: \"{$request->response}\"", 'info', '/persuratan/surat-masuk');

        return response()->json(['message' => 'Jawaban disposisi berhasil dikirim.']);
    }
}
