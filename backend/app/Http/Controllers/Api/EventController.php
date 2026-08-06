<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventAttendance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = Event::withCount('attendances')
            ->when($request->search, fn($q) => $q->where('title', 'like', "%{$request->search}%"))
            ->when($request->category, fn($q) => $q->where('category', $request->category))
            ->orderByDesc('event_date')
            ->paginate($request->per_page ?? 15);

        return response()->json($data);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title'        => 'required|string|max:255',
            'organizer'    => 'nullable|string|max:255',
            'category'     => 'required|in:Rapat,Seminar,Workshop,Pelatihan,Wisuda,Dies Natalis,Lainnya',
            'type'         => 'required|in:Luring,Daring,Hibrid',
            'location'     => 'nullable|string|max:255',
            'meeting_link' => 'nullable|url|max:500',
            'event_date'   => 'required|date',
            'start_time'   => 'nullable|date_format:H:i',
            'end_time'     => 'nullable|date_format:H:i',
            'description'  => 'nullable|string',
            'invitee_ids'  => 'nullable|array',
            'invitee_ids.*'=> 'exists:users,id',
        ]);

        $validated['created_by'] = auth()->id();
        $event = Event::create($validated);

        if (!empty($validated['invitee_ids'])) {
            $event->invitees()->attach($validated['invitee_ids']);
        }

        return response()->json(['message' => 'Agenda berhasil dibuat.', 'data' => $event], 201);
    }

    public function show(Event $event): JsonResponse
    {
        return response()->json($event->load([
            'creator', 'invitees', 'attendances.user',
        ])->loadCount('attendances'));
    }

    public function update(Request $request, Event $event): JsonResponse
    {
        $validated = $request->validate([
            'title'        => 'sometimes|string|max:255',
            'organizer'    => 'nullable|string|max:255',
            'category'     => 'sometimes|in:Rapat,Seminar,Workshop,Pelatihan,Wisuda,Dies Natalis,Lainnya',
            'type'         => 'sometimes|in:Luring,Daring,Hibrid',
            'location'     => 'nullable|string|max:255',
            'meeting_link' => 'nullable|url|max:500',
            'event_date'   => 'sometimes|date',
            'start_time'   => 'nullable|date_format:H:i',
            'end_time'     => 'nullable|date_format:H:i',
            'description'  => 'nullable|string',
            'is_open'      => 'sometimes|boolean',
            'invitee_ids'  => 'nullable|array',
            'invitee_ids.*'=> 'exists:users,id',
        ]);

        $event->update($validated);

        if (isset($validated['invitee_ids'])) {
            $event->invitees()->sync($validated['invitee_ids']);
        }

        return response()->json(['message' => 'Agenda berhasil diupdate.', 'data' => $event->fresh()]);
    }

    /** Presensi via aplikasi (user login) */
    public function attend(Request $request, string $qrToken): JsonResponse
    {
        $event = Event::where('qr_token', $qrToken)->firstOrFail();

        if (!$event->is_open) {
            return response()->json(['message' => 'Presensi untuk agenda ini sudah ditutup.'], 422);
        }

        $userId = auth()->id();

        // Cek sudah presensi
        $exists = EventAttendance::where('event_id', $event->id)
            ->where('user_id', $userId)
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'Anda sudah tercatat hadir di agenda ini.'], 422);
        }

        EventAttendance::create([
            'event_id'    => $event->id,
            'user_id'     => $userId,
            'method'      => 'APP',
            'attended_at' => now(),
        ]);

        return response()->json(['message' => 'Presensi berhasil dicatat.', 'event' => $event->only('title', 'event_date')]);
    }

    /** Presensi via form publik (tamu tanpa login) */
    public function attendPublic(Request $request, string $qrToken): JsonResponse
    {
        $event = Event::where('qr_token', $qrToken)->firstOrFail();

        if (!$event->is_open) {
            return response()->json(['message' => 'Presensi untuk agenda ini sudah ditutup.'], 422);
        }

        $validated = $request->validate([
            'guest_name'        => 'required|string|max:255',
            'guest_phone'       => 'nullable|string|max:20',
            'guest_institution' => 'nullable|string|max:255',
            'guest_position'    => 'nullable|string|max:255',
        ]);

        EventAttendance::create(array_merge($validated, [
            'event_id'    => $event->id,
            'method'      => 'FORM',
            'attended_at' => now(),
        ]));

        return response()->json(['message' => 'Kehadiran berhasil dicatat. Terima kasih!', 'event' => $event->only('title', 'event_date')]);
    }

    /** Info agenda via QR token (publik, untuk halaman presensi) */
    public function getByToken(string $qrToken): JsonResponse
    {
        $event = Event::where('qr_token', $qrToken)->first();
        if (!$event) {
            return response()->json(['message' => 'Agenda tidak ditemukan.'], 404);
        }
        return response()->json($event->only('id', 'title', 'organizer', 'category', 'type', 'location', 'event_date', 'start_time', 'end_time', 'is_open'));
    }

    public function destroy(Event $event): JsonResponse
    {
        $event->delete();
        return response()->json(['message' => 'Agenda berhasil dihapus.']);
    }

    /** Toggle buka/tutup presensi */
    public function toggleOpen(Event $event): JsonResponse
    {
        $event->update(['is_open' => !$event->is_open]);
        return response()->json([
            'message' => $event->is_open ? 'Presensi dibuka.' : 'Presensi ditutup.',
            'data'    => $event->fresh(),
        ]);
    }
}
