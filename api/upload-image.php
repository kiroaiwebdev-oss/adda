<?php
session_start();
require_once '../app/config/database.php';
require_once '../app/core/Auth.php';

$auth = new Auth($db);

// Check authentication
if (!$auth->check()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Check if admin
if ($auth->user()['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Admin access required']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!isset($_FILES['image'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No image provided']);
    exit;
}

$file = $_FILES['image'];
$type = $_POST['type'] ?? 'general';

// Validate file
$allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
if (!in_array($file['type'], $allowedTypes)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid file type. Only JPG, PNG, GIF, and WebP allowed']);
    exit;
}

// Check file size (2MB max)
if ($file['size'] > 2 * 1024 * 1024) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'File size must be less than 2MB']);
    exit;
}

// Create upload directory if not exists
$uploadDir = __DIR__ . '/../uploads/internships/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Generate unique filename
$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = uniqid('internship_') . '_' . time() . '.' . $extension;
$filepath = $uploadDir . $filename;

// Move uploaded file
if (move_uploaded_file($file['tmp_name'], $filepath)) {
    $url = '/uploads/internships/' . $filename;
    
    echo json_encode([
        'success' => true,
        'message' => 'Image uploaded successfully',
        'url' => $url,
        'filename' => $filename
    ]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to upload image']);
}
