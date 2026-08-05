<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TwoFactorController extends Controller
{
    /**
     * Generate 2FA secret & QR code URL
     */
    public function setup(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->two_factor_enabled) {
            return response()->json(['message' => '2FA sudah aktif.'], 422);
        }

        // Generate secret (base32 compatible, 16 chars)
        $secret = strtoupper(Str::random(16));
        $user->two_factor_secret = encrypt($secret);
        $user->save();

        // Generate otpauth URL for QR code
        $issuer = config('app.name', 'SIAKAD');
        $otpauthUrl = "otpauth://totp/{$issuer}:{$user->email}?secret={$secret}&issuer={$issuer}&digits=6&period=30";

        return response()->json([
            'secret' => $secret,
            'qr_url' => $otpauthUrl,
            'message' => 'Scan QR code ini dengan aplikasi authenticator (Google Authenticator, Authy, dll).',
        ]);
    }

    /**
     * Konfirmasi & aktifkan 2FA setelah verifikasi kode
     */
    public function confirm(Request $request): JsonResponse
    {
        $request->validate(['code' => 'required|string|size:6']);

        $user = $request->user();

        if ($user->two_factor_enabled) {
            return response()->json(['message' => '2FA sudah aktif.'], 422);
        }

        if (!$user->two_factor_secret) {
            return response()->json(['message' => 'Setup 2FA terlebih dahulu.'], 422);
        }

        $secret = decrypt($user->two_factor_secret);
        $valid = $this->verifyTOTP($secret, $request->code);

        if (!$valid) {
            return response()->json(['message' => 'Kode tidak valid. Pastikan waktu perangkat Anda sinkron.'], 422);
        }

        // Generate recovery codes
        $recoveryCodes = collect(range(1, 8))->map(fn() => Str::random(10))->toArray();

        $user->two_factor_enabled = true;
        $user->two_factor_recovery_codes = encrypt(json_encode($recoveryCodes));
        $user->two_factor_confirmed_at = now();
        $user->save();

        AuditLog::record('2FA_ENABLED', 'User', $user->id);

        return response()->json([
            'message' => '2FA berhasil diaktifkan!',
            'recovery_codes' => $recoveryCodes,
        ]);
    }

    /**
     * Verifikasi kode 2FA saat login
     */
    public function verify(Request $request): JsonResponse
    {
        $request->validate(['code' => 'required|string']);

        $user = $request->user();

        if (!$user->two_factor_enabled) {
            return response()->json(['message' => '2FA tidak aktif untuk akun ini.'], 422);
        }

        $secret = decrypt($user->two_factor_secret);
        $code = $request->code;

        // Cek apakah ini recovery code
        if (strlen($code) === 10) {
            $recoveryCodes = json_decode(decrypt($user->two_factor_recovery_codes), true);
            $index = array_search($code, $recoveryCodes);

            if ($index !== false) {
                // Hapus recovery code yang sudah dipakai
                unset($recoveryCodes[$index]);
                $user->two_factor_recovery_codes = encrypt(json_encode(array_values($recoveryCodes)));
                $user->save();

                return response()->json(['message' => 'Verifikasi berhasil (recovery code).', 'verified' => true]);
            }
        }

        // Verifikasi TOTP
        if ($this->verifyTOTP($secret, $code)) {
            return response()->json(['message' => 'Verifikasi berhasil.', 'verified' => true]);
        }

        return response()->json(['message' => 'Kode tidak valid.', 'verified' => false], 422);
    }

    /**
     * Nonaktifkan 2FA
     */
    public function disable(Request $request): JsonResponse
    {
        $request->validate(['password' => 'required|string']);

        $user = $request->user();

        if (!\Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Password tidak valid.'], 422);
        }

        $user->two_factor_enabled = false;
        $user->two_factor_secret = null;
        $user->two_factor_recovery_codes = null;
        $user->two_factor_confirmed_at = null;
        $user->save();

        AuditLog::record('2FA_DISABLED', 'User', $user->id);

        return response()->json(['message' => '2FA berhasil dinonaktifkan.']);
    }

    /**
     * Tampilkan recovery codes (harus verifikasi password)
     */
    public function recoveryCodes(Request $request): JsonResponse
    {
        $request->validate(['password' => 'required|string']);

        $user = $request->user();

        if (!\Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Password tidak valid.'], 422);
        }

        if (!$user->two_factor_recovery_codes) {
            return response()->json(['message' => '2FA tidak aktif.'], 422);
        }

        $codes = json_decode(decrypt($user->two_factor_recovery_codes), true);

        return response()->json(['recovery_codes' => $codes]);
    }

    /**
     * TOTP verification (RFC 6238)
     * Simple implementation tanpa library eksternal
     */
    private function verifyTOTP(string $secret, string $code, int $window = 1): bool
    {
        $timeSlice = floor(time() / 30);

        // Check current time and ±window
        for ($i = -$window; $i <= $window; $i++) {
            $calculatedCode = $this->generateTOTP($secret, $timeSlice + $i);
            if (hash_equals($calculatedCode, $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate TOTP code from secret and time
     */
    private function generateTOTP(string $secret, int $timeSlice): string
    {
        // Decode base32 secret
        $secretKey = $this->base32Decode($secret);

        // Pack time into 8-byte binary
        $time = pack('N*', 0, $timeSlice);

        // HMAC-SHA1
        $hmac = hash_hmac('sha1', $time, $secretKey, true);

        // Dynamic truncation
        $offset = ord(substr($hmac, -1)) & 0x0F;
        $code = (
            ((ord($hmac[$offset]) & 0x7F) << 24) |
            ((ord($hmac[$offset + 1]) & 0xFF) << 16) |
            ((ord($hmac[$offset + 2]) & 0xFF) << 8) |
            (ord($hmac[$offset + 3]) & 0xFF)
        ) % 1000000;

        return str_pad((string) $code, 6, '0', STR_PAD_LEFT);
    }

    private function base32Decode(string $input): string
    {
        $map = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $input = strtoupper(rtrim($input, '='));
        $output = '';
        $buffer = 0;
        $bitsLeft = 0;

        for ($i = 0; $i < strlen($input); $i++) {
            $val = strpos($map, $input[$i]);
            if ($val === false) continue;
            $buffer = ($buffer << 5) | $val;
            $bitsLeft += 5;
            if ($bitsLeft >= 8) {
                $bitsLeft -= 8;
                $output .= chr(($buffer >> $bitsLeft) & 0xFF);
            }
        }

        return $output;
    }
}
