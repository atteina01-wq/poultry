<?php
namespace Poultry\Core;

class Config {
    private static ?array $config = null;

    public static function load(): void {
        if (self::$config !== null) {
            return;
        }

        // Default configurations
        self::$config = [
            'DB_HOST' => 'localhost',
            'DB_NAME' => 'poultry_db',
            'DB_USER' => 'root',
            'DB_PASS' => '',
            'JWT_SECRET' => 'PoultryCare_JWT_Secret_Key_2026_Secure_Integration_Key#987!',
            'JWT_TTL' => 86400 * 7,
            'APP_ENV' => 'development',
            'CORS_ALLOWED_ORIGINS' => '*'
        ];

        // Read .env if present
        $envPath = __DIR__ . '/../../.env';
        if (file_exists($envPath)) {
            $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#')) {
                    continue;
                }
                if (str_contains($line, '=')) {
                    [$key, $value] = explode('=', $line, 2);
                    $key = trim($key);
                    $value = trim($value, " \t\n\r\0\x0B\"'");
                    self::$config[$key] = $value;
                }
            }
        }
    }

    public static function get(string $key, mixed $default = null): mixed {
        self::load();
        return self::$config[$key] ?? (getenv($key) ?: $default);
    }
}
