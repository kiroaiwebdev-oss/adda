<?php
/**
 * Application Entry Point
 * All requests are routed through this file
 */

// Error reporting based on environment
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Set timezone
date_default_timezone_set('Asia/Kolkata');

// Load configuration
$appConfig = require __DIR__ . '/app/config/app.php';
$dbConfig = require __DIR__ . '/app/config/database.php';

// Database connection
try {
    $dsn = sprintf(
        "mysql:host=%s;port=%s;dbname=%s;charset=%s",
        $dbConfig['host'],
        $dbConfig['port'],
        $dbConfig['database'],
        $dbConfig['charset']
    );
    
    $db = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options']);
} catch (PDOException $e) {
    die('Database connection failed: ' . $e->getMessage());
}

// Autoload core classes
spl_autoload_register(function ($class) {
    $paths = [
        __DIR__ . '/app/core/' . $class . '.php',
        __DIR__ . '/app/models/' . $class . '.php',
        __DIR__ . '/app/controllers/' . $class . '.php',
    ];
    
    foreach ($paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            return;
        }
    }
});

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Initialize Auth
$auth = new Auth($db);

// Route static public files directly
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Serve public HTML files directly
if (preg_match('/\.(html|css|js|jpg|jpeg|png|gif|svg|ico|woff|woff2|ttf|eot)$/', $requestUri)) {
    return false; // Let Apache handle static files
}

// Include routes
require __DIR__ . '/app/routes.php';
