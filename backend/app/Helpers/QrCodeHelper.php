<?php

namespace App\Helpers;

use SimpleSoftwareIO\QrCode\Facades\QrCode;
use App\Models\Institution;
use Illuminate\Support\Facades\Log;

class QrCodeHelper
{
    /**
     * Generate QR code PNG dengan logo di tengah sebagai base64 data URI.
     */
    public static function generateWithLogo(string $data, int $size = 200): string
    {
        try {
            $logoPath = self::getLogoPath();
            Log::info('QR generateWithLogo: logoPath=' . ($logoPath ?? 'null') . ', exists=' . (($logoPath && file_exists($logoPath)) ? 'yes' : 'no'));

            if ($logoPath && file_exists($logoPath)) {
                // Pastikan logo dalam format yang didukung (convert ke PNG jika perlu)
                $pngLogoPath = self::ensurePngLogo($logoPath);

                $png = QrCode::format('png')
                    ->size($size)
                    ->color(30, 58, 138)
                    ->backgroundColor(255, 255, 255)
                    ->errorCorrection('H')
                    ->margin(1)
                    ->merge($pngLogoPath, 0.25, true)
                    ->generate($data);

                Log::info('QR generateWithLogo: success, size=' . strlen($png) . ' bytes');
                return 'data:image/png;base64,' . base64_encode($png);
            }

            return self::generate($data, $size);
        } catch (\Exception $e) {
            Log::error('QR with logo failed: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
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
            Log::error('QR generate failed: ' . $e->getMessage());
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

    /**
     * Pastikan logo dalam format PNG — convert jika perlu.
     * simple-qrcode merge() hanya support PNG.
     */
    private static function ensurePngLogo(string $originalPath): string
    {
        $mime = mime_content_type($originalPath);
        Log::info("QR ensurePngLogo: mime={$mime}, path={$originalPath}");

        // Sudah PNG
        if ($mime === 'image/png') {
            return $originalPath;
        }

        // Convert ke PNG via GD
        $pngPath = storage_path('app/public/logos/qr-logo.png');

        try {
            $image = match ($mime) {
                'image/jpeg', 'image/jpg' => imagecreatefromjpeg($originalPath),
                'image/webp' => imagecreatefromwebp($originalPath),
                'image/gif' => imagecreatefromgif($originalPath),
                default => null,
            };

            if ($image) {
                // Buat background putih untuk transparansi
                $width = imagesx($image);
                $height = imagesy($image);
                $pngImage = imagecreatetruecolor($width, $height);
                $white = imagecolorallocate($pngImage, 255, 255, 255);
                imagefill($pngImage, 0, 0, $white);
                imagecopy($pngImage, $image, 0, 0, 0, 0, $width, $height);
                imagepng($pngImage, $pngPath);
                imagedestroy($image);
                imagedestroy($pngImage);

                Log::info("QR ensurePngLogo: converted to PNG at {$pngPath}");
                return $pngPath;
            }
        } catch (\Exception $e) {
            Log::warning("QR ensurePngLogo conversion failed: " . $e->getMessage());
        }

        // Fallback: return original dan berharap merge bisa handle
        return $originalPath;
    }
}
