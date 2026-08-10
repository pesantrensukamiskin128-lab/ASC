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
        $user = auth()->user();
        $canManage = $user->can('agenda.create');

        $query = Event::withCount('attendances')
            ->when($request->search, fn($q) => $q->where('title', 'like', "%{$request->search}%"))
            ->when($request->category, fn($q) => $q->where('category', $request->category));

        // Non-admin: hanya lihat agenda yang diundang atau dibuat sendiri
        if (!$canManage) {
            $query->where(function ($q) use ($user) {
                $q->where('created_by', $user->id)
                  ->orWhereHas('invitees', fn($sub) => $sub->where('user_id', $user->id));
            });
        }

        $data = $query->orderByDesc('event_date')
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
        $user = auth()->user();
        $canManage = $user->can('agenda.create');

        // Non-admin: hanya bisa lihat jika diundang atau pembuat
        if (!$canManage && $event->created_by !== $user->id) {
            $isInvited = $event->invitees()->where('user_id', $user->id)->exists();
            if (!$isInvited) {
                return response()->json(['message' => 'Anda tidak memiliki akses ke agenda ini.'], 403);
            }
        }

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

    /** Riwayat kehadiran user yang sedang login */
    public function myAttendance(): JsonResponse
    {
        $userId = auth()->id();

        $data = EventAttendance::where('user_id', $userId)
            ->with('event:id,title,event_date,location,category')
            ->orderByDesc('attended_at')
            ->limit(50)->get()
            ->map(fn($a) => [
                'id' => $a->id,
                'event_id' => $a->event_id,
                'event_title' => $a->event?->title,
                'event_date' => $a->event?->event_date?->format('Y-m-d'),
                'location' => $a->event?->location,
                'category' => $a->event?->category,
                'attended_at' => $a->attended_at?->format('Y-m-d H:i'),
                'method' => $a->method,
            ]);

        return response()->json($data);
    }

    /** Generate QR code presensi dengan logo (base64) */
    public function qrCode(Event $event): JsonResponse
    {
        $frontendUrl = rtrim(config('app.frontend_url'), '/');
        $url = $frontendUrl . '/presensi/' . $event->qr_token;
        $qr = \App\Helpers\QrCodeHelper::generateWithLogo($url, 400);

        return response()->json([
            'qr_image' => $qr,
            'url'      => $url,
            'event'    => $event->only('id', 'title', 'event_date', 'start_time', 'end_time', 'location', 'organizer'),
        ]);
    }

    /** Download daftar hadir sebagai Excel */
    public function exportExcel(Event $event)
    {
        $filename = 'Daftar-Hadir-' . preg_replace('/[^a-zA-Z0-9]/', '-', $event->title) . '.xlsx';
        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\EventAttendanceExport($event),
            $filename
        );
    }

    /** Download daftar hadir sebagai PDF */
    public function exportPdf(Event $event)
    {
        $attendances = EventAttendance::where('event_id', $event->id)
            ->with('user')
            ->orderBy('attended_at')
            ->get();

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.event-attendance', [
            'event' => $event,
            'attendances' => $attendances,
        ])->setPaper('a4', 'portrait');

        $filename = 'Daftar-Hadir-' . preg_replace('/[^a-zA-Z0-9]/', '-', $event->title) . '.pdf';
        return $pdf->download($filename);
    }
}
