<?php
/**
 * Internship Enrollments API
 * Handles enrollment operations
 */

header('Content-Type: application/json');
require_once '../app/config/database.php';
require_once '../app/core/Auth.php';
require_once '../app/models/InternshipEnrollment.php';
require_once '../app/models/Internship.php';

$auth = new Auth($db);
$enrollmentModel = new InternshipEnrollment($db);
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
            handleGet($enrollmentModel, $action, $user);
            break;
            
        case 'POST':
            handlePost($enrollmentModel, $internshipModel, $action, $user, $db);
            break;
            
        case 'PUT':
            handlePut($enrollmentModel, $action, $user);
            break;
            
        case 'DELETE':
            handleDelete($enrollmentModel, $action, $user);
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
            
            // Admin can see all, learners see only their own
            if ($user['role'] === 'admin') {
                if (isset($_GET['internship_id'])) {
                    $filters['internship_id'] = $_GET['internship_id'];
                }
                if (isset($_GET['user_id'])) {
                    $filters['user_id'] = $_GET['user_id'];
                }
            } else {
                $filters['user_id'] = $user['id'];
            }
            
            if (isset($_GET['status'])) {
                $filters['status'] = $_GET['status'];
            }
            
            $enrollments = $model->getAll($filters);
            echo json_encode(['success' => true, 'data' => $enrollments]);
            break;
            
        case 'my-internships':
            $internships = $model->getMyInternships($user['id']);
            echo json_encode(['success' => true, 'data' => $internships]);
            break;
            
        case 'get':
            $id = $_GET['id'] ?? null;
            if (!$id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'ID required']);
                return;
            }
            
            $enrollment = $model->getById($id);
            if (!$enrollment) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Enrollment not found']);
                return;
            }
            
            // Check permission
            if ($user['role'] !== 'admin' && $enrollment['user_id'] != $user['id']) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Access denied']);
                return;
            }
            
            echo json_encode(['success' => true, 'data' => $enrollment]);
            break;
            
        case 'check-enrollment':
            $internshipId = $_GET['internship_id'] ?? null;
            if (!$internshipId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Internship ID required']);
                return;
            }
            
            $isEnrolled = $model->isEnrolled($user['id'], $internshipId);
            $enrollment = $model->getUserEnrollment($user['id'], $internshipId);
            
            echo json_encode([
                'success' => true, 
                'is_enrolled' => $isEnrolled,
                'enrollment' => $enrollment
            ]);
            break;
            
        case 'stats':
            // Admin only
            if ($user['role'] !== 'admin') {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Admin access required']);
                return;
            }
            
            $internshipId = $_GET['internship_id'] ?? null;
            $stats = $model->getCountByStatus($internshipId);
            echo json_encode(['success' => true, 'data' => $stats]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
}

function handlePost($enrollmentModel, $internshipModel, $action, $user, $db) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    switch ($action) {
        case 'enroll':
            $internshipId = $data['internship_id'] ?? null;
            if (!$internshipId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Internship ID required']);
                return;
            }
            
            // Check if internship exists
            $internship = $internshipModel->getById($internshipId);
            if (!$internship) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Internship not found']);
                return;
            }
            
            // Check if enrollment is full
            if ($internshipModel->isEnrollmentFull($internshipId)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Internship enrollment is full']);
                return;
            }
            
            // Calculate end date
            $startDate = date('Y-m-d');
            $endDate = date('Y-m-d', strtotime("+{$internship['duration_weeks']} weeks"));
            
            $enrollmentData = [
                'internship_id' => $internshipId,
                'user_id' => $user['id'],
                'status' => 'active',
                'start_date' => $startDate,
                'end_date' => $endDate,
                'completion_percentage' => 0,
                'offer_letter_sent' => 0
            ];
            
            $result = $enrollmentModel->create($enrollmentData);
            
            if ($result['success']) {
                // Send offer letter email (implement this later)
                $enrollmentModel->sendOfferLetter($result['enrollment_id']);
                
                echo json_encode([
                    'success' => true, 
                    'message' => 'Successfully enrolled! Offer letter will be sent to your email.',
                    'enrollment_id' => $result['enrollment_id']
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

function handlePut($model, $action, $user) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    switch ($action) {
        case 'update':
            // Admin only
            if ($user['role'] !== 'admin') {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Admin access required']);
                return;
            }
            
            $id = $data['id'] ?? null;
            if (!$id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'ID required']);
                return;
            }
            
            $result = $model->update($id, $data);
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Enrollment updated successfully']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to update enrollment']);
            }
            break;
            
        case 'update-progress':
            $id = $data['id'] ?? null;
            $percentage = $data['completion_percentage'] ?? null;
            
            if (!$id || $percentage === null) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'ID and percentage required']);
                return;
            }
            
            // Check permission
            $enrollment = $model->getById($id);
            if ($user['role'] !== 'admin' && $enrollment['user_id'] != $user['id']) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Access denied']);
                return;
            }
            
            $result = $model->updateProgress($id, $percentage);
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Progress updated successfully']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to update progress']);
            }
            break;
            
        case 'mark-completed':
            // Admin only
            if ($user['role'] !== 'admin') {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Admin access required']);
                return;
            }
            
            $id = $data['id'] ?? null;
            if (!$id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'ID required']);
                return;
            }
            
            $result = $model->markCompleted($id);
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Marked as completed']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to mark as completed']);
            }
            break;
            
        case 'cancel':
            $id = $data['id'] ?? null;
            if (!$id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'ID required']);
                return;
            }
            
            // Check permission
            $enrollment = $model->getById($id);
            if ($user['role'] !== 'admin' && $enrollment['user_id'] != $user['id']) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Access denied']);
                return;
            }
            
            $result = $model->cancel($id);
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Enrollment cancelled']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to cancel enrollment']);
            }
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
}

function handleDelete($model, $action, $user) {
    // Admin only
    if ($user['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Admin access required']);
        return;
    }
    
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Delete not supported. Use cancel instead.']);
}
