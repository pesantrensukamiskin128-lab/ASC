<?php

namespace App\Helpers;

use SimpleSoftwareIO\QrCode\Facades\QrCode;
use App\Models\Institution;
use Illuminate\Support\Facades\Log;

class QrCodeHelper
{
    /**
     * Generate QR code PNG dengan logo di tengah menggunakan GD manual overlay.
     */
    public static function generateWithLogo(string $data, int $size = 200): string
    {
        try {
            // Generate QR tanpa logo dulu (SVG → convert ke PNG via Imagick)
            $qrPng = self::generateRawPng($data, $size);
            if (!$qrPng) {
                return self::fallbackExternal($data, $size);
            }

            $logoPath = self::getLogoPath();
            if (!$logoPath || !file_exists($logoPath)) {
                return 'data:image/png;base64,' . base64_encode($qrPng);
            }

            // Overlay logo manual pakai GD
            $qrImage = @imagecreatefromstring($qrPng);
            if (!$qrImage) {
                return 'data:image/png;base64,' . base64_encode($qrPng);
            }

            $logoImage = self::loadImage($logoPath);
            if (!$logoImage) {
                return 'data:image/png;base64,' . base64_encode($qrPng);
            }

            // Resize logo ke 22% dari QR
            $logoMaxSize = (int)($size * 0.22);
            $logoW = imagesx($logoImage);
            $logoH = imagesy($logoImage);
            $scale = min($logoMaxSize / $logoW, $logoMaxSize / $logoH);
            $newW = (int)($logoW * $scale);
            $newH = (int)($logoH * $scale);

            $resizedLogo = imagecreatetruecolor($newW, $newH);
            imagealphablending($resizedLogo, false);
            imagesavealpha($resizedLogo, true);
            imagecopyresampled($resizedLogo, $logoImage, 0, 0, 0, 0, $newW, $newH, $logoW, $logoH);

            // Buat background putih bulat di tengah QR
            $cx = (int)($size / 2);
            $cy = (int)($size / 2);
            $padding = 4;
            $bgRadius = (int)(max($newW, $newH) / 2) + $padding;

            $white = imagecolorallocate($qrImage, 255, 255, 255);
            imagefilledellipse($qrImage, $cx, $cy, $bgRadius * 2, $bgRadius * 2, $white);

            // Tempel logo di tengah
            $logoX = $cx - (int)($newW / 2);
            $logoY = $cy - (int)($newH / 2);
            imagecopy($qrImage, $resizedLogo, $logoX, $logoY, 0, 0, $newW, $newH);

            // Output ke PNG string
            ob_start();
            imagepng($qrImage);
            $finalPng = ob_get_clean();

            imagedestroy($qrImage);
            imagedestroy($logoImage);
            imagedestroy($resizedLogo);

            return 'data:image/png;base64,' . base64_encode($finalPng);
        } catch (\Exception $e) {
            Log::error('QR with logo failed: ' . $e->getMessage());
            return self::generate($data, $size);
        }
    }

    /**
     * Generate QR code tanpa logo.
     */
    public static function generate(string $data, int $size = 150): string
    {
        try {
            $png = self::generateRawPng($data, $size);
            if ($png) {
                return 'data:image/png;base64,' . base64_encode($png);
            }
            return self::fallbackExternal($data, $size);
        } catch (\Exception $e) {
            Log::error('QR generate failed: ' . $e->getMessage());
            return self::fallbackExternal($data, $size);
        }
    }

    /**
     * Generate raw PNG bytes via simple-qrcode SVG → GD conversion.
     */
    private static function generateRawPng(string $data, int $size): ?string
    {
        try {
            // Generate sebagai SVG (tidak butuh Imagick)
            $svg = QrCode::size($size)
                ->color(30, 58, 138)
                ->backgroundColor(255, 255, 255)
                ->errorCorrection('H')
                ->margin(1)
                ->generate($data);

            // Convert SVG ke PNG via GD + Imagick (jika ada)
            if (extension_loaded('imagick')) {
                $im = new \Imagick();
                $im->readImageBlob($svg);
                $im->setImageFormat('png');
                $im->resizeImage($size, $size, \Imagick::FILTER_LANCZOS, 1);
                $png = $im->getImageBlob();
                $im->clear();
                $im->destroy();
                return $png;
            }

            // Fallback: buat simple QR via GD langsung
            return null;
        } catch (\Exception $e) {
            Log::warning('generateRawPng failed: ' . $e->getMessage());
            return null;
        }
    }

    private static function fallbackExternal(string $data, int $size): string
    {
        $color = '1e3a8a';
        return "https://api.qrserver.com/v1/create-qr-code/?size={$size}x{$size}&color={$color}&data=" . urlencode($data);
    }

    private static function getLogoPath(): ?string
    {
        $institution = Institution::first();
        if (!$institution?->logo_path) return null;
        $path = storage_path('app/public/' . $institution->logo_path);
        return file_exists($path) ? $path : null;
    }

    /**
     * Load gambar dari path apapun formatnya, handle ICC profile warning.
     */
    private static function loadImage(string $path): ?\GdImage
    {
        $mime = @mime_content_type($path);

        // Suppress warnings (libpng ICC profile warning)
        $image = match ($mime) {
            'image/png' => @imagecreatefrompng($path),
            'image/jpeg', 'image/jpg' => @imagecreatefromjpeg($path),
            'image/webp' => @imagecreatefromwebp($path),
            'image/gif' => @imagecreatefromgif($path),
            default => null,
        };

        return $image ?: null;
    }
}
