<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LmsConfig;
use App\Models\LmsSyncLog;
use App\Services\LmsSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LmsIntegrationController extends Controller
{
    /** Get konfigurasi LMS */
    public function config(): JsonResponse
    {
        $config = LmsConfig::first();
        return response()->json([
            'config' => $config ? [
                'id'           => $config->id,
                'base_url'     => $config->base_url,
                'is_active'    => $config->is_active,
                'last_sync_at' => $config->last_sync_at,
                'has_token'    => !empty($config->attributes['api_token']),
            ] : null,
        ]);
    }

    /** Simpan/update konfigurasi LMS */
    public function saveConfig(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'base_url'  => 'required|url|max:500',
            'api_token' => 'required|string|min:10',
            'is_active' => 'boolean',
        ]);

        $config = LmsConfig::first();
        if ($config) {
            $config->update($validated);
        } else {
            $config = LmsConfig::create($validated);
        }

        return response()->json(['message' => 'Konfigurasi LMS berhasil disimpan.']);
    }

    /** Test koneksi ke LMS */
    public function testConnection(): JsonResponse
    {
        try {
            $config = LmsConfig::where('is_active', true)->firstOrFail();
            $url = rtrim($config->base_url, '/') . '/health';

            $response = \Illuminate\Support\Facades\Http::withToken($config->getDecryptedToken())
                ->acceptJson()
                ->withoutVerifying()
                ->timeout(15)
                ->get($url);

            if ($response->successful()) {
                $body = $response->json();
                return response()->json([
                    'message' => 'Koneksi ke LMS berhasil!',
                    'status'  => 'ok',
                    'response' => $body,
                    'url'     => $url,
                ]);
            }

            return response()->json([
                'message' => 'LMS merespons dengan HTTP ' . $response->status(),
                'body'    => substr($response->body(), 0, 300),
            ], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal terhubung: ' . $e->getMessage()], 422);
        }
    }

    /** Jalankan sinkronisasi semua data */
    public function syncAll(): JsonResponse
    {
        try {
            $service = new LmsSyncService();
            $results = $service->syncAll(auth()->id());

            return response()->json([
                'message' => 'Sinkronisasi selesai.',
                'results' => collect($results)->map(fn($log) => [
                    'type'         => $log->sync_type,
                    'status'       => $log->status,
                    'total'        => $log->total_items,
                    'synced'       => $log->synced_items,
                    'failed'       => $log->failed_items,
                    'duration_ms'  => $log->duration_ms,
                    'errors'       => $log->errors,
                ]),
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal sinkronisasi: ' . $e->getMessage()], 500);
        }
    }

    /** Sinkronisasi per tipe */
    public function syncType(string $type): JsonResponse
    {
        $allowed = ['users', 'courses', 'classes', 'enrollments'];
        if (!in_array($type, $allowed)) {
            return response()->json(['message' => 'Tipe sync tidak valid.'], 422);
        }

        try {
            $service = new LmsSyncService();
            $method = 'sync' . ucfirst($type);
            $log = $service->$method(auth()->id());

            return response()->json([
                'message' => "Sinkronisasi {$type} selesai.",
                'result'  => [
                    'type'        => $log->sync_type,
                    'status'      => $log->status,
                    'total'       => $log->total_items,
                    'synced'      => $log->synced_items,
                    'failed'      => $log->failed_items,
                    'duration_ms' => $log->duration_ms,
                    'errors'      => $log->errors,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal: ' . $e->getMessage()], 500);
        }
    }

    /** Log sinkronisasi */
    public function logs(Request $request): JsonResponse
    {
        $logs = LmsSyncLog::with('triggeredBy')
            ->orderByDesc('created_at')
            ->paginate($request->per_page ?? 20);

        return response()->json($logs);
    }
}
