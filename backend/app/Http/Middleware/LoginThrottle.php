<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LoginThrottle
{
    public function __construct(private RateLimiter $limiter) {}

    /**
     * Batasi percobaan login: 5 kali per menit per IP+email
     */
    public function handle(Request $request, Closure $next): Response
    {
        $key = 'login:' . $request->ip() . '|' . strtolower($request->input('email', ''));
        $maxAttempts = 5;
        $decayMinutes = 1;

        if ($this->limiter->tooManyAttempts($key, $maxAttempts)) {
            $seconds = $this->limiter->availableIn($key);
            return response()->json([
                'message' => "Terlalu banyak percobaan login. Coba lagi dalam {$seconds} detik.",
                'retry_after' => $seconds,
            ], 429);
        }

        $response = $next($request);

        // Hanya hitung jika login gagal (status bukan 2xx)
        if ($response->getStatusCode() >= 400) {
            $this->limiter->hit($key, $decayMinutes * 60);
        } else {
            $this->limiter->clear($key);
        }

        return $response;
    }
}
