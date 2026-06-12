<?php
/**
 * Internship Modules API
 * Handles CRUD operations for internship modules
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
        case 'list':
            $internshipId = $_GET['internship_id'] ?? null;
            if (!$internshipId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Internship ID required']);
                return;
            }
            
            $stmt = $db->prepare("SELECT * FROM internship_modules WHERE internship_id = ? ORDER BY sort_order ASC, id ASC");
            $stmt->execute([$internshipId]);
            $modules = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'data' => $modules]);
            break;
            
        case 'get':
            $id = $_GET['id'] ?? null;
            if (!$id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Module ID required']);
                return;
            }
            
            $stmt = $db->prepare("SELECT * FROM internship_modules WHERE id = ?");
            $stmt->execute([$id]);
            $module = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$module) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Module not found']);
                return;
            }
            
            echo json_encode(['success' => true, 'data' => $module]);
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
            if (empty($data['internship_id']) || empty($data['title'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Internship ID and title are required']);
                return;
            }
            
            // Get current max sort_order
            $stmt = $db->prepare("SELECT MAX(sort_order) as max_order FROM internship_modules WHERE internship_id = ?");
            $stmt->execute([$data['internship_id']]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $sortOrder = ($result['max_order'] ?? 0) + 1;
            
            $sql = "INSERT INTO internship_modules (internship_id, title, description, sort_order) VALUES (?, ?, ?, ?)";
            $stmt = $db->prepare($sql);
            $success = $stmt->execute([
                $data['internship_id'],
                $data['title'],
                $data['description'] ?? null,
                $sortOrder
            ]);
            
            if ($success) {
                $id = $db->lastInsertId();
                $stmt = $db->prepare("SELECT * FROM internship_modules WHERE id = ?");
                $stmt->execute([$id]);
                $module = $stmt->fetch(PDO::FETCH_ASSOC);
                
                echo json_encode(['success' => true, 'message' => 'Module created successfully', 'data' => $module]);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to create module']);
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
                echo json_encode(['success' => false, 'message' => 'Module ID required']);
                return;
            }
            
            $sql = "UPDATE internship_modules SET title = ?, description = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
            $stmt = $db->prepare($sql);
            $success = $stmt->execute([
                $data['title'],
                $data['description'] ?? null,
                $id
            ]);
            
            if ($success) {
                echo json_encode(['success' => true, 'message' => 'Module updated successfully']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to update module']);
            }
            break;
            
        case 'reorder':
            // Update sort orders
            if (empty($data['modules'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Modules array required']);
                return;
            }
            
            $db->beginTransaction();
            try {
                $stmt = $db->prepare("UPDATE internship_modules SET sort_order = ? WHERE id = ?");
                foreach ($data['modules'] as $index => $moduleId) {
                    $stmt->execute([$index, $moduleId]);
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
                echo json_encode(['success' => false, 'message' => 'Module ID required']);
                return;
            }
            
            $stmt = $db->prepare("DELETE FROM internship_modules WHERE id = ?");
            $success = $stmt->execute([$id]);
            
            if ($success) {
                echo json_encode(['success' => true, 'message' => 'Module deleted successfully']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to delete module']);
            }
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
}
