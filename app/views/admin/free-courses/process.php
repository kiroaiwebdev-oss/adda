<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Correct path to database config
require_once __DIR__ . '/../../../config/database.php';
$dbConfig = require __DIR__ . '/../../../config/database.php';

$dsn = sprintf("mysql:host=%s;port=%s;dbname=%s;charset=%s",
    $dbConfig['host'], 
    $dbConfig['port'], 
    $dbConfig['database'], 
    $dbConfig['charset']
);

try {
    $db = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options']);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

spl_autoload_register(function ($class) {
    $paths = [
        __DIR__ . '/../../../core/' . $class . '.php',
        __DIR__ . '/../../../models/' . $class . '.php',
        __DIR__ . '/../../../controllers/' . $class . '.php',
    ];
    foreach ($paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            return;
        }
    }
});

$auth = new Auth($db);
$auth->requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: assign.php');
    exit;
}

try {
    $user_id = (int)$_POST['user_id'];
    $course_id = (int)$_POST['course_id'];
    
    // Validate inputs
    if (empty($user_id) || empty($course_id)) {
        throw new Exception('User and Course are required');
    }
    
    // Check if user exists and is active learner
    $userStmt = $db->prepare("SELECT id, name, email FROM users WHERE id = ? AND role = 'learner' AND status = 'active'");
    $userStmt->execute([$user_id]);
    $user = $userStmt->fetch();
    
    if (!$user) {
        throw new Exception('Invalid or inactive user');
    }
    
    // Check if course exists and is published
    $courseStmt = $db->prepare("SELECT id, title, price FROM courses WHERE id = ? AND status = 'published'");
    $courseStmt->execute([$course_id]);
    $course = $courseStmt->fetch();
    
    if (!$course) {
        throw new Exception('Invalid or unpublished course');
    }
    
    // Check if already enrolled
    $enrollmentCheck = $db->prepare("SELECT id FROM enrollments WHERE user_id = ? AND course_id = ?");
    $enrollmentCheck->execute([$user_id, $course_id]);
    
    if ($enrollmentCheck->fetch()) {
        throw new Exception('User is already enrolled in this course');
    }
    
    // Create enrollment (simple version - only existing columns)
    $insertStmt = $db->prepare("
        INSERT INTO enrollments (
            user_id, 
            course_id, 
            enrolled_at,
            progress_percent
        ) VALUES (?, ?, NOW(), 0.00)
    ");
    
    $insertStmt->execute([
        $user_id,
        $course_id
    ]);
    
    // Success - redirect with success message
    header('Location: assign.php?success=1');
    exit;
    
} catch (Exception $e) {
    // Error - redirect with error message
    header('Location: assign.php?error=' . urlencode($e->getMessage()));
    exit;
}
?>
