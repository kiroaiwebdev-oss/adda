<?php
session_name('ai_studio_session');
session_start();
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

if (!isset($_SESSION['studio_user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../../ai-studio/config/database.php';
$db     = getAIDb();
$action = $_GET['action'] ?? '';

if ($action === 'get') {
    try {
        $rows = $db->query("SELECT setting_key, setting_value FROM ai_generator_settings")
                   ->fetchAll(PDO::FETCH_KEY_PAIR);
        echo json_encode(['success' => true, 'settings' => $rows ?: new stdClass()]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'save') {
    if (($_SESSION['studio_role'] ?? '') !== 'admin') {
        echo json_encode(['success' => false, 'message' => 'Admin only']);
        exit;
    }
    $raw   = file_get_contents('php://input');
    $input = json_decode($raw, true);
    if (!$input || !is_array($input)) {
        echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
        exit;
    }
    $allowed = [
        'active_ai_provider',
        'gemini_api_key','gemini_api_key_2','gemini_api_key_3','gemini_model',
        'groq_api_key',  'groq_api_key_2',  'groq_api_key_3',  'groq_model',
        'openai_api_key','openai_api_key_2', 'openai_api_key_3','openai_model',
        'grok_api_key',  'grok_api_key_2',   'grok_api_key_3',  'grok_model',
        'default_language','batch_size','image_search_engine','include_quiz'
    ];
    $stmt = $db->prepare(
        "INSERT INTO ai_generator_settings (setting_key, setting_value)
         VALUES (?,?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
    );
    foreach ($allowed as $key) {
        if (array_key_exists($key, $input)) {
            $stmt->execute([$key, $input[$key]]);
        }
    }
    $verified = $db->query("SELECT setting_key, setting_value FROM ai_generator_settings")
                   ->fetchAll(PDO::FETCH_KEY_PAIR);
    echo json_encode(['success' => true, 'message' => 'Saved!', 'verified' => $verified]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action: ' . $action]);