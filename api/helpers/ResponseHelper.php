<?php
/**
 * DUHN FRAGRANCES — JSON Response Helper
 */
class ResponseHelper
{
    private static function cleanBuffer(): void
    {
        while (ob_get_level() > 0) { ob_end_clean(); }
    }

    public static function success(mixed $data, string $message = '', int $status = 200, array $pagination = []): never
    {
        self::cleanBuffer();
        http_response_code($status);
        $response = ['success' => true, 'data' => $data];
        if ($message)    $response['message']    = $message;
        if ($pagination) $response['pagination'] = $pagination;
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function error(string $error, string $message, int $status = 400, array $errors = []): never
    {
        self::cleanBuffer();
        http_response_code($status);
        $response = ['success' => false, 'error' => $error, 'message' => $message];
        if ($errors) $response['errors'] = $errors;
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function notFound(string $message = 'Resource not found'): never
    {
        self::error('NOT_FOUND', $message, 404);
    }

    public static function unauthorized(string $message = 'Unauthorized'): never
    {
        self::error('UNAUTHORIZED', $message, 401);
    }

    public static function forbidden(string $message = 'Forbidden'): never
    {
        self::error('FORBIDDEN', $message, 403);
    }

    public static function serverError(string $message = 'An unexpected error occurred'): never
    {
        self::error('SERVER_ERROR', $message, 500);
    }
}
