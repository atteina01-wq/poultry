<?php
namespace Poultry\Core;

class Response {
    public static function json(
        mixed $data = null,
        string $message = 'Success',
        int $statusCode = 200,
        mixed $errors = null
    ): void {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');

        $isSuccess = $statusCode >= 200 && $statusCode < 300;

        $payload = [
            'success' => $isSuccess,
            'message' => $message,
            'data' => $data,
            'errors' => $errors,
            'timestamp' => date('c')
        ];

        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    }

    public static function success(mixed $data = null, string $message = 'Operation successful', int $statusCode = 200): void {
        self::json($data, $message, $statusCode, null);
    }

    public static function created(mixed $data = null, string $message = 'Resource created successfully'): void {
        self::json($data, $message, 201, null);
    }

    public static function error(string $message = 'Bad Request', int $statusCode = 400, mixed $errors = null): void {
        self::json(null, $message, $statusCode, $errors);
    }

    public static function notFound(string $message = 'Resource not found'): void {
        self::json(null, $message, 404, null);
    }

    public static function unauthorized(string $message = 'Unauthorized access'): void {
        self::json(null, $message, 401, null);
    }

    public static function forbidden(string $message = 'Forbidden action'): void {
        self::json(null, $message, 403, null);
    }

    public static function unprocessable(mixed $errors, string $message = 'Validation failed'): void {
        self::json(null, $message, 422, $errors);
    }

    public static function serverError(string $message = 'Internal server error'): void {
        self::json(null, $message, 500, null);
    }
}
