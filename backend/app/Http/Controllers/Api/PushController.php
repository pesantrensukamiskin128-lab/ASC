<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PushSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushController extends Controller
{
    /** Return VAPID public key untuk frontend subscribe */
    public function vapidKey(): JsonResponse
    {
        return response()->json([
            'publicKey' => config('webpush.public_key'),
        ]);
    }

    /** Subscribe user ke push notification */
    public function subscribe(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'endpoint' => 'required|string|max:500',
            'keys.p256dh' => 'required|string',
            'keys.auth'   => 'required|string',
        ]);

        PushSubscription::updateOrCreate(
            ['endpoint' => $validated['endpoint']],
            [
                'user_id' => auth()->id(),
                'p256dh'  => $validated['keys']['p256dh'],
                'auth'    => $validated['keys']['auth'],
            ]
        );

        return response()->json(['message' => 'Push notification berhasil diaktifkan.']);
    }

    /** Unsubscribe */
    public function unsubscribe(Request $request): JsonResponse
    {
        $request->validate(['endpoint' => 'required|string']);

        PushSubscription::where('endpoint', $request->endpoint)
            ->where('user_id', auth()->id())
            ->delete();

        return response()->json(['message' => 'Push notification dinonaktifkan.']);
    }
}
