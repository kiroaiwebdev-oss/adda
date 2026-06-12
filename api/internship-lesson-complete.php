<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../app/config/database.php';

$dbConfig = require __DIR__ . '/../app/config/database.php';
$dsn = sprintf("mysql:host=%s;port=%s;dbname=%s;charset=%s",
    $dbConfig['host'], $dbConfig['port'], $dbConfig['database'], $dbConfig['charset']
);

try {
    $db = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options']);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die(json_encode(['success' => false, 'error' => 'Database connection failed']));
}

spl_autoload_register(function ($class) {
    $paths = [
        __DIR__ . '/../app/core/' . $class . '.php',
        __DIR__ . '/../app/models/' . $class . '.php',
    ];
    foreach ($paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            return;
        }
    }
});

$auth = new Auth($db);
if (!$auth->check()) {
    die(json_encode(['success' => false, 'error' => 'Not authenticated']));
}

$userId = $auth->id();

$input = json_decode(file_get_contents('php://input'), true);
$lessonId = filter_var($input['lesson_id'] ?? 0, FILTER_VALIDATE_INT);

if (!$lessonId) {
    die(json_encode(['success' => false, 'error' => 'Invalid lesson ID']));
}

try {
    // Check if progress already exists (using student_id as per table structure)
    $checkStmt = $db->prepare("SELECT * FROM internship_lesson_progress WHERE lesson_id = ? AND student_id = ?");
    $checkStmt->execute([$lessonId, $userId]);
    $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing) {
        // Update existing
        $updateStmt = $db->prepare("UPDATE internship_lesson_progress SET is_completed = 1, completed_at = NOW() WHERE lesson_id = ? AND student_id = ?");
        $updateStmt->execute([$lessonId, $userId]);
    } else {
        // Insert new (using student_id)
        $insertStmt = $db->prepare("INSERT INTO internship_lesson_progress (lesson_id, student_id, is_completed, completed_at) VALUES (?, ?, 1, NOW())");
        $insertStmt->execute([$lessonId, $userId]);
    }
    
    echo json_encode(['success' => true, 'message' => 'Lesson marked as complete']);
    
} catch (Exception $e) {
    error_log('Complete lesson error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
