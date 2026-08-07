<?php

namespace App\Helpers;

use SimpleSoftwareIO\QrCode\Facades\QrCode;
use App\Models\Institution;

class QrCodeHelper
{
    /**
     * Generate QR code PNG dengan logo di tengah sebagai base64 data URI.
     * Warna: biru (#1e3a8a).
     */
    public static function generateWithLogo(string $data, int $size = 200): string
    {
        try {
            $logoPath = self::getLogoPath();

            if ($logoPath && file_exists($logoPath)) {
                $png = QrCode::format('png')
                    ->size($size)
                    ->color(30, 58, 138)
                    ->backgroundColor(255, 255, 255)
                    ->errorCorrection('H')
                    ->margin(1)
                    ->merge($logoPath, 0.2, true)
                    ->generate($data);

                return 'data:image/png;base64,' . base64_encode($png);
            }

            // Tanpa logo — fallback ke PNG biasa
            return self::generate($data, $size);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('QR with logo failed: ' . $e->getMessage());
            return self::generate($data, $size);
        }
    }

    /**
     * Generate QR code PNG tanpa logo sebagai base64 data URI.
     */
    public static function generate(string $data, int $size = 150): string
    {
        try {
            $png = QrCode::format('png')
                ->size($size)
                ->color(30, 58, 138)
                ->backgroundColor(255, 255, 255)
                ->errorCorrection('M')
                ->margin(1)
                ->generate($data);

            return 'data:image/png;base64,' . base64_encode($png);
        } catch (\Exception $e) {
            // Ultimate fallback: external API
            \Illuminate\Support\Facades\Log::warning('QR generate failed: ' . $e->getMessage());
            $color = '1e3a8a';
            return "https://api.qrserver.com/v1/create-qr-code/?size={$size}x{$size}&color={$color}&data=" . urlencode($data);
        }
    }

    private static function getLogoPath(): ?string
    {
        $institution = Institution::first();
        if (!$institution?->logo_path) return null;

        $path = storage_path('app/public/' . $institution->logo_path);
        return file_exists($path) ? $path : null;
    }
}
