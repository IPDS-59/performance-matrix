<?php

namespace App\Kinetik;

use Illuminate\Support\Carbon;

/**
 * Display-only decode of a kipApp x-auth JWT (no signature verification — the
 * token is kipApp-minted HS256 and we only read its public payload to show the
 * admin which account it belongs to and when it expires).
 */
class KipTokenInfo
{
    public function __construct(
        public readonly ?string $nip,
        public readonly ?string $email,
        public readonly ?Carbon $expiresAt,
    ) {}

    public static function fromToken(string $token): self
    {
        $token = trim(preg_replace('/^Bearer\s+/i', '', $token));
        $parts = explode('.', $token);

        if (count($parts) < 2) {
            return new self(null, null, null);
        }

        $payload = json_decode(self::base64UrlDecode($parts[1]), true) ?: [];

        $expiresAt = isset($payload['exp'])
            ? Carbon::createFromTimestamp((int) $payload['exp'])
            : null;

        return new self(
            $payload['nip'] ?? null,
            $payload['email'] ?? null,
            $expiresAt,
        );
    }

    /**
     * Strip "Bearer " and surrounding whitespace, returning the raw token.
     */
    public static function normalize(string $token): string
    {
        return trim(preg_replace('/^Bearer\s+/i', '', trim($token)));
    }

    private static function base64UrlDecode(string $segment): string
    {
        $padded = str_pad(strtr($segment, '-_', '+/'), strlen($segment) % 4 ? strlen($segment) + 4 - strlen($segment) % 4 : strlen($segment), '=');

        return base64_decode($padded) ?: '';
    }
}
