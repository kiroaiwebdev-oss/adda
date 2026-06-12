<?php
/**
 * Banner API - Handles all banner operations
 * File: api/banners.php
 */

header('Content-Type: application/json');
session_start();

// Load database config
$dbConfig = require __DIR__ . '/../app/config/database.php';
$dsn = sprintf("mysql:host=%s;port=%s;dbname=%s;charset=%s",
    $dbConfig['host'], $dbConfig['port'], $dbConfig['database'], $dbConfig['charset']
);

try {
    $db = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Load Banner model
require_once __DIR__ . '/../app/models/Banner.php';
$banner = new Banner($db);

// Check admin authentication for write operations
$isAdmin = isset($_SESSION['user_id']) && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';

// GET request - Public or admin list
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $activeOnly = !isset($_GET['admin']);
    $banners = $banner->getAll($activeOnly);
    echo json_encode(['success' => true, 'banners' => $banners, 'count' => count($banners)]);
    exit;
}

// POST request - Admin operations only
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$isAdmin) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    // Handle JSON requests (toggle/delete)
    if (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? '';
        $id = $input['id'] ?? 0;

        if ($action === 'toggle' && $id) {
            if ($banner->toggleStatus($id)) {
                echo json_encode(['success' => true, 'message' => 'Status updated successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to update status']);
            }
            exit;
        }

        if ($action === 'delete' && $id) {
            if ($banner->delete($id)) {
                echo json_encode(['success' => true, 'message' => 'Banner deleted successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to delete banner']);
            }
            exit;
        }
    }

    // Handle form-data requests (create/update with file upload)
    $action = $_POST['action'] ?? '';
    $id = $_POST['id'] ?? 0;

    if ($action === 'create' || $action === 'update') {
        $data = [
            'title' => trim($_POST['title'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'link_url' => trim($_POST['link_url'] ?? ''),
            'display_order' => (int)($_POST['display_order'] ?? 0),
            'is_active' => isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1
        ];

        // Handle file upload
        if (isset($_FILES['banner_image']) && $_FILES['banner_image']['error'] === 0) {
            $uploadResult = uploadBannerImage($_FILES['banner_image']);
            if (!$uploadResult['success']) {
                echo json_encode(['success' => false, 'message' => $uploadResult['message']]);
                exit;
            }
            $data['image_path'] = $uploadResult['path'];
        } elseif ($action === 'update' && $id) {
            // Keep existing image
            $existing = $banner->getById($id);
            $data['image_path'] = $existing['image_path'];
        } else {
            echo json_encode(['success' => false, 'message' => 'Image is required']);
            exit;
        }

        if ($action === 'create') {
            if ($banner->create($data)) {
                echo json_encode(['success' => true, 'message' => 'Banner created successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to create banner']);
            }
        } else {
            if ($banner->update($id, $data)) {
                echo json_encode(['success' => true, 'message' => 'Banner updated successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to update banner']);
            }
        }
        exit;
    }
}

echo json_encode(['success' => false, 'message' => 'Invalid request']);

/**
 * Upload banner image
 */
function uploadBannerImage($file) {
    $uploadDir = __DIR__ . '/../public/uploads/banners/';

    // Create directory if not exists
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
    $maxSize = 5 * 1024 * 1024; // 5MB

    if (!in_array($file['type'], $allowedTypes)) {
        return ['success' => false, 'message' => 'Invalid file type. Only JPG, PNG, WEBP allowed'];
    }

    if ($file['size'] > $maxSize) {
        return ['success' => false, 'message' => 'File size must be less than 5MB'];
    }

    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'banner_' . uniqid() . '_' . time() . '.' . $extension;
    $filepath = 'public/uploads/banners/' . $filename;
    $fullPath = __DIR__ . '/../' . $filepath;

    if (move_uploaded_file($file['tmp_name'], $fullPath)) {
        return ['success' => true, 'path' => $filepath];
    }

    return ['success' => false, 'message' => 'Failed to upload file'];
}
?>
