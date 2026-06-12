<?php
header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../app/config/database.php';

$dbConfig = require __DIR__ . '/../app/config/database.php';
$dsn = sprintf("mysql:host=%s;port=%s;dbname=%s;charset=%s",
    $dbConfig['host'], $dbConfig['port'], $dbConfig['database'], $dbConfig['charset']
);

try {
    $db = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options']);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
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

// Check authentication
$auth = new Auth($db);
if (!$auth->check()) {
    echo json_encode(['success' => false, 'error' => 'Please login first']);
    exit;
}

$userId = $auth->id();
$action = $_GET['action'] ?? '';

// Submit Review
if ($action === 'submit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $courseId = (int)$_POST['course_id'];
        $rating = (int)$_POST['rating'];
        $reviewText = trim($_POST['review_text'] ?? '');
        
        // Validate
        if (empty($courseId) || empty($rating) || $rating < 1 || $rating > 5) {
            throw new Exception('Invalid input');
        }
        
        // Check if enrolled
        $enrollCheck = $db->prepare("SELECT id FROM enrollments WHERE user_id = ? AND course_id = ?");
        $enrollCheck->execute([$userId, $courseId]);
        if (!$enrollCheck->fetch()) {
            throw new Exception('You must be enrolled to leave a review');
        }
        
        // Check if already reviewed
        $reviewCheck = $db->prepare("SELECT id FROM course_reviews WHERE user_id = ? AND course_id = ?");
        $reviewCheck->execute([$userId, $courseId]);
        if ($reviewCheck->fetch()) {
            throw new Exception('You have already reviewed this course');
        }
        
        // Insert review
        $stmt = $db->prepare("
            INSERT INTO course_reviews (course_id, user_id, rating, review_text, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$courseId, $userId, $rating, $reviewText]);
        
        echo json_encode(['success' => true, 'message' => 'Review submitted successfully']);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Get Reviews
if ($action === 'get' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $courseId = (int)($_GET['course_id'] ?? 0);
        
        if (empty($courseId)) {
            throw new Exception('Course ID required');
        }
        
        $stmt = $db->prepare("
            SELECT cr.*, u.name as user_name
            FROM course_reviews cr
            JOIN users u ON cr.user_id = u.id
            WHERE cr.course_id = ?
            ORDER BY cr.created_at DESC
        ");
        $stmt->execute([$courseId]);
        $reviews = $stmt->fetchAll();
        
        echo json_encode(['success' => true, 'reviews' => $reviews]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'error' => 'Invalid action']);
