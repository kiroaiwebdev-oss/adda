<?php
/**
 * Users API Endpoint
 * Handles both Admin and Learner operations
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../app/config/database.php';
$dbConfig = require __DIR__ . '/../app/config/database.php';

try {
    $dsn = sprintf("mysql:host=%s;port=%s;dbname=%s;charset=%s",
        $dbConfig['host'], $dbConfig['port'], $dbConfig['database'], $dbConfig['charset']
    );
    $db = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options']);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
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
$auth->requireAuth(); // ✅ Changed from requireAdmin() to requireAuth()

$action = $_GET['action'] ?? '';
$input = file_get_contents('php://input');
$data = json_decode($input, true) ?? [];

// Get current user info
$userId = $auth->id();
$isAdmin = $auth->isAdmin();

// Handle Learner-specific actions (profile updates)
if ($action === 'update-profile') {
    
    try {
        // Validate input
        if (empty($data['name']) || empty($data['email'])) {
            throw new Exception("Name and email are required");
        }
        
        $name = trim($data['name']);
        $email = trim($data['email']);
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid email format");
        }
        
        // Check if email already exists for other users
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $userId]);
        if ($stmt->fetch()) {
            throw new Exception("Email already in use");
        }
        
        // Update user profile
        $stmt = $db->prepare("UPDATE users SET name = ?, email = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$name, $email, $userId]);
        
        // Update session
        $_SESSION['user_name'] = $name;
        $_SESSION['user_email'] = $email;
        
        echo json_encode([
            'success' => true,
            'message' => 'Profile updated successfully'
        ]);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
    exit;
}

// Handle Learner password change
if ($action === 'change-password') {
    
    try {
        // Validate input
        if (empty($data['current_password']) || empty($data['new_password'])) {
            throw new Exception("All password fields are required");
        }
        
        $currentPassword = $data['current_password'];
        $newPassword = $data['new_password'];
        
        if (strlen($newPassword) < 8) {
            throw new Exception("New password must be at least 8 characters");
        }
        
        // Get current password hash
        $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            throw new Exception("User not found");
        }
        
        // Verify current password
        if (!password_verify($currentPassword, $user['password'])) {
            throw new Exception("Current password is incorrect");
        }
        
        // Hash new password
        $newPasswordHash = password_hash($newPassword, PASSWORD_BCRYPT);
        
        // Update password
        $stmt = $db->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$newPasswordHash, $userId]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Password changed successfully'
        ]);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
    exit;
}

// ========================================
// ADMIN-ONLY ACTIONS BELOW
// ========================================

// Check admin access for admin actions
if (!$isAdmin) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error' => 'Admin access required'
    ]);
    exit;
}

// Admin actions - require UserController
$controller = new UserController($db);

switch ($action) {
    case 'update':
        $controller->update($data);
        break;
        
    case 'update-status':
        $controller->updateStatus($data);
        break;
        
    case 'delete':
        $controller->delete($data);
        break;
        
    default:
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Invalid action'
        ]);
}
?>
