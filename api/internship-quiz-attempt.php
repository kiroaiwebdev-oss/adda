<?php
/**
 * Internship Weekly Quiz - record attempt and (if passed) auto-complete lesson
 */
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
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
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
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$userId = $auth->id();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid method']);
    exit;
}

try {
    $input        = json_decode(file_get_contents('php://input'), true) ?? [];
    $lessonId     = (int) ($input['lesson_id'] ?? 0);
    $internshipId = (int) ($input['internship_id'] ?? 0);
    $score        = (int) ($input['score'] ?? 0);
    $total        = (int) ($input['total'] ?? 0);
    $percentage   = (float) ($input['percentage'] ?? 0);
    $passed       = !empty($input['passed']);

    if (!$lessonId) {
        throw new Exception('lesson_id required');
    }

    // Optional: try to write into an attempts table if it exists, otherwise silently skip.
    try {
        $check = $db->query("SHOW TABLES LIKE 'internship_quiz_attempts'");
        if ($check && $check->fetch()) {
            $stmt = $db->prepare("
                INSERT INTO internship_quiz_attempts
                    (user_id, lesson_id, score, total_questions, percentage, passed, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$userId, $lessonId, $score, $total, $percentage, $passed ? 1 : 0]);
        }
    } catch (Exception $e) {
        error_log('Quiz attempt store skipped: ' . $e->getMessage());
    }

    // If passed, auto-mark lesson complete
    if ($passed) {
        $checkStmt = $db->prepare("SELECT id FROM internship_lesson_progress WHERE lesson_id = ? AND student_id = ?");
        $checkStmt->execute([$lessonId, $userId]);
        if ($checkStmt->fetchColumn()) {
            $upd = $db->prepare("UPDATE internship_lesson_progress SET is_completed = 1, completed_at = NOW() WHERE lesson_id = ? AND student_id = ?");
            $upd->execute([$lessonId, $userId]);
        } else {
            $ins = $db->prepare("INSERT INTO internship_lesson_progress (lesson_id, student_id, is_completed, completed_at) VALUES (?, ?, 1, NOW())");
            $ins->execute([$lessonId, $userId]);
        }
    }

    echo json_encode([
        'success'    => true,
        'recorded'   => true,
        'passed'     => $passed,
        'percentage' => $percentage,
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
