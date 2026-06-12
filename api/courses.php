<?php
/**
 * Courses API Endpoint
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../app/config/database.php';
$dbConfig = require __DIR__ . '/../app/config/database.php';

try {
    $dsn = sprintf("mysql:host=%s;port=%s;dbname=%s;charset=%s",
        $dbConfig['host'], $dbConfig['port'], $dbConfig['database'], $dbConfig['charset']
    );
    $db = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

spl_autoload_register(function ($class) {
    $paths = [
        __DIR__ . '/../app/core/' . $class . '.php',
        __DIR__ . '/../app/models/' . $class . '.php',
        __DIR__ . '/../app/controllers/' . $class . '.php',
    ];
    foreach ($paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            return;
        }
    }
});

session_start();

$auth = new Auth($db);
$auth->requireAdmin();

$action = $_GET['action'] ?? '';
$input = file_get_contents('php://input');
$data = json_decode($input, true) ?? [];

$controller = new CourseController($db, $auth);

switch ($action) {
    case 'create':
        $controller->create($data);
        break;
        
    case 'update':
        $controller->update($data);
        break;
        
    case 'delete':
        $controller->delete($data);
        break;
        
    default:
        Response::error('Invalid action', [], 400);
}
