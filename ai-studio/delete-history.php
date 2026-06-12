<?php
/**
 * AI Studio — Delete a generated course/history record
 * Self-contained: uses the studio DB directly (config/database.php) so it does
 * NOT depend on the external ../api/ai/history.php (which has no delete action).
 */
session_name('ai_studio_session');
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['studio_user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

require_once __DIR__ . '/config/database.php';

// Accept id from JSON body, POST or GET
$body = json_decode(file_get_contents('php://input'), true) ?: [];
$id   = intval($body['id'] ?? $_POST['id'] ?? $_GET['id'] ?? 0);

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid id']);
    exit;
}

try {
    $db = getAIDb();

    // Find the history table dynamically (schema may name it differently).
    $tbl  = null;
    $cands = [
        'ai_generation_history', 'ai_generations', 'generation_history',
        'ai_history', 'ai_course_history', 'ai_studio_history', 'ai_courses'
    ];
    foreach ($cands as $c) {
        $q = $db->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ? AND table_name = ?");
        $q->execute([AI_DB_NAME, $c]);
        if ($q->fetchColumn() > 0) { $tbl = $c; break; }
    }
    // Fallback: any table in this DB that has a 'duration_days' column
    if (!$tbl) {
        $q = $db->prepare("SELECT table_name FROM information_schema.columns WHERE table_schema = ? AND column_name = 'duration_days' LIMIT 1");
        $q->execute([AI_DB_NAME]);
        $tbl = $q->fetchColumn() ?: null;
    }
    if (!$tbl) {
        echo json_encode(['success' => false, 'message' => 'History table nahi mili']);
        exit;
    }

    // $tbl comes from our own information_schema lookup (not user input) -> safe.
    $stmt = $db->prepare("DELETE FROM `$tbl` WHERE id = ?");
    $stmt->execute([$id]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Deleted']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Record nahi mila (id ' . $id . ')']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'DB error: ' . $e->getMessage()]);
}