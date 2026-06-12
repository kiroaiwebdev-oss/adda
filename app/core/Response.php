<?php
/**
 * Response - API response helper
 * Standardized JSON responses
 */

class Response {
    
    /**
     * Success response
     */
    public static function success($data = [], $message = null, $code = 200) {
        http_response_code($code);
        echo json_encode([
            'success' => true,
            'message' => $message,
            'data' => $data
        ], JSON_PRETTY_PRINT);
        exit;
    }
    
    /**
     * Error response
     */
    public static function error($message, $errors = [], $code = 400) {
        http_response_code($code);
        echo json_encode([
            'success' => false,
            'error' => $message,
            'errors' => $errors
        ], JSON_PRETTY_PRINT);
        exit;
    }
    
    /**
     * Unauthorized response
     */
    public static function unauthorized($message = 'Unauthorized') {
        self::error($message, [], 401);
    }
    
    /**
     * Forbidden response
     */
    public static function forbidden($message = 'Access denied') {
        self::error($message, [], 403);
    }
    
    /**
     * Not found response
     */
    public static function notFound($message = 'Resource not found') {
        self::error($message, [], 404);
    }
    
    /**
     * Validation error response
     */
    public static function validationError($errors, $message = 'Validation failed') {
        self::error($message, $errors, 422);
    }
    
    /**
     * Server error response
     */
    public static function serverError($message = 'Internal server error') {
        self::error($message, [], 500);
    }
}
