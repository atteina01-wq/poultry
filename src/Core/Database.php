<?php
namespace Poultry\Core;

use PDO;
use PDOException;

class Database {
    private static ?Database $instance = null;
    private ?PDO $conn = null;

    private function __construct() {
        $host = Config::get('DB_HOST', 'localhost');
        $port = Config::get('DB_PORT', '3306');
        $dbName = Config::get('DB_NAME', 'poultry_db');
        $user = Config::get('DB_USER', 'root');
        $pass = Config::get('DB_PASS', '');

        try {
            $dsn = "mysql:host={$host};port={$port};dbname={$dbName};charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            if ($host !== 'localhost' && $host !== '127.0.0.1') {
                $options[PDO::MYSQL_ATTR_SSL_CA] = true;
                $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
            }
            if (date_default_timezone_get() !== 'Asia/Manila') {
                date_default_timezone_set('Asia/Manila');
            }
            $this->conn = new PDO($dsn, $user, $pass, $options);
            $this->conn->exec("SET time_zone = '+08:00'");
        } catch (PDOException $e) {
            error_log("Database Connection Error: " . $e->getMessage());
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Database service unavailable. Please check system configuration.',
                'errors' => null,
                'timestamp' => date('c')
            ]);
            exit();
        }
    }

    public static function getInstance(): Database {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection(): PDO {
        return $this->conn;
    }
}
