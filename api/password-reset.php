<?php
error_reporting(E_ALL);
ini_set('display_errors', 0); // Production: hide errors from output
ini_set('log_errors', 1);

try {
    session_start();
} catch (Exception $e) {
    error_log("Session error: " . $e->getMessage());
}

// Load database config
$configPath = __DIR__ . '/../app/config/database.php';
if (!file_exists($configPath)) {
    error_log("Database config not found: $configPath");
    die(json_encode(['success' => false, 'message' => 'Configuration error']));
}
require_once $configPath;

// Load EmailService - CHECK IF FILE EXISTS FIRST
$emailServicePath = __DIR__ . '/../app/core/EmailService.php';
if (!file_exists($emailServicePath)) {
    error_log("EmailService not found: $emailServicePath");
    die(json_encode(['success' => false, 'message' => 'Email service not configured']));
}
require_once $emailServicePath;

header('Content-Type: application/json');

// Database connection
try {
    $dbConfig = require __DIR__ . '/../app/config/database.php';
    $dsn = sprintf("mysql:host=%s;port=%s;dbname=%s;charset=%s",
        $dbConfig['host'], $dbConfig['port'], $dbConfig['database'], $dbConfig['charset']
    );
    
    $db = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options']);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    die(json_encode(['success' => false, 'message' => 'Database connection failed']));
}

$action = $_GET['action'] ?? '';

// REQUEST RESET
if ($action === 'request' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        $email = filter_var($data['email'] ?? '', FILTER_VALIDATE_EMAIL);
        
        if (!$email) {
            die(json_encode(['success' => false, 'message' => 'Invalid email address']));
        }
        
        // Check if user exists
        $stmt = $db->prepare("SELECT id, name FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            // Security: don't reveal if email exists
            die(json_encode(['success' => true, 'message' => 'If the email exists, a reset link will be sent']));
        }
        
        // Generate unique token
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        // Store token
        $stmt = $db->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
        $stmt->execute([$email, $token, $expiresAt]);
        
        // Send email
        $resetLink = "https://" . $_SERVER['HTTP_HOST'] . "/public/reset-password.php?token=" . $token;
        
        $emailService = new EmailService($db);
        $result = $emailService->sendPasswordReset($email, $user['name'], $resetLink, $token);
        
        if ($result['success']) {
            die(json_encode(['success' => true, 'message' => 'Reset link sent to your email']));
        } else {
            error_log("Email sending failed: " . ($result['error'] ?? 'Unknown error'));
            die(json_encode(['success' => false, 'message' => 'Failed to send email. Please try again.']));
        }
        
    } catch (Exception $e) {
        error_log("Password reset request error: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        die(json_encode(['success' => false, 'message' => 'Server error occurred']));
    }
}

// RESET PASSWORD
if ($action === 'reset' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        $token = $data['token'] ?? '';
        $password = $data['password'] ?? '';
        
        if (!$token || !$password) {
            die(json_encode(['success' => false, 'message' => 'Missing required fields']));
        }
        
        if (strlen($password) < 8) {
            die(json_encode(['success' => false, 'message' => 'Password must be at least 8 characters']));
        }
        
        // Validate token
        $stmt = $db->prepare("SELECT * FROM password_resets WHERE token = ? AND used = 0 AND expires_at > NOW()");
        $stmt->execute([$token]);
        $reset = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$reset) {
            die(json_encode(['success' => false, 'message' => 'Invalid or expired token']));
        }
        
        // Update password
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $db->prepare("UPDATE users SET password = ? WHERE email = ?");
        $stmt->execute([$hashedPassword, $reset['email']]);
        
        // Mark token as used
        $stmt = $db->prepare("UPDATE password_resets SET used = 1 WHERE token = ?");
        $stmt->execute([$token]);
        
        die(json_encode(['success' => true, 'message' => 'Password reset successful']));
        
    } catch (Exception $e) {
        error_log("Password reset error: " . $e->getMessage());
        die(json_encode(['success' => false, 'message' => 'Failed to reset password']));
    }
}

die(json_encode(['success' => false, 'message' => 'Invalid action']));
