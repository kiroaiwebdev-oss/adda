<?php
/**
 * Internships API
 * Handles CRUD operations for internships
 */

header('Content-Type: application/json');
require_once '../app/config/database.php';
require_once '../app/core/Auth.php';
require_once '../app/models/Internship.php';

$auth = new Auth($db);
$internshipModel = new Internship($db);

// Check if user is authenticated
if (!$auth->check()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user = $auth->user();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    switch ($method) {
        case 'GET':
            handleGet($internshipModel, $action, $user);
            break;
            
        case 'POST':
            handlePost($internshipModel, $action, $user);
            break;
            
        case 'PUT':
            handlePut($internshipModel, $action, $user);
            break;
            
        case 'DELETE':
            handleDelete($internshipModel, $action, $user);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function handleGet($model, $action, $user) {
    switch ($action) {
        case 'list':
            $filters = [];
            
            // Admin can see all, learners see only active
            if ($user['role'] !== 'admin') {
                $filters['is_active'] = 1;
            }
            
            if (isset($_GET['category'])) {
                $filters['category'] = $_GET['category'];
            }
            
            if (isset($_GET['skill_level'])) {
                $filters['skill_level'] = $_GET['skill_level'];
            }
            
            $internships = $model->getAll($filters);
            
            // Add enrollment count for each
            foreach ($internships as &$internship) {
                $internship['enrollment_count'] = $model->getEnrollmentCount($internship['id']);
                $internship['is_full'] = $model->isEnrollmentFull($internship['id']);
            }
            
            echo json_encode(['success' => true, 'data' => $internships]);
            break;
            
        case 'get':
            $id = $_GET['id'] ?? null;
            if (!$id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'ID required']);
                return;
            }
            
            $internship = $model->getById($id);
            if (!$internship) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Internship not found']);
                return;
            }
            
            // Add stats
            $internship['enrollment_count'] = $model->getEnrollmentCount($id);
            $internship['is_full'] = $model->isEnrollmentFull($id);
            $internship['stats'] = $model->getStats($id);
            
            echo json_encode(['success' => true, 'data' => $internship]);
            break;
            
        case 'by-slug':
            $slug = $_GET['slug'] ?? null;
            if (!$slug) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Slug required']);
                return;
            }
            
            $internship = $model->getBySlug($slug);
            if (!$internship) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Internship not found']);
                return;
            }
            
            echo json_encode(['success' => true, 'data' => $internship]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
}

function handlePost($model, $action, $user) {
    // Only admin can create
    if ($user['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Admin access required']);
        return;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    switch ($action) {
        case 'create':
            // Validate required fields
            $required = ['title', 'description', 'duration_weeks', 'skill_level', 'category'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => ucfirst($field) . ' is required']);
                    return;
                }
            }
            
            // Generate slug
            $data['slug'] = $model->generateSlug($data['title']);
            
            // Add pricing fields
            $data['price'] = $data['price'] ?? 0;
            $data['discount_price'] = $data['discount_price'] ?? null;
            $data['discount_percentage'] = $data['discount_percentage'] ?? null;
            
            $id = $model->create($data);
            if ($id) {
                $internship = $model->getById($id);
                echo json_encode(['success' => true, 'message' => 'Internship created successfully', 'data' => $internship]);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to create internship']);
            }
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
}

function handlePut($model, $action, $user) {
    // Only admin can update
    if ($user['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Admin access required']);
        return;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    switch ($action) {
        case 'update':
            $id = $data['id'] ?? null;
            if (!$id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'ID required']);
                return;
            }
            
            // Get existing internship to preserve slug if title not changed
            $existing = $model->getById($id);
            if (!$existing) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Internship not found']);
                return;
            }
            
            // Generate new slug if title changed
            if (isset($data['title']) && $data['title'] !== $existing['title']) {
                $data['slug'] = $model->generateSlug($data['title'], $id);
            } else {
                $data['slug'] = $existing['slug'];
            }
            
            // Ensure pricing fields exist
            $data['price'] = $data['price'] ?? $existing['price'] ?? 0;
            $data['discount_price'] = $data['discount_price'] ?? $existing['discount_price'] ?? null;
            $data['discount_percentage'] = $data['discount_percentage'] ?? $existing['discount_percentage'] ?? null;
            
            // Preserve other fields if not provided
            $data['cover_image'] = $data['cover_image'] ?? $existing['cover_image'];
            $data['certificate_template_id'] = $data['certificate_template_id'] ?? $existing['certificate_template_id'];
            
            $result = $model->update($id, $data);
            if ($result) {
                $internship = $model->getById($id);
                echo json_encode(['success' => true, 'message' => 'Internship updated successfully', 'data' => $internship]);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to update internship']);
            }
            break;
            
        case 'toggle-status':
            $id = $data['id'] ?? null;
            if (!$id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'ID required']);
                return;
            }
            
            $result = $model->toggleStatus($id);
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Status updated successfully']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to update status']);
            }
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
}

function handleDelete($model, $action, $user) {
    // Only admin can delete
    if ($user['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Admin access required']);
        return;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    switch ($action) {
        case 'delete':
            $id = $data['id'] ?? null;
            if (!$id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'ID required']);
                return;
            }
            
            $result = $model->delete($id);
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Internship deleted successfully']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to delete internship']);
            }
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
}
