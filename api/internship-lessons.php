<?php
/**
 * Internship Lessons API
 * Handles CRUD operations for internship lessons
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
if (!in_array($auth->user()['role'], ['admin','manager'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Admin or Manager access required']);
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
        case 'list':
            $moduleId = $_GET['module_id'] ?? null;
            if (!$moduleId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Module ID required']);
                return;
            }
            
            $stmt = $db->prepare("SELECT * FROM internship_lessons WHERE module_id = ? ORDER BY sort_order ASC, id ASC");
            $stmt->execute([$moduleId]);
            $lessons = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'data' => $lessons]);
            break;
            
        case 'get':
            $id = $_GET['id'] ?? null;
            if (!$id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Lesson ID required']);
                return;
            }
            
            $stmt = $db->prepare("SELECT * FROM internship_lessons WHERE id = ?");
            $stmt->execute([$id]);
            $lesson = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$lesson) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Lesson not found']);
                return;
            }
            
            echo json_encode(['success' => true, 'data' => $lesson]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
}

function handlePost($db, $action) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    switch ($action) {
        case 'create':
            if (empty($data['module_id']) || empty($data['title'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Module ID and title are required']);
                return;
            }
            
            // Get current max sort_order
            $stmt = $db->prepare("SELECT MAX(sort_order) as max_order FROM internship_lessons WHERE module_id = ?");
            $stmt->execute([$data['module_id']]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $sortOrder = ($result['max_order'] ?? 0) + 1;
            
            $sql = "INSERT INTO internship_lessons (module_id, title, description, duration_minutes, sort_order) VALUES (?, ?, ?, ?, ?)";
            $stmt = $db->prepare($sql);
            $success = $stmt->execute([
                $data['module_id'],
                $data['title'],
                $data['description'] ?? null,
                $data['duration_minutes'] ?? 0,
                $sortOrder
            ]);
            
            if ($success) {
                $id = $db->lastInsertId();
                $stmt = $db->prepare("SELECT * FROM internship_lessons WHERE id = ?");
                $stmt->execute([$id]);
                $lesson = $stmt->fetch(PDO::FETCH_ASSOC);
                
                echo json_encode(['success' => true, 'message' => 'Lesson created successfully', 'data' => $lesson]);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to create lesson']);
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
                echo json_encode(['success' => false, 'message' => 'Lesson ID required']);
                return;
            }
            
            $sql = "UPDATE internship_lessons SET title = ?, description = ?, duration_minutes = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
            $stmt = $db->prepare($sql);
            $success = $stmt->execute([
                $data['title'],
                $data['description'] ?? null,
                $data['duration_minutes'] ?? 0,
                $id
            ]);
            
            if ($success) {
                echo json_encode(['success' => true, 'message' => 'Lesson updated successfully']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to update lesson']);
            }
            break;
            
        case 'reorder':
            // Update sort orders
            if (empty($data['lessons'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Lessons array required']);
                return;
            }
            
            $db->beginTransaction();
            try {
                $stmt = $db->prepare("UPDATE internship_lessons SET sort_order = ? WHERE id = ?");
                foreach ($data['lessons'] as $index => $lessonId) {
                    $stmt->execute([$index, $lessonId]);
                }
                $db->commit();
                echo json_encode(['success' => true, 'message' => 'Order updated successfully']);
            } catch (Exception $e) {
                $db->rollBack();
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to update order']);
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
                echo json_encode(['success' => false, 'message' => 'Lesson ID required']);
                return;
            }
            
            $stmt = $db->prepare("DELETE FROM internship_lessons WHERE id = ?");
            $success = $stmt->execute([$id]);
            
            if ($success) {
                echo json_encode(['success' => true, 'message' => 'Lesson deleted successfully']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to delete lesson']);
            }
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
}
