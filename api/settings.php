<?php
// 🔥 PRODUCTION-READY API FOR SETTINGS
session_start(); // 🔥 FIXED: Add session before auth

// Error logging (not displaying to users)
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Set JSON header
header('Content-Type: application/json; charset=utf-8');

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ========================================
// DATABASE CONNECTION
// ========================================
try {
    $dbConfig = require __DIR__ . '/../app/config/database.php';
    
    if (!$dbConfig || !isset($dbConfig['host'])) {
        throw new Exception('Invalid database configuration');
    }
    
    $dsn = sprintf("mysql:host=%s;port=%s;dbname=%s;charset=%s",
        $dbConfig['host'], 
        $dbConfig['port'], 
        $dbConfig['database'], 
        $dbConfig['charset']
    );
    
    $db = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options']);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
} catch (PDOException $e) {
    error_log("Database connection error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'error' => 'Database connection failed. Please check logs.'
    ]);
    exit;
} catch (Exception $e) {
    error_log("Config error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'error' => 'Configuration error: ' . $e->getMessage()
    ]);
    exit;
}

// ========================================
// AUTOLOAD CLASSES
// ========================================
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

// ========================================
// AUTHENTICATION & AUTHORIZATION
// ========================================
try {
    // Check if Auth class exists
    if (!class_exists('Auth')) {
        throw new Exception('Auth class not found. Check app/core/Auth.php');
    }
    
    // Initialize Auth
    $auth = new Auth($db);
    
    // Check authentication
    if (!$auth->check()) {
        http_response_code(401);
        echo json_encode([
            'success' => false, 
            'error' => 'Unauthorized. Please login.',
            'redirect' => '/app/views/auth/login.php'
        ]);
        exit;
    }
    
    // Check if user is admin
    if (!$auth->isAdmin()) {
        http_response_code(403);
        echo json_encode([
            'success' => false, 
            'error' => 'Access denied. Admin privileges required.'
        ]);
        exit;
    }
    
} catch (Exception $e) {
    error_log("Auth error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => 'Authentication failed: ' . $e->getMessage()
    ]);
    exit;
}

// ========================================
// INITIALIZE CONTROLLER
// ========================================
try {
    // Check if Settings model exists
    if (!class_exists('Settings')) {
        throw new Exception('Settings model not found. Check app/models/Settings.php');
    }
    
    // Check if SettingsController exists
    if (!class_exists('SettingsController')) {
        throw new Exception('SettingsController not found. Check app/controllers/SettingsController.php');
    }
    
    // Initialize controller
    $controller = new SettingsController($db, $auth);
    
} catch (Exception $e) {
    error_log("Controller initialization error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => 'Controller initialization failed: ' . $e->getMessage()
    ]);
    exit;
}

// ========================================
// HANDLE API ACTIONS
// ========================================
try {
    // Get action from query parameter
    $action = $_GET['action'] ?? 'get';
    
    // Route to appropriate method
    switch ($action) {
        case 'get':
            // Get all settings
            echo $controller->getAll();
            break;
            
        case 'get-single':
            // Get single setting
            $key = $_GET['key'] ?? '';
            if (empty($key)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false, 
                    'error' => 'Setting key is required'
                ]);
                exit;
            }
            echo $controller->get($key);
            break;
            
        case 'update':
            // 🔥 FIXED: Update settings with proper validation
            $input = file_get_contents('php://input');
            
            if (empty($input)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false, 
                    'error' => 'No data received in request body'
                ]);
                exit;
            }
            
            $data = json_decode($input, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                http_response_code(400);
                echo json_encode([
                    'success' => false, 
                    'error' => 'Invalid JSON data: ' . json_last_error_msg(),
                    'received' => substr($input, 0, 100) // First 100 chars for debugging
                ]);
                exit;
            }
            
            if (!is_array($data) || empty($data)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false, 
                    'error' => 'Data must be a non-empty object'
                ]);
                exit;
            }
            
            // 🔥 Log for debugging
            error_log("Settings update request: " . count($data) . " settings");
            
            // Call controller update method
            echo $controller->update($data);
            break;
            
        case 'update-single':
            // Update single setting
            $input = file_get_contents('php://input');
            $data = json_decode($input, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                http_response_code(400);
                echo json_encode([
                    'success' => false, 
                    'error' => 'Invalid JSON data'
                ]);
                exit;
            }
            
            $key = $data['key'] ?? '';
            $value = $data['value'] ?? '';
            
            if (empty($key)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false, 
                    'error' => 'Setting key is required'
                ]);
                exit;
            }
            
            echo $controller->updateSingle($key, $value);
            break;
            
        case 'reset':
            // Reset to defaults (requires confirmation)
            echo $controller->reset();
            break;
            
        case 'public':
            // Get public settings (minimal auth required)
            echo $controller->getPublic();
            break;
            
        default:
            http_response_code(400);
            echo json_encode([
                'success' => false, 
                'error' => 'Invalid action: ' . htmlspecialchars($action),
                'available_actions' => ['get', 'get-single', 'update', 'update-single', 'reset', 'public']
            ]);
            break;
    }
    
} catch (Exception $e) {
    error_log("API error in action '$action': " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => 'An error occurred: ' . $e->getMessage(),
        'action' => $action ?? 'unknown'
    ]);
}
