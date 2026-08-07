<?php

namespace App\Helpers;

use App\Models\Institution;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class QrCodeHelper
{
    /**
     * Generate QR code biru dengan logo di tengah.
     * Menggunakan external API untuk generate QR, lalu overlay logo via GD.
     */
    public static function generateWithLogo(string $data, int $size = 200): string
    {
        try {
            $qrPng = self::fetchQrPng($data, $size);
            if (!$qrPng) {
                return self::fallbackUrl($data, $size);
            }

            $logoPath = self::getLogoPath();
            if (!$logoPath || !file_exists($logoPath)) {
                return 'data:image/png;base64,' . base64_encode($qrPng);
            }

            // Overlay logo via GD
            $qrImage = @imagecreatefromstring($qrPng);
            if (!$qrImage) {
                return 'data:image/png;base64,' . base64_encode($qrPng);
            }

            $logoImage = self::loadImage($logoPath);
            if (!$logoImage) {
                $result = 'data:image/png;base64,' . base64_encode($qrPng);
                imagedestroy($qrImage);
                return $result;
            }

            // Resize logo ke 22% dari QR
            $logoMaxSize = (int)($size * 0.22);
            $logoW = imagesx($logoImage);
            $logoH = imagesy($logoImage);
            $scale = min($logoMaxSize / $logoW, $logoMaxSize / $logoH);
            $newW = (int)($logoW * $scale);
            $newH = (int)($logoH * $scale);

            $resizedLogo = imagecreatetruecolor($newW, $newH);
            imagesavealpha($resizedLogo, true);
            $transparent = imagecolorallocatealpha($resizedLogo, 0, 0, 0, 127);
            imagefill($resizedLogo, 0, 0, $transparent);
            imagecopyresampled($resizedLogo, $logoImage, 0, 0, 0, 0, $newW, $newH, $logoW, $logoH);

            // Background putih bulat di tengah QR
            $cx = (int)(imagesx($qrImage) / 2);
            $cy = (int)(imagesy($qrImage) / 2);
            $padding = 5;
            $bgSize = max($newW, $newH) + ($padding * 2);
            $white = imagecolorallocate($qrImage, 255, 255, 255);
            imagefilledellipse($qrImage, $cx, $cy, $bgSize, $bgSize, $white);

            // Tempel logo
            $logoX = $cx - (int)($newW / 2);
            $logoY = $cy - (int)($newH / 2);
            imagecopy($qrImage, $resizedLogo, $logoX, $logoY, 0, 0, $newW, $newH);

            ob_start();
            imagepng($qrImage);
            $finalPng = ob_get_clean();

            imagedestroy($qrImage);
            imagedestroy($logoImage);
            imagedestroy($resizedLogo);

            return 'data:image/png;base64,' . base64_encode($finalPng);
        } catch (\Exception $e) {
            Log::error('QR with logo error: ' . $e->getMessage());
            return self::fallbackUrl($data, $size);
        }
    }

    /**
     * Generate QR code biru tanpa logo.
     */
    public static function generate(string $data, int $size = 150): string
    {
        try {
            $qrPng = self::fetchQrPng($data, $size);
            if ($qrPng) {
                return 'data:image/png;base64,' . base64_encode($qrPng);
            }
        } catch (\Exception $e) {
            Log::warning('QR generate error: ' . $e->getMessage());
        }

        return self::fallbackUrl($data, $size);
    }

    /**
     * Fetch QR PNG dari external API (warna biru).
     */
    private static function fetchQrPng(string $data, int $size): ?string
    {
        $url = "https://api.qrserver.com/v1/create-qr-code/?"
            . http_build_query([
                'size' => "{$size}x{$size}",
                'color' => '1e3a8a',
                'bgcolor' => 'ffffff',
                'data' => $data,
                'format' => 'png',
                'qzone' => 1,
                'ecc' => 'H',
            ]);

        $response = Http::withoutVerifying()->timeout(10)->get($url);

        if ($response->successful() && str_starts_with($response->header('Content-Type') ?? '', 'image/')) {
            return $response->body();
        }

        return null;
    }

    private static function fallbackUrl(string $data, int $size): string
    {
        return "https://api.qrserver.com/v1/create-qr-code/?size={$size}x{$size}&color=1e3a8a&data=" . urlencode($data);
    }

    private static function getLogoPath(): ?string
    {
        $institution = Institution::first();
        if (!$institution?->logo_path) return null;
        $path = storage_path('app/public/' . $institution->logo_path);
        return file_exists($path) ? $path : null;
    }

    /**
     * Load gambar — suppress ICC profile warnings.
     */
    private static function loadImage(string $path): ?\GdImage
    {
        $mime = @mime_content_type($path);
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
