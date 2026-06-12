<?php
/**
 * Internship Content API
 * Handles CRUD operations for lesson content blocks
 */

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

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    switch ($method) {
        case 'GET':
            handleGet($db, $action);
            break;
            
        case 'POST':
            handlePost($db, $action);
            break;
            
        case 'PUT':
            handlePut($db, $action);
            break;
            
        case 'DELETE':
            handleDelete($db, $action);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function handleGet($db, $action) {
    switch ($action) {
        case 'get':
            $lessonId = $_GET['lesson_id'] ?? null;
            if (!$lessonId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Lesson ID required']);
                return;
            }
            
            $stmt = $db->prepare("SELECT * FROM internship_content_blocks WHERE lesson_id = ? ORDER BY sort_order ASC, id ASC");
            $stmt->execute([$lessonId]);
            $content = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'data' => $content]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
}

function handlePost($db, $action) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    switch ($action) {
        case 'save':
            if (empty($data['lesson_id']) || empty($data['content'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Lesson ID and content are required']);
                return;
            }
            
            // Check if content block already exists for this lesson
            $stmt = $db->prepare("SELECT id FROM internship_content_blocks WHERE lesson_id = ? AND type = 'text' LIMIT 1");
            $stmt->execute([$data['lesson_id']]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existing) {
                // Update existing
                $sql = "UPDATE internship_content_blocks SET content = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
                $stmt = $db->prepare($sql);
                $success = $stmt->execute([
                    $data['content'],
                    $existing['id']
                ]);
            } else {
                // Create new
                $sql = "INSERT INTO internship_content_blocks (lesson_id, type, content, sort_order) VALUES (?, ?, ?, 0)";
                $stmt = $db->prepare($sql);
                $success = $stmt->execute([
                    $data['lesson_id'],
                    $data['type'] ?? 'text',
                    $data['content']
                ]);
            }
            
            if ($success) {
                echo json_encode(['success' => true, 'message' => 'Content saved successfully']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to save content']);
            }
            break;
            
        case 'add-block':
            if (empty($data['lesson_id']) || empty($data['type'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Lesson ID and type are required']);
                return;
            }
            
            // Get current max sort_order
            $stmt = $db->prepare("SELECT MAX(sort_order) as max_order FROM internship_content_blocks WHERE lesson_id = ?");
            $stmt->execute([$data['lesson_id']]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $sortOrder = ($result['max_order'] ?? 0) + 1;
            
            $sql = "INSERT INTO internship_content_blocks (lesson_id, type, content, title, sort_order) VALUES (?, ?, ?, ?, ?)";
            $stmt = $db->prepare($sql);
            $success = $stmt->execute([
                $data['lesson_id'],
                $data['type'],
                $data['content'] ?? null,
                $data['title'] ?? null,
                $sortOrder
            ]);
            
            if ($success) {
                $id = $db->lastInsertId();
                echo json_encode(['success' => true, 'message' => 'Content block added successfully', 'id' => $id]);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to add content block']);
            }
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
}

function handlePut($db, $action) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    switch ($action) {
        case 'update':
            $id = $data['id'] ?? null;
            if (!$id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Content block ID required']);
                return;
            }
            
            $sql = "UPDATE internship_content_blocks SET content = ?, title = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
            $stmt = $db->prepare($sql);
            $success = $stmt->execute([
                $data['content'] ?? null,
                $data['title'] ?? null,
                $id
            ]);
            
            if ($success) {
                echo json_encode(['success' => true, 'message' => 'Content block updated successfully']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to update content block']);
            }
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
}

function handleDelete($db, $action) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    switch ($action) {
        case 'delete':
            $id = $data['id'] ?? null;
            if (!$id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Content block ID required']);
                return;
            }
            
            $stmt = $db->prepare("DELETE FROM internship_content_blocks WHERE id = ?");
            $success = $stmt->execute([$id]);
            
            if ($success) {
                echo json_encode(['success' => true, 'message' => 'Content block deleted successfully']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to delete content block']);
            }
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
}
