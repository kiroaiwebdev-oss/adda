<?php
header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../app/config/database.php';

$dbConfig = require __DIR__ . '/../app/config/database.php';
$dsn = sprintf("mysql:host=%s;port=%s;dbname=%s;charset=%s",
    $dbConfig['host'], $dbConfig['port'], $dbConfig['database'], $dbConfig['charset']
);

try {
    $db = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options']);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
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
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId = $auth->id();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $topicId = (int)($input['topic_id'] ?? 0);
    
    if (!$topicId) {
        throw new Exception('Topic ID required');
    }
    
    // Detect actual column name in topic_progress
    $tpColumn = 'is_complete';
    try {
        $colCheck = $db->query("SHOW COLUMNS FROM topic_progress LIKE 'is_complete'");
        if (!$colCheck->fetch()) {
            $colCheck2 = $db->query("SHOW COLUMNS FROM topic_progress LIKE 'is_completed'");
            if ($colCheck2->fetch()) {
                $tpColumn = 'is_completed';
            }
        }
    } catch (Exception $e) {
        $tpColumn = 'is_complete';
    }
    
    // Check if progress exists
    $checkStmt = $db->prepare("
        SELECT * FROM topic_progress 
        WHERE topic_id = ? AND user_id = ?
    ");
    $checkStmt->execute([$topicId, $userId]);
    $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing) {
        $updateStmt = $db->prepare("
            UPDATE topic_progress 
            SET {$tpColumn} = 1, completed_at = NOW()
            WHERE topic_id = ? AND user_id = ?
        ");
        $updateStmt->execute([$topicId, $userId]);
    } else {
        $insertStmt = $db->prepare("
            INSERT INTO topic_progress (topic_id, user_id, {$tpColumn}, completed_at)
            VALUES (?, ?, 1, NOW())
        ");
        $insertStmt->execute([$topicId, $userId]);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Topic marked as complete'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
