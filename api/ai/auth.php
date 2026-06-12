<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
session_name('ai_studio_session');
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../../ai-studio/config/database.php';

$db     = getAIDb();
$action = $_GET['action'] ?? '';

// ── LOGIN ─────────────────────────────────────────
if ($action === 'login') {
    $input    = json_decode(file_get_contents('php://input'), true);
    $email    = trim($input['email']    ?? '');
    $password = trim($input['password'] ?? '');

    if (!$email || !$password) {
        echo json_encode(['success' => false, 'message' => 'Email aur password required']);
        exit;
    }

    $stmt = $db->prepare("SELECT * FROM ai_generator_users WHERE email = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid email ya password']);
        exit;
    }

    $_SESSION['studio_user_id'] = $user['id'];
    $_SESSION['studio_name']    = $user['name'];
    $_SESSION['studio_role']    = $user['role'];

    $db->prepare("UPDATE ai_generator_users SET last_login = NOW() WHERE id = ?")
       ->execute([$user['id']]);

    echo json_encode(['success' => true, 'name' => $user['name'], 'role' => $user['role']]);
    exit;
}

// ── LOGOUT ────────────────────────────────────────
if ($action === 'logout') {
    $_SESSION = [];
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    session_destroy();
    echo json_encode(['success' => true]);
    exit;
}

// ── CHECK ─────────────────────────────────────────
if ($action === 'check') {
    echo json_encode([
        'success'   => true,
        'logged_in' => isset($_SESSION['studio_user_id']),
        'name'      => $_SESSION['studio_name'] ?? '',
        'role'      => $_SESSION['studio_role'] ?? ''
    ]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);