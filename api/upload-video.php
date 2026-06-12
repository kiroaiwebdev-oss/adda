<?php
header('Content-Type: application/json');
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

if (!isset($_FILES['video'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No video uploaded']);
    exit;
}

$file = $_FILES['video'];
$type = $_POST['type'] ?? 'general';

// Validate file
$allowedTypes = ['video/mp4', 'video/webm', 'video/ogg', 'video/quicktime'];
$maxSize = 50 * 1024 * 1024; // 50MB

if (!in_array($file['type'], $allowedTypes)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid file type. Only MP4, WEBM, OGG, MOV allowed']);
    exit;
}

if ($file['size'] > $maxSize) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'File size must be less than 50MB']);
    exit;
}

// Create upload directory
$uploadDir = __DIR__ . '/../uploads/content/videos/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Generate unique filename
$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = uniqid('vid_') . '_' . time() . '.' . $extension;
$filepath = $uploadDir . $filename;

// Move uploaded file
if (move_uploaded_file($file['tmp_name'], $filepath)) {
    $url = '/uploads/content/videos/' . $filename;
    echo json_encode([
        'success' => true,
        'message' => 'Video uploaded successfully',
        'url' => $url,
        'filename' => $filename
    ]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to upload video']);
}
