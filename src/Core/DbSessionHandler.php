<?php
namespace Poultry\Core;

use PDO;
use SessionHandlerInterface;

class DbSessionHandler implements SessionHandlerInterface {
    private static bool $registered = false;
    private ?PDO $pdo = null;

    public function __construct(?PDO $pdo = null) {
        $this->pdo = $pdo;
    }

    public static function register(?PDO $pdo = null): void {
        if (self::$registered || session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.use_strict_mode', '0');
            $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
            
            session_set_cookie_params([
                'lifetime' => 86400 * 7,
                'path' => '/',
                'domain' => '',
                'secure' => $isSecure,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
        }

        $handler = new self($pdo);
        session_set_save_handler($handler, true);
        session_register_shutdown();
        self::$registered = true;
    }

    private function getPdo(): ?PDO {
        if ($this->pdo === null) {
            try {
                $this->pdo = Database::getInstance()->getConnection();
            } catch (\Throwable $e) {
                return null;
            }
        }
        return $this->pdo;
    }

    public function open(string $path, string $name): bool {
        return true;
    }

    public function close(): bool {
        return true;
    }

    public function read(string $id): string|false {
        $pdo = $this->getPdo();
        if (!$pdo) {
            return '';
        }
        try {
            $stmt = $pdo->prepare("SELECT data FROM php_sessions WHERE id = ?");
            $stmt->execute([$id]);
            $val = $stmt->fetchColumn();
            return $val !== false ? (string)$val : '';
        } catch (\Throwable $e) {
            return '';
        }
    }

    public function write(string $id, string $data): bool {
        $pdo = $this->getPdo();
        if (!$pdo) {
            return false;
        }
        try {
            $access = time();
            $stmt = $pdo->prepare("REPLACE INTO php_sessions (id, access, data) VALUES (?, ?, ?)");
            return $stmt->execute([$id, $access, $data]);
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function destroy(string $id): bool {
        $pdo = $this->getPdo();
        if (!$pdo) {
            return false;
        }
        try {
            $stmt = $pdo->prepare("DELETE FROM php_sessions WHERE id = ?");
            return $stmt->execute([$id]);
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function gc(int $max_lifetime): int|false {
        $pdo = $this->getPdo();
        if (!$pdo) {
            return 0;
        }
        try {
            $old = time() - $max_lifetime;
            $stmt = $pdo->prepare("DELETE FROM php_sessions WHERE access < ?");
            $stmt->execute([$old]);
            return $stmt->rowCount();
        } catch (\Throwable $e) {
            return 0;
        }
    }
}

