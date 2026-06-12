<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once __DIR__ . '/../app/config/database.php';

$dbConfig = require __DIR__ . '/../app/config/database.php';
$dsn = sprintf("mysql:host=%s;port=%s;dbname=%s;charset=%s",
    $dbConfig['host'], $dbConfig['port'], $dbConfig['database'], $dbConfig['charset']
);

try {
    $db = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options']);
} catch (PDOException $e) {
    die(json_encode(['success' => false, 'error' => 'Database connection failed']));
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

header('Content-Type: application/json');

// Check authentication
$auth = new Auth($db);
if (!$auth->check()) {
    echo json_encode(['success' => false, 'error' => 'Please login to submit a review']);
    exit;
}

$userId = $auth->id();
$action = $_GET['action'] ?? '';

if ($action === 'submit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $internshipId = intval($_POST['internship_id'] ?? 0);
    $rating = intval($_POST['rating'] ?? 0);
    $reviewText = trim($_POST['review_text'] ?? '');
    
    if (!$internshipId || !$rating) {
        echo json_encode(['success' => false, 'error' => 'Internship ID and rating are required']);
        exit;
    }
    
    if ($rating < 1 || $rating > 5) {
        echo json_encode(['success' => false, 'error' => 'Rating must be between 1 and 5']);
        exit;
    }
    
    try {
        // Check if user has applied
        $checkEnrollment = $db->prepare("SELECT id FROM internship_enrollments WHERE user_id = ? AND internship_id = ?");
        $checkEnrollment->execute([$userId, $internshipId]);
        
        if (!$checkEnrollment->fetch()) {
            echo json_encode(['success' => false, 'error' => 'You can only review internships you have applied for']);
            exit;
        }
        
        // Check if already reviewed
        $checkReview = $db->prepare("SELECT id FROM internship_reviews WHERE user_id = ? AND internship_id = ?");
        $checkReview->execute([$userId, $internshipId]);
        
        if ($checkReview->fetch()) {
            echo json_encode(['success' => false, 'error' => 'You have already reviewed this internship']);
            exit;
        }
        
        // Insert review
        $insertStmt = $db->prepare("
            INSERT INTO internship_reviews (user_id, internship_id, rating, review_text) 
            VALUES (?, ?, ?, ?)
        ");
        
        $insertStmt->execute([$userId, $internshipId, $rating, $reviewText]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Review submitted successfully',
            'review_id' => $db->lastInsertId()
        ]);
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
    
    exit;
}

echo json_encode(['success' => false, 'error' => 'Invalid action']);
?>
