<?php
/**
 * Authentication API Endpoint
 * Handles all authentication-related API requests
 * Updated with OTP Email Verification System + Auto-Login
 */

// CORS headers (adjust in production)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Error handling
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Helper function to send JSON response
function sendJsonResponse($success, $message, $data = [], $httpCode = null) {
    if ($httpCode) {
        http_response_code($httpCode);
    } else {
        http_response_code($success ? 200 : 400);
    }
    
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message
    ], $data));
    exit;
}

// Load dependencies
require_once __DIR__ . '/../app/config/database.php';

// Database connection
$dbConfig = require __DIR__ . '/../app/config/database.php';

try {
    $dsn = sprintf(
        "mysql:host=%s;port=%s;dbname=%s;charset=%s",
        $dbConfig['host'],
        $dbConfig['port'],
        $dbConfig['database'],
        $dbConfig['charset']
    );
    
    $db = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options']);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    error_log("Database connection error: " . $e->getMessage());
    sendJsonResponse(false, 'Database connection failed', [], 500);
}

// Autoload classes
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

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get action from query parameter
$action = $_GET['action'] ?? '';

// Get request body
$input = file_get_contents('php://input');
$data = json_decode($input, true) ?? [];

// Route to appropriate action
switch ($action) {
    
    // ============================================
    // STORE REGISTRATION (Before OTP)
    // ============================================
    case 'store-registration':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            sendJsonResponse(false, 'Method not allowed', [], 405);
        }
        
        try {
            $name = trim($data['name'] ?? '');
            $email = trim($data['email'] ?? '');
            $mobile = trim($data['mobile'] ?? '');
            $password = $data['password'] ?? '';
            $referralCode = trim($data['referral_code'] ?? '');
            
            // Basic validation
            if (empty($name) || empty($email) || empty($password)) {
                sendJsonResponse(false, 'Name, email and password are required');
            }
            
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                sendJsonResponse(false, 'Invalid email format');
            }
            
            // Check if email already exists
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                sendJsonResponse(false, 'Email already registered');
            }
            
            // Store in session temporarily
            $_SESSION['pending_registration'] = [
                'name' => $name,
                'email' => $email,
                'mobile' => $mobile,
                'password' => $password,
                'referral_code' => $referralCode,
                'timestamp' => time()
            ];
            
            $_SESSION['pending_verification_email'] = $email;
            $_SESSION['pending_verification_name'] = $name;
            
            sendJsonResponse(true, 'Registration data stored successfully', ['stored' => true]);
            
        } catch (Exception $e) {
            error_log("Store registration error: " . $e->getMessage());
            sendJsonResponse(false, 'Failed to store registration data');
        }
        break;
    
    // ============================================
    // COMPLETE REGISTRATION (After OTP) + AUTO-LOGIN
    // ============================================
    case 'register':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            sendJsonResponse(false, 'Method not allowed', [], 405);
        }
        
        try {
            // Check if coming from OTP verification flow
            $fromOTP = isset($_SESSION['verified_email']) && isset($_SESSION['pending_registration']);
            
            if (!$fromOTP) {
                sendJsonResponse(false, 'Email verification required. Please verify your email first.');
            }
            
            // Use data from session (after OTP verification)
            $regData = $_SESSION['pending_registration'];
            
            // Check if session expired (15 minutes)
            if (time() - $regData['timestamp'] > 900) {
                sendJsonResponse(false, 'Registration session expired. Please start again.');
            }
            
            $name = $regData['name'];
            $email = $regData['email'];
            $mobile = $regData['mobile'];
            $password = $regData['password'];
            $referralCode = $regData['referral_code'];

            // ✅ FIXED: Get redirect URL from session (set by verify-otp.php)
            $redirectUrl = $_SESSION['post_register_redirect'] ?? null;
            
            // Verify the email matches
            if ($_SESSION['verified_email'] !== $email) {
                sendJsonResponse(false, 'Email verification mismatch');
            }
            
            // Validate input
            if (empty($name) || empty($email) || empty($password)) {
                sendJsonResponse(false, 'Name, email and password are required');
            }
            
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                sendJsonResponse(false, 'Invalid email format');
            }
            
            if (!empty($mobile) && !preg_match('/^[0-9]{10}$/', $mobile)) {
                sendJsonResponse(false, 'Invalid mobile number. Must be 10 digits.');
            }
            
            if (strlen($password) < 6) {
                sendJsonResponse(false, 'Password must be at least 6 characters');
            }
            
            $db->beginTransaction();
            
            // Double-check if email exists
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $db->rollBack();
                sendJsonResponse(false, 'Email already registered');
            }
            
            // Generate unique referral code for new user
            $newUserReferralCode = 'REF' . strtoupper(substr(md5(uniqid($email, true)), 0, 8));
            
            // Make sure it's unique
            $checkCodeStmt = $db->prepare("SELECT id FROM users WHERE referral_code = ?");
            $attempts = 0;
            while ($attempts < 5) {
                $checkCodeStmt->execute([$newUserReferralCode]);
                if (!$checkCodeStmt->fetch()) {
                    break;
                }
                $newUserReferralCode = 'REF' . strtoupper(substr(md5(uniqid($email . $attempts, true)), 0, 8));
                $attempts++;
            }
            
            // Validate referral code if provided
            $referrerUserId = null;
            $referrerName = null;
            if ($referralCode) {
                $refStmt = $db->prepare("SELECT id, name FROM users WHERE referral_code = ? AND status = 'active' AND role = 'learner'");
                $refStmt->execute([$referralCode]);
                $referrer = $refStmt->fetch(PDO::FETCH_ASSOC);
                if ($referrer) {
                    $referrerUserId = $referrer['id'];
                    $referrerName = $referrer['name'];
                }
            }
            
            // Hash password
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert user with email_verified = 1 (already verified via OTP)
            $stmt = $db->prepare("
                INSERT INTO users (
                    name, email, mobile, password, role, referral_code, 
                    referred_by_code, email_verified, status, created_at
                ) 
                VALUES (?, ?, ?, ?, 'learner', ?, ?, 1, 'active', NOW())
            ");
            
            $stmt->execute([
                $name, 
                $email, 
                $mobile ?: null, 
                $hashedPassword, 
                $newUserReferralCode, 
                $referralCode ?: null
            ]);
            
            $newUserId = $db->lastInsertId();
            
            // Create referral relationship if valid referral code
            if ($referrerUserId) {
                $refInsert = $db->prepare("
                    INSERT INTO referrals (referrer_user_id, referred_user_id, referral_code, status, signup_date)
                    VALUES (?, ?, ?, 'pending', NOW())
                ");
                $refInsert->execute([$referrerUserId, $newUserId, $referralCode]);
            }
            
            $db->commit();
            
            // ✅ Clear old session data
            unset($_SESSION['pending_registration']);
            unset($_SESSION['verified_email']);
            unset($_SESSION['pending_verification_email']);
            unset($_SESSION['pending_verification_name']);
            unset($_SESSION['email_verified_at']);
            unset($_SESSION['post_register_redirect']); // ✅ Clear redirect session too
            
            // ✅ AUTO-LOGIN: Set user session
            $_SESSION['user_id'] = $newUserId;
            $_SESSION['user_role'] = 'learner';
            $_SESSION['user_name'] = $name;
            $_SESSION['user_email'] = $email;
            $_SESSION['logged_in'] = true;
            $_SESSION['login_time'] = time();
            
            // Log success
            error_log("✅ User registered and logged in: $email (ID: $newUserId)");
            
            // ✅ FIXED: Use redirect URL from session if available, else default dashboard
            $finalRedirect = (!empty($redirectUrl) && str_starts_with($redirectUrl, '/'))
                ? $redirectUrl
                : '/app/views/learner/dashboard.php';

            // Prepare success response
            $responseData = [
                'user_id' => $newUserId,
                'name' => $name,
                'email' => $email,
                'referral_code' => $newUserReferralCode,
                'email_verified' => true,
                'has_referral_bonus' => !empty($referrerUserId),
                'auto_login' => true,
                'redirect_url' => $finalRedirect // ✅ FIXED: Dynamic redirect
            ];
            
            if ($referrerUserId) {
                $responseData['referrer_name'] = $referrerName;
            }
            
            sendJsonResponse(true, 'Registration successful! Welcome to Internship Adda!', $responseData);
            
        } catch (PDOException $e) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            error_log("❌ Registration DB error: " . $e->getMessage());
            sendJsonResponse(false, 'Registration failed. Please try again.', [], 500);
        } catch (Exception $e) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            error_log("❌ Registration error: " . $e->getMessage());
            sendJsonResponse(false, 'An error occurred during registration', [], 500);
        }
        break;
        
    // ============================================
    // LOGIN
    // ============================================
    case 'login':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            sendJsonResponse(false, 'Method not allowed', [], 405);
        }
        
        try {
            if (class_exists('AuthController')) {
                $controller = new AuthController($db);
                $controller->login($data);
            } else {
                // Fallback manual login
                $email = trim($data['email'] ?? '');
                $password = $data['password'] ?? '';
                
                if (empty($email) || empty($password)) {
                    sendJsonResponse(false, 'Email and password are required');
                }
                
                $stmt = $db->prepare("SELECT * FROM users WHERE email = ? AND status = 'active'");
                $stmt->execute([$email]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$user || !password_verify($password, $user['password'])) {
                    sendJsonResponse(false, 'Invalid credentials');
                }
                
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['logged_in'] = true;
                
                sendJsonResponse(true, 'Login successful', [
                    'data' => [
                        'user_id' => $user['id'],
                        'name' => $user['name'],
                        'role' => $user['role'],
                        'email' => $user['email']
                    ]
                ]);
            }
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            sendJsonResponse(false, 'Login failed');
        }
        break;
        
    // ============================================
    // ADMIN LOGIN
    // ============================================
    case 'admin-login':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            sendJsonResponse(false, 'Method not allowed', [], 405);
        }
        
        try {
            $email = trim($data['email'] ?? '');
            $password = $data['password'] ?? '';
            
            if (empty($email) || empty($password)) {
                sendJsonResponse(false, 'Email and password are required');
            }
            
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                sendJsonResponse(false, 'Invalid email format');
            }

            $stmt = $db->prepare("SELECT * FROM users WHERE email = ? AND role = 'admin' AND status = 'active'");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                sendJsonResponse(false, 'Access denied. Admin credentials required.', [], 403);
            }

            if (!password_verify($password, $user['password'])) {
                sendJsonResponse(false, 'Invalid admin credentials', [], 401);
            }

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['logged_in'] = true;

            sendJsonResponse(true, 'Admin login successful', [
                'user_id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'role' => $user['role']
            ]);
            
        } catch (PDOException $e) {
            error_log("Admin login error: " . $e->getMessage());
            sendJsonResponse(false, 'Login failed. Please try again.');
        }
        break;
        
    // ============================================
    // LOGOUT
    // ============================================
    case 'logout':
        try {
            $_SESSION = array();
            
            if (isset($_COOKIE[session_name()])) {
                setcookie(session_name(), '', time() - 3600, '/');
            }
            
            session_destroy();
            
            if (isset($_COOKIE['user_id'])) {
                setcookie('user_id', '', time() - 3600, '/');
            }
            if (isset($_COOKIE['user_role'])) {
                setcookie('user_role', '', time() - 3600, '/');
            }
            
            header('Location: /public/index.html');
            exit;
            
        } catch (Exception $e) {
            error_log("Logout error: " . $e->getMessage());
            header('Location: /public/index.html');
            exit;
        }
        break;
        
    // ============================================
    // CHECK SESSION
    // ============================================
    case 'check-session':
        if (isset($_SESSION['user_id'])) {
            sendJsonResponse(true, 'User is logged in', [
                'logged_in' => true,
                'user_id' => $_SESSION['user_id'],
                'role' => $_SESSION['user_role'] ?? 'learner',
                'name' => $_SESSION['user_name'] ?? '',
                'email' => $_SESSION['user_email'] ?? ''
            ]);
        } else {
            sendJsonResponse(true, 'No active session', ['logged_in' => false]);
        }
        break;
        
    // ============================================
    // FORGOT PASSWORD
    // ============================================
    case 'forgot-password':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            sendJsonResponse(false, 'Method not allowed', [], 405);
        }
        
        try {
            if (class_exists('AuthController')) {
                $controller = new AuthController($db);
                $controller->forgotPassword($data);
            } else {
                sendJsonResponse(false, 'Feature not available');
            }
        } catch (Exception $e) {
            error_log("Forgot password error: " . $e->getMessage());
            sendJsonResponse(false, 'Failed to process request');
        }
        break;
        
    // ============================================
    // RESET PASSWORD
    // ============================================
    case 'reset-password':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            sendJsonResponse(false, 'Method not allowed', [], 405);
        }
        
        try {
            if (class_exists('AuthController')) {
                $controller = new AuthController($db);
                $controller->resetPassword($data);
            } else {
                sendJsonResponse(false, 'Feature not available');
            }
        } catch (Exception $e) {
            error_log("Reset password error: " . $e->getMessage());
            sendJsonResponse(false, 'Failed to process request');
        }
        break;
        
    // ============================================
    // PROFILE
    // ============================================
    case 'profile':
        try {
            if (class_exists('AuthController')) {
                $controller = new AuthController($db);
                $controller->profile();
            } else {
                if (!isset($_SESSION['user_id'])) {
                    sendJsonResponse(false, 'Not authenticated', [], 401);
                }
                
                $stmt = $db->prepare("SELECT id, name, email, mobile, role, referral_code, created_at FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user) {
                    sendJsonResponse(true, 'Profile retrieved', $user);
                } else {
                    sendJsonResponse(false, 'User not found');
                }
            }
        } catch (Exception $e) {
            error_log("Profile error: " . $e->getMessage());
            sendJsonResponse(false, 'Failed to fetch profile');
        }
        break;
        
    // ============================================
    // DEFAULT/INVALID ACTION
    // ============================================
    default:
        sendJsonResponse(false, 'Invalid action', [], 400);
        break;
}