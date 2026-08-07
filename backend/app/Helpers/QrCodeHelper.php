<?php

namespace App\Helpers;

use SimpleSoftwareIO\QrCode\Facades\QrCode;
use App\Models\Institution;

class QrCodeHelper
{
    /**
     * Generate QR code sebagai base64 PNG data URI dengan logo institusi di tengah.
     * Warna QR: biru (#1e3a8a) sesuai branding ASC.
     *
     * @param string $data URL atau teks untuk QR
     * @param int $size Ukuran QR dalam pixel
     * @return string Data URI (data:image/png;base64,...)
     */
    public static function generateWithLogo(string $data, int $size = 200): string
    {
        $logoPath = self::getLogoPath();

        $qr = QrCode::format('png')
            ->size($size)
            ->color(30, 58, 138) // #1e3a8a (biru)
            ->backgroundColor(255, 255, 255)
            ->errorCorrection('H') // High error correction untuk support logo
            ->margin(1);

        if ($logoPath && file_exists($logoPath)) {
            // Merge logo di tengah QR — ukuran logo 20% dari QR
            $qr = $qr->merge($logoPath, 0.2, true);
        }

        $pngData = $qr->generate($data);

        return 'data:image/png;base64,' . base64_encode($pngData);
    }

    /**
     * Generate QR code tanpa logo (untuk tanda tangan — harus kecil & scannable).
     */
    public static function generate(string $data, int $size = 150): string
    {
        $pngData = QrCode::format('png')
            ->size($size)
            ->color(30, 58, 138)
            ->backgroundColor(255, 255, 255)
            ->errorCorrection('M')
            ->margin(1)
            ->generate($data);

        return 'data:image/png;base64,' . base64_encode($pngData);
    }

    /**
     * Ambil path logo institusi dari storage.
     */
    private static function getLogoPath(): ?string
    {
        $institution = Institution::first();
        if (!$institution?->logo_path) return null;

        $path = storage_path('app/public/' . $institution->logo_path);
        return file_exists($path) ? $path : null;
    }
}
