<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display, we'll log them
ini_set('log_errors', 1);

session_start();
require_once __DIR__ . '/../app/config/database.php';

spl_autoload_register(function ($class) {
    $paths = [
        __DIR__ . '/../app/core/' . $class . '.php',
        __DIR__ . '/../app/models/' . $class . '.php',
    ];
    foreach ($paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            return;
        }
    }
});

// Set content type first
header('Content-Type: application/json');

try {
    $dbConfig = require __DIR__ . '/../app/config/database.php';
    $dsn = sprintf("mysql:host=%s;port=%s;dbname=%s;charset=%s",
        $dbConfig['host'], $dbConfig['port'], $dbConfig['database'], $dbConfig['charset']
    );
    $db = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options']);

    $auth = new Auth($db);
    $auth->requireAdmin();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'error' => 'Invalid request method']);
        exit;
    }

    $courseId = $_POST['course_id'] ?? null;

    if (!$courseId) {
        echo json_encode(['success' => false, 'error' => 'Course ID required']);
        exit;
    }

    if (!isset($_FILES['cover_image'])) {
        echo json_encode(['success' => false, 'error' => 'No file uploaded - FILES array empty']);
        exit;
    }

    if ($_FILES['cover_image']['error'] !== UPLOAD_ERR_OK) {
        $errorMsg = 'Upload error: ';
        switch ($_FILES['cover_image']['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $errorMsg .= 'File too large';
                break;
            case UPLOAD_ERR_NO_FILE:
                $errorMsg .= 'No file selected';
                break;
            default:
                $errorMsg .= 'Error code ' . $_FILES['cover_image']['error'];
        }
        echo json_encode(['success' => false, 'error' => $errorMsg]);
        exit;
    }

    $file = $_FILES['cover_image'];
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
    $maxSize = 5 * 1024 * 1024; // 5MB

    // Validate file type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, $allowedTypes) && !in_array($file['type'], $allowedTypes)) {
        echo json_encode(['success' => false, 'error' => 'Invalid file type. Only JPG, PNG, WEBP allowed. Detected: ' . $mimeType]);
        exit;
    }

    // Validate file size
    if ($file['size'] > $maxSize) {
        echo json_encode(['success' => false, 'error' => 'File size exceeds 5MB']);
        exit;
    }

    // Create upload directory
    $uploadDir = __DIR__ . '/../public/uploads/course-covers/';
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            echo json_encode(['success' => false, 'error' => 'Failed to create upload directory']);
            exit;
        }
    }

    // Check if directory is writable
    if (!is_writable($uploadDir)) {
        echo json_encode(['success' => false, 'error' => 'Upload directory is not writable']);
        exit;
    }

    // Generate unique filename
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (empty($extension)) {
        $extension = 'jpg'; // default
    }
    $filename = 'course_' . $courseId . '_' . uniqid() . '.' . $extension;
    $destination = $uploadDir . $filename;

    // Delete old cover image if exists
    $courseModel = new Course($db);
    $stmt = $db->prepare("SELECT cover_image FROM courses WHERE id = :id");
    $stmt->execute(['id' => $courseId]);
    $course = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($course && !empty($course['cover_image'])) {
        $oldFile = __DIR__ . '/../public/uploads/course-covers/' . basename($course['cover_image']);
        if (file_exists($oldFile)) {
            @unlink($oldFile);
        }
    }

    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $destination)) {
        $relativePath = '/public/uploads/course-covers/' . $filename;
        
        // Update database
        $updateStmt = $db->prepare("UPDATE courses SET cover_image = :cover_image, updated_at = NOW() WHERE id = :course_id");
        $updateResult = $updateStmt->execute([
            'cover_image' => $relativePath,
            'course_id' => $courseId
        ]);
        
        if ($updateResult) {
            echo json_encode([
                'success' => true,
                'image_url' => $relativePath,
                'message' => 'Cover image uploaded successfully'
            ]);
        } else {
            @unlink($destination);
            echo json_encode(['success' => false, 'error' => 'Database update failed']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to move uploaded file']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}
exit;
