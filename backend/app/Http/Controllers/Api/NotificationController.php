<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /** List notifikasi user login */
    public function index(Request $request): JsonResponse
    {
        $query = AppNotification::where('user_id', auth()->id())
            ->when($request->unread_only, fn($q) => $q->where('is_read', false))
            ->orderByDesc('created_at');

        return response()->json($query->paginate($request->per_page ?? 20));
    }

    /** Jumlah unread */
    public function unreadCount(): JsonResponse
    {
        $count = AppNotification::where('user_id', auth()->id())->where('is_read', false)->count();
        return response()->json(['count' => $count]);
    }

    /** Mark satu notifikasi sebagai read */
    public function markAsRead(AppNotification $notification): JsonResponse
    {
        if ($notification->user_id !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }
        $notification->markAsRead();
        return response()->json(['message' => 'Ditandai sudah dibaca.']);
    }

    /** Mark semua sebagai read */
    public function markAllAsRead(): JsonResponse
    {
        AppNotification::where('user_id', auth()->id())
            ->where('is_read', false)
            ->update(['is_read' => true, 'read_at' => now()]);

        return response()->json(['message' => 'Semua notifikasi ditandai sudah dibaca.']);
    }

    /** Hapus satu notifikasi */
    public function destroy(AppNotification $notification): JsonResponse
    {
        if ($notification->user_id !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }
        $notification->delete();
        return response()->json(['message' => 'Notifikasi dihapus.']);
    }

    /** Admin: kirim notifikasi ke user(s) */
    public function send(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
            'title'    => 'required|string|max:255',
            'message'  => 'nullable|string',
            'type'     => 'nullable|in:info,warning,success,error',
            'link'     => 'nullable|string|max:500',
        ]);

        foreach ($validated['user_ids'] as $userId) {
            AppNotification::send($userId, $validated['title'], $validated['message'] ?? null, $validated['type'] ?? 'info', $validated['link'] ?? null);
        }

        return response()->json(['message' => 'Notifikasi berhasil dikirim ke ' . count($validated['user_ids']) . ' user.']);
    }
}
