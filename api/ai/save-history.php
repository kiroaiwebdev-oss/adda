<?php
session_name('ai_studio_session');
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../../ai-studio/config/database.php';

if (!isset($_SESSION['studio_user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$db    = getAIDb();
$input = json_decode(file_get_contents('php://input'), true);

$topic     = trim($input['topic']      ?? '');
$totalDays = (int)($input['total_days']?? 30);
$level     = trim($input['level']      ?? 'Beginner');
$language  = trim($input['language']   ?? 'English');
$type      = trim($input['type']       ?? 'course');
$provider  = trim($input['provider']   ?? 'gemini');
$model     = trim($input['model']      ?? '');

if (!$topic) {
    echo json_encode(['success' => false, 'message' => 'Topic required']);
    exit;
}

try {
    $db->prepare("
        INSERT INTO ai_generated_history
        (studio_user_id, topic, duration_days, level, language, generate_for, status, ai_provider, created_at)
        VALUES (?,?,?,?,?,?,'generating',?,NOW())
    ")->execute([
        $_SESSION['studio_user_id'], $topic, $totalDays,
        $level, $language, $type, $provider . '/' . $model
    ]);
    echo json_encode(['success' => true, 'history_id' => $db->lastInsertId()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}