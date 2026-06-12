<?php
header('Content-Type: application/json');

// Test 1: Basic PHP
$tests = [];
$tests['php_working'] = true;

// Test 2: Session
ini_set('session.cookie_path', '/');
ini_set('session.cookie_domain', 'internshipadda.com');
session_name('ai_studio_session');
session_start();
$tests['session_user_id'] = $_SESSION['studio_user_id'] ?? 'NOT FOUND';
$tests['session_id'] = session_id();

// Test 3: Database file includeable
$dbPath = __DIR__ . '/../../ai-studio/config/database.php';
$tests['db_file_exists'] = file_exists($dbPath);
$tests['db_file_path'] = $dbPath;

// Test 4: Try include
try {
    require_once $dbPath;
    $tests['db_include'] = 'SUCCESS';
} catch(Exception $e) {
    $tests['db_include'] = 'FAILED: ' . $e->getMessage();
}

// Test 5: Try DB connection
try {
    $lmsDb = getLMSDb();
    $tests['lms_db'] = 'CONNECTED';
} catch(Exception $e) {
    $tests['lms_db'] = 'FAILED: ' . $e->getMessage();
}

// Test 6: Token check
$input = json_decode(file_get_contents('php://input'), true);
$tests['token_received'] = $input['_token'] ?? 'NO TOKEN';

// Test 7: Request method
$tests['request_method'] = $_SERVER['REQUEST_METHOD'];

// Test 8: Cookies received
$tests['cookies'] = $_COOKIE;

echo json_encode($tests, JSON_PRETTY_PRINT);