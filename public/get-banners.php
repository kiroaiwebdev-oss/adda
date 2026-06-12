<?php
/**
 * Public API - Get Active Banners for Homepage
 * File: public/get-banners.php
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Load database config
$dbConfig = require __DIR__ . '/../app/config/database.php';
$dsn = sprintf("mysql:host=%s;port=%s;dbname=%s;charset=%s",
    $dbConfig['host'], $dbConfig['port'], $dbConfig['database'], $dbConfig['charset']
);

try {
    $db = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options']);
    
    // Load Banner model
    require_once __DIR__ . '/../app/models/Banner.php';
    $banner = new Banner($db);
    
    // Get only active banners
    $banners = $banner->getAll(true);
    
    echo json_encode([
        'success' => true,
        'banners' => $banners,
        'count' => count($banners)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load banners',
        'error' => $e->getMessage()
    ]);
}
?>
