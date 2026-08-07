<?php

namespace App\Services;

use App\Models\PushSubscription;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class PushNotificationService
{
    /**
     * Kirim push notification ke semua subscription milik user.
     */
    public static function sendToUser(int $userId, string $title, ?string $body = null, ?string $url = null): void
    {
        $subscriptions = PushSubscription::where('user_id', $userId)->get();

        if ($subscriptions->isEmpty()) return;

        foreach ($subscriptions as $sub) {
            try {
                self::sendPush($sub, $title, $body, $url);
            } catch (\Exception $e) {
                Log::warning("Push failed for sub {$sub->id}: " . $e->getMessage());
                // Hapus subscription yang sudah expired/invalid
                if (str_contains($e->getMessage(), '410') || str_contains($e->getMessage(), '404')) {
                    $sub->delete();
                }
            }
        }
    }

    /**
     * Kirim push notification via Web Push Protocol.
     * Menggunakan VAPID authentication.
     */
    private static function sendPush(PushSubscription $sub, string $title, ?string $body, ?string $url): void
    {
        $vapidPublic = config('webpush.public_key');
        $vapidPrivate = config('webpush.private_key');
        $vapidSubject = config('webpush.subject');

        if (!$vapidPublic || !$vapidPrivate) {
            Log::warning('VAPID keys not configured. Skip push.');
            return;
        }

        $payload = json_encode([
            'title' => $title,
            'body'  => $body ?? '',
            'url'   => $url ?? '/',
            'icon'  => '/icons/pwa-192x192.png',
            'badge' => '/icons/pwa-192x192.png',
        ]);

        // Gunakan library web-push jika tersedia
        if (class_exists(\Minishlink\WebPush\WebPush::class)) {
            $webPush = new \Minishlink\WebPush\WebPush([
                'VAPID' => [
                    'subject'    => $vapidSubject,
                    'publicKey'  => $vapidPublic,
                    'privateKey' => $vapidPrivate,
                ],
            ]);

            $subscription = \Minishlink\WebPush\Subscription::create([
                'endpoint'        => $sub->endpoint,
                'publicKey'       => $sub->p256dh,
                'authToken'       => $sub->auth,
                'contentEncoding' => 'aesgcm',
            ]);

            $webPush->sendOneNotification($subscription, $payload);

            foreach ($webPush->flush() as $report) {
                if (!$report->isSuccess()) {
                    $statusCode = $report->getResponse()?->getStatusCode() ?? 0;
                    if (in_array($statusCode, [404, 410])) {
                        $sub->delete();
                    }
                    Log::warning("Push delivery failed: {$report->getReason()}");
                }
            }
        }
    }
}
