<?php
session_name('ai_studio_session');
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['studio_user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../../ai-studio/config/database.php';
$db = getAIDb();

try {
    $stmt = $db->query("SELECT setting_key, setting_value FROM ai_generator_settings");
    $settings = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    echo json_encode(['success' => true, 'settings' => $settings]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}