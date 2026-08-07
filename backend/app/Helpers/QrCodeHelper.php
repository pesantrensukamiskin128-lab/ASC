<?php

namespace App\Helpers;

use SimpleSoftwareIO\QrCode\Facades\QrCode;
use App\Models\Institution;

class QrCodeHelper
{
    /**
     * Generate QR code dengan logo di tengah sebagai SVG data URI.
     * Warna: biru (#1e3a8a).
     */
    public static function generateWithLogo(string $data, int $size = 200): string
    {
        try {
            $logoPath = self::getLogoPath();

            $qr = QrCode::size($size)
                ->color(30, 58, 138)
                ->backgroundColor(255, 255, 255)
                ->errorCorrection('H')
                ->margin(1);

            if ($logoPath && file_exists($logoPath)) {
                $svg = $qr->merge($logoPath, 0.2, true)->generate($data);
            } else {
                $svg = $qr->generate($data);
            }

            return 'data:image/svg+xml;base64,' . base64_encode($svg);
        } catch (\Exception $e) {
            // Fallback: SVG tanpa logo
            return self::generate($data, $size);
        }
    }

    /**
     * Generate QR code tanpa logo sebagai SVG data URI.
     */
    public static function generate(string $data, int $size = 150): string
    {
        try {
            $svg = QrCode::size($size)
                ->color(30, 58, 138)
                ->backgroundColor(255, 255, 255)
                ->errorCorrection('M')
                ->margin(1)
                ->generate($data);

            return 'data:image/svg+xml;base64,' . base64_encode($svg);
        } catch (\Exception $e) {
            // Ultimate fallback: use external API
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
