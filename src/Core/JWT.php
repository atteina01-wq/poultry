<?php
namespace Poultry\Core;

class JWT {
    private static function getSecret(): string {
        return Config::get('JWT_SECRET', 'PoultryCare_JWT_Secret_Key_2026_Secure_Integration_Key#987!');
    }

    private static function getDefaultTtl(): int {
        return (int)Config::get('JWT_TTL', 86400 * 7);
    }

    public static function generate(array $payload, ?int $ttl = null): string {
        $ttl = $ttl ?? self::getDefaultTtl();
        $issuedAt = time();
        $expire = $issuedAt + $ttl;

        $header = [
            'typ' => 'JWT',
            'alg' => 'HS256'
        ];

        $tokenPayload = array_merge($payload, [
            'iat' => $issuedAt,
            'exp' => $expire
        ]);

        $base64UrlHeader = self::base64UrlEncode(json_encode($header));
        $base64UrlPayload = self::base64UrlEncode(json_encode($tokenPayload));

        $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, self::getSecret(), true);
        $base64UrlSignature = self::base64UrlEncode($signature);

        return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
    }

    public static function verify(string $token): ?array {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$base64UrlHeader, $base64UrlPayload, $base64UrlSignature] = $parts;

        // 1. Verify Header algorithm & type
        $header = json_decode(self::base64UrlDecode($base64UrlHeader), true);
        if (!is_array($header) || ($header['alg'] ?? '') !== 'HS256' || ($header['typ'] ?? '') !== 'JWT') {
            return null;
        }

        // 2. Verify signature
        $expectedSignature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, self::getSecret(), true);
        $expectedBase64UrlSignature = self::base64UrlEncode($expectedSignature);

        if (!hash_equals($expectedBase64UrlSignature, $base64UrlSignature)) {
            return null;
        }

        // 3. Verify payload
        $payload = json_decode(self::base64UrlDecode($base64UrlPayload), true);
        if (!is_array($payload)) {
            return null;
        }

        // 4. Check expiration
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            return null; // Expired
        }

        return $payload;
    }

    private static function base64UrlEncode(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $data): string {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
