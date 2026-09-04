<?php

namespace App\Support;

class OperationalDocumentVerification
{
    public static function issue(string $type, int $id, string $reference): string
    {
        $encodedId = base_convert((string) $id, 10, 36);
        $signature = substr(hash_hmac('sha256', $type.'|'.$id.'|'.$reference, self::key()), 0, 32);

        return $encodedId.'.'.$signature;
    }

    public static function id(string $token): ?int
    {
        [$encodedId, $signature] = array_pad(explode('.', $token, 2), 2, null);
        if (! $encodedId || ! $signature || ! preg_match('/^[0-9a-z]+$/', $encodedId)) {
            return null;
        }

        return (int) base_convert($encodedId, 36, 10);
    }

    public static function matches(string $token, string $type, int $id, string $reference): bool
    {
        return hash_equals(self::issue($type, $id, $reference), $token);
    }

    private static function key(): string
    {
        return (string) config('app.key');
    }
}
