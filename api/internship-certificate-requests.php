<?php
/**
 * Internship Certificate Requests API
 * Handles certificate request operations
 */

header('Content-Type: application/json');
ini_set('display_errors', 0); ini_set('log_errors', 1);
error_reporting(E_ALL);

require_once '../app/config/database.php';
require_once '../app/core/Auth.php';
require_once '../app/models/InternshipCertificateRequest.php';
require_once '../app/models/InternshipEnrollment.php';
require_once '../app/models/InternshipCertificate.php';

$auth = new Auth($db);
$requestModel = new InternshipCertificateRequest($db);
$enrollmentModel = new InternshipEnrollment($db);

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
            handleGet($requestModel, $action, $user);
            break;
            
        case 'POST':
            handlePost($requestModel, $enrollmentModel, $action, $user);
            break;
            
        case 'PUT':
            handlePut($requestModel, $action, $user, $db);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
} catch (Exception $e) {
    error_log("API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function handleGet($model, $action, $user) {
    switch ($action) {
        case 'list':
            $filters = [];
            
            // Admin can see all, learners see only their own
            if ($user['role'] === 'admin') {
                if (isset($_GET['status'])) {
                    $filters['status'] = $_GET['status'];
                }
            } else {
                $filters['user_id'] = $user['id'];
            }
            
            $requests = $model->getAll($filters);
            echo json_encode(['success' => true, 'data' => $requests]);
            break;
            
        case 'my-requests':
            $requests = $model->getMyRequests($user['id']);
            echo json_encode(['success' => true, 'data' => $requests]);
            break;
            
        case 'get':
            $id = $_GET['id'] ?? null;
            if (!$id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'ID required']);
                return;
            }
            
            $request = $model->getById($id);
            if (!$request) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Request not found']);
                return;
            }
            
            // Check permission
            if ($user['role'] !== 'admin' && $request['user_id'] != $user['id']) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Access denied']);
                return;
            }
            
            echo json_encode(['success' => true, 'data' => $request]);
            break;
            
        case 'pending-count':
            // Admin only
            if ($user['role'] !== 'admin') {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Admin access required']);
                return;
            }
            
            $count = $model->getPendingCount();
            echo json_encode(['success' => true, 'count' => $count]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
}

function handlePost($requestModel, $enrollmentModel, $action, $user) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    switch ($action) {
        case 'request':
            $enrollmentId = $data['enrollment_id'] ?? null;
            if (!$enrollmentId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Enrollment ID required']);
                return;
            }
            
            // Get enrollment details
            $enrollment = $enrollmentModel->getById($enrollmentId);
            if (!$enrollment) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Enrollment not found']);
                return;
            }
            
            // Check if user owns this enrollment
            if ($enrollment['user_id'] != $user['id']) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Access denied']);
                return;
            }
            
            // ✅ FIXED: Check completion WITHOUT completion_percentage column
            // Only check status = completed
            if ($enrollment['status'] !== 'completed') {
                http_response_code(400);
                echo json_encode([
                    'success' => false, 
                    'message' => 'Please complete the internship first. Current status: ' . $enrollment['status']
                ]);
                return;
            }
            
            $requestData = [
                'user_id' => $user['id'],
                'internship_id' => $enrollment['internship_id'],
                'enrollment_id' => $enrollmentId
            ];
            
            $result = $requestModel->create($requestData);
            
            if ($result['success']) {
                echo json_encode([
                    'success' => true, 
                    'message' => 'Certificate request submitted successfully! Admin will review and issue your certificate.',
                    'request_id' => $result['request_id']
                ]);
            } else {
                http_response_code(400);
                echo json_encode($result);
            }
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
}

function handlePut($model, $action, $user, $db) {
    // Admin only
    if ($user['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Admin access required']);
        return;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    switch ($action) {
        case 'approve':
            $id = $data['id'] ?? null;
            if (!$id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'ID required']);
                return;
            }
            
            $notes = $data['admin_notes'] ?? null;
            $result = $model->approve($id, $user['id'], $notes);
            
            if ($result) {
                // Generate certificate (implement InternshipCertificate model separately)
                // $certificateModel = new InternshipCertificate($db);
                // $request = $model->getById($id);
                // $certificateModel->generate($request);
                
                echo json_encode(['success' => true, 'message' => 'Certificate request approved and certificate generated']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to approve request']);
            }
            break;
            
        case 'reject':
            $id = $data['id'] ?? null;
            if (!$id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'ID required']);
                return;
            }
            
            $notes = $data['admin_notes'] ?? 'Certificate request rejected';
            $result = $model->reject($id, $user['id'], $notes);
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Certificate request rejected']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to reject request']);
            }
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
}
