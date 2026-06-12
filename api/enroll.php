<?php
session_start();
header('Content-Type: application/json');

try {
    // Database connection
    $dbConfig = require __DIR__ . '/../app/config/database.php';
    $dsn = sprintf("mysql:host=%s;port=%s;dbname=%s;charset=%s",
        $dbConfig['host'], $dbConfig['port'], $dbConfig['database'], $dbConfig['charset']
    );
    $db = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options']);
    
    // Autoload
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
    
    // Auth check
    $auth = new Auth($db);
    if (!$auth->check()) {
        echo json_encode(['success' => false, 'error' => 'Please login first']);
        exit;
    }
    
    // Get input
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    $courseId = $data['course_id'] ?? $_POST['course_id'] ?? null;
    
    if (!$courseId) {
        echo json_encode(['success' => false, 'error' => 'Course ID is required']);
        exit;
    }
    
    $userId = $auth->user()['id'];
    
    // Check if already enrolled
    $checkStmt = $db->prepare("
        SELECT id FROM enrollments 
        WHERE user_id = ? AND course_id = ?
    ");
    $checkStmt->execute([$userId, $courseId]);
    
    if ($checkStmt->rowCount() > 0) {
        echo json_encode(['success' => false, 'error' => 'Already enrolled in this course']);
        exit;
    }
    
    // Get course details
    $courseStmt = $db->prepare("SELECT * FROM courses WHERE id = ?");
    $courseStmt->execute([$courseId]);
    $course = $courseStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$course) {
        echo json_encode(['success' => false, 'error' => 'Course not found']);
        exit;
    }
    
    // Check if course is free or paid
    if ($course['price'] > 0) {
        // Paid course - redirect to payment
        echo json_encode([
            'success' => true,
            'redirect' => '/app/views/student/checkout.php?course_id=' . $courseId
        ]);
        exit;
    }
    
    // Free course - enroll directly
    $enrollStmt = $db->prepare("
        INSERT INTO enrollments (user_id, course_id, enrolled_at, status) 
        VALUES (?, ?, NOW(), 'active')
    ");
    
    if ($enrollStmt->execute([$userId, $courseId])) {
        echo json_encode([
            'success' => true,
            'message' => 'Enrolled successfully!',
            'redirect' => '/app/views/student/course-player.php?course_id=' . $courseId
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to enroll']);
    }
    
} catch (Exception $e) {
    error_log("Enrollment error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'error' => 'Server error: ' . $e->getMessage()
    ]);
}
