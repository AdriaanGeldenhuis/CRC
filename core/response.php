<?php
/**
 * CRC Response Helpers
 * JSON response formatting and HTTP status helpers
 */

// Prevent direct access
if (!defined('CRC_LOADED')) {
    die('Direct access not permitted');
}

class Response {
    /**
     * Send JSON response and exit
     */
    public static function json(array $data, int $status = 200): never {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Success response
     */
    public static function success(array $data = [], string $message = null): never {
        $response = ['ok' => true];

        if ($message) {
            $response['message'] = $message;
        }

        self::json(array_merge($response, $data));
    }

    /**
     * Error response
     */
    public static function error(string $message, int $status = 400, array $data = []): never {
        $response = ['ok' => false, 'error' => $message];
        self::json(array_merge($response, $data), $status);
    }

    /**
     * Validation error response
     */
    public static function validationError(array $errors): never {
        self::json([
            'ok' => false,
            'error' => 'Validation failed',
            'errors' => $errors
        ], 422);
    }

    /**
     * Unauthorized response (401)
     */
    public static function unauthorized(string $message = 'Unauthorized'): never {
        self::json(['ok' => false, 'error' => $message], 401);
    }

    /**
     * Forbidden response (403)
     */
    public static function forbidden(string $message = 'Forbidden'): never {
        self::json(['ok' => false, 'error' => $message], 403);
    }

    /**
     * Not found response (404)
     */
    public static function notFound(string $message = 'Not found'): never {
        self::json(['ok' => false, 'error' => $message], 404);
    }

    /**
     * Method not allowed (405)
     */
    public static function methodNotAllowed(string $message = 'Method not allowed'): never {
        self::json(['ok' => false, 'error' => $message], 405);
    }

    /**
     * Too many requests (429)
     */
    public static function tooManyRequests(string $message = 'Too many requests'): never {
        self::json(['ok' => false, 'error' => $message], 429);
    }

    /**
     * Server error (500)
     */
    public static function serverError(string $message = 'Internal server error'): never {
        self::json(['ok' => false, 'error' => $message], 500);
    }

    /**
     * Paginated response
     */
    public static function paginated(array $items, int $total, int $page, int $perPage): never {
        $totalPages = ceil($total / $perPage);

        self::success([
            'data' => $items,
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'total_pages' => $totalPages,
                'has_more' => $page < $totalPages
            ]
        ]);
    }

    /**
     * Require POST method
     */
    public static function requirePost(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            self::methodNotAllowed('POST method required');
        }
    }

    /**
     * Require GET method
     */
    public static function requireGet(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            self::methodNotAllowed('GET method required');
        }
    }

    /**
     * Get JSON body from request
     */
    public static function getJsonBody(): array {
        $body = file_get_contents('php://input');
        if (empty($body)) {
            return [];
        }

        $data = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            self::error('Invalid JSON body');
        }

        return $data;
    }

    /**
     * Get request input (POST or JSON body)
     */
    public static function input(?string $key = null, mixed $default = null): mixed {
        static $input = null;

        if ($input === null) {
            $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

            if (str_contains($contentType, 'application/json')) {
                $input = self::getJsonBody();
            } else {
                $input = $_POST;
            }

            // Merge with GET for query params
            $input = array_merge($_GET, $input);
        }

        if ($key === null) {
            return $input;
        }

        return $input[$key] ?? $default;
    }

    /**
     * Redirect
     */
    public static function redirect(string $url, int $status = 302): never {
        http_response_code($status);
        header('Location: ' . $url);
        exit;
    }

    /**
     * Set no-cache headers
     */
    public static function noCache(): void {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
    }

    /**
     * Set cache headers
     */
    public static function cache(int $seconds): void {
        header('Cache-Control: public, max-age=' . $seconds);
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $seconds) . ' GMT');
    }
}

/**
 * Helper function for quick JSON response
 */
function json_response(array $data, int $status = 200): never {
    Response::json($data, $status);
}

/**
 * Helper function to get input
 */
function input(?string $key = null, mixed $default = null): mixed {
    return Response::input($key, $default);
}
