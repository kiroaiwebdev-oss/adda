<?php
/**
 * Internship Quiz API
 * Handles CRUD operations for quiz questions
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
            
            $stmt = $db->prepare("SELECT * FROM internship_quiz_questions WHERE lesson_id = ? ORDER BY sort_order ASC, id ASC");
            $stmt->execute([$lessonId]);
            $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'data' => $questions]);
            break;
            
        case 'get_single':
            $id = $_GET['id'] ?? null;
            if (!$id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Question ID required']);
                return;
            }
            
            $stmt = $db->prepare("SELECT * FROM internship_quiz_questions WHERE id = ?");
            $stmt->execute([$id]);
            $question = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$question) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Question not found']);
                return;
            }
            
            echo json_encode(['success' => true, 'data' => $question]);
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
            if (empty($data['lesson_id']) || empty($data['question']) || empty($data['type'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Lesson ID, question and type are required']);
                return;
            }
            
            // Get current max sort_order
            $stmt = $db->prepare("SELECT MAX(sort_order) as max_order FROM internship_quiz_questions WHERE lesson_id = ?");
            $stmt->execute([$data['lesson_id']]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $sortOrder = ($result['max_order'] ?? 0) + 1;
            
            // Prepare options and answers based on type
            $options = null;
            $correctOption = null;
            $correctAnswer = null;
            
            if ($data['type'] === 'multiple_choice' || $data['type'] === 'true_false') {
                $options = $data['options'] ?? null;
                $correctOption = $data['correct_option'] ?? null;
            } else if ($data['type'] === 'short_answer') {
                $correctAnswer = $data['correct_answer'] ?? null;
            }
            
            $sql = "INSERT INTO internship_quiz_questions (lesson_id, question, type, options, correct_option, correct_answer, explanation, points, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $db->prepare($sql);
            $success = $stmt->execute([
                $data['lesson_id'],
                $data['question'],
                $data['type'],
                $options,
                $correctOption,
                $correctAnswer,
                $data['explanation'] ?? null,
                $data['points'] ?? 10,
                $sortOrder
            ]);
            
            if ($success) {
                $id = $db->lastInsertId();
                echo json_encode(['success' => true, 'message' => 'Question created successfully', 'id' => $id]);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to create question']);
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
                echo json_encode(['success' => false, 'message' => 'Question ID required']);
                return;
            }
            
            $sql = "UPDATE internship_quiz_questions SET question = ?, points = ?, explanation = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
            $stmt = $db->prepare($sql);
            $success = $stmt->execute([
                $data['question'],
                $data['points'] ?? 10,
                $data['explanation'] ?? null,
                $id
            ]);
            
            if ($success) {
                echo json_encode(['success' => true, 'message' => 'Question updated successfully']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to update question']);
            }
            break;
            
        case 'reorder':
            // Update sort orders
            if (empty($data['questions'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Questions array required']);
                return;
            }
            
            $db->beginTransaction();
            try {
                $stmt = $db->prepare("UPDATE internship_quiz_questions SET sort_order = ? WHERE id = ?");
                foreach ($data['questions'] as $index => $questionId) {
                    $stmt->execute([$index, $questionId]);
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
                echo json_encode(['success' => false, 'message' => 'Question ID required']);
                return;
            }
            
            $stmt = $db->prepare("DELETE FROM internship_quiz_questions WHERE id = ?");
            $success = $stmt->execute([$id]);
            
            if ($success) {
                echo json_encode(['success' => true, 'message' => 'Question deleted successfully']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to delete question']);
            }
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
}
