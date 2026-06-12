<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

session_start();
require_once __DIR__ . '/../app/config/database.php';

$dbConfig = require __DIR__ . '/../app/config/database.php';
$dsn = sprintf("mysql:host=%s;port=%s;dbname=%s;charset=%s",
    $dbConfig['host'], $dbConfig['port'], $dbConfig['database'], $dbConfig['charset']
);

try {
    $db = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options']);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

spl_autoload_register(function ($class) {
    $paths = [
        __DIR__ . '/../app/core/' . $class . '.php',
        __DIR__ . '/../app/models/' . $class . '.php',
    ];
    foreach ($paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            return;
        }
    }
});

$auth = new Auth($db);

if (!$auth->check()) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$userId = $auth->id();
$action = $_GET['action'] ?? '';
// ✅ SUBMIT QUIZ ATTEMPT
if ($action === 'submit-attempt' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $quizId = $input['quiz_id'] ?? 0;
        $answers = $input['answers'] ?? [];
        
        error_log("=== QUIZ SUBMIT DEBUG ===");
        error_log("Quiz ID: $quizId");
        error_log("User ID: $userId");
        error_log("Received answers: " . json_encode($answers));
        
        if (!$quizId || empty($answers)) {
            echo json_encode(['success' => false, 'error' => 'Invalid quiz submission']);
            exit;
        }
        
        // Get quiz details
        $stmt = $db->prepare("SELECT * FROM quizzes WHERE id = ?");
        $stmt->execute([$quizId]);
        $quiz = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$quiz) {
            echo json_encode(['success' => false, 'error' => 'Quiz not found']);
            exit;
        }
        
        // Get all questions with correct answers
        $stmt = $db->prepare("SELECT * FROM quiz_questions WHERE quiz_id = ?");
        $stmt->execute([$quizId]);
        $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($questions)) {
            echo json_encode(['success' => false, 'error' => 'No questions found']);
            exit;
        }
        
        error_log("Total questions: " . count($questions));
        
        // Calculate score
        $totalQuestions = count($questions);
        $totalPoints = 0;
        $earnedPoints = 0;
        $correctCount = 0;
        
        foreach ($questions as $question) {
            $totalPoints += $question['points'];
            
            $questionId = $question['id'];
            $userAnswer = isset($answers[$questionId]) ? trim($answers[$questionId]) : '';
            $correctAnswer = trim($question['correct_answer']);
            
            // Normalize: remove extra spaces, convert to lowercase
            $userAnswerNormalized = preg_replace('/\s+/', ' ', strtolower($userAnswer));
            $correctAnswerNormalized = preg_replace('/\s+/', ' ', strtolower($correctAnswer));
            
            error_log("--- Question ID: $questionId ---");
            error_log("User Answer: '$userAnswer'");
            error_log("User Normalized: '$userAnswerNormalized'");
            error_log("Correct Answer: '$correctAnswer'");
            error_log("Correct Normalized: '$correctAnswerNormalized'");
            
            if ($userAnswerNormalized === $correctAnswerNormalized) {
                $earnedPoints += $question['points'];
                $correctCount++;
                error_log("✅ CORRECT!");
            } else {
                error_log("❌ WRONG!");
            }
        }
        
        error_log("Correct count: $correctCount / $totalQuestions");
        error_log("Points: $earnedPoints / $totalPoints");
        
        $scorePercentage = $totalPoints > 0 ? ($earnedPoints / $totalPoints) * 100 : 0;
        $passScore = $quiz['pass_score'] ?? 70;
        $isPassed = $scorePercentage >= $passScore;
        
        error_log("Score: $scorePercentage% (Pass score: $passScore%)");
        error_log("Status: " . ($isPassed ? 'PASSED' : 'FAILED'));
        
        // Insert quiz attempt
        $stmt = $db->prepare("
            INSERT INTO quiz_attempts (user_id, quiz_id, score, answers, status, completed_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        $answersJson = json_encode($answers);
        $status = $isPassed ? 'passed' : 'failed';
        
        $stmt->execute([
            $userId,
            $quizId,
            $scorePercentage,
            $answersJson,
            $status
        ]);
        
        echo json_encode([
            'success' => true,
            'data' => [
                'score' => $scorePercentage,
                'is_passed' => $isPassed,
                'correct_count' => $correctCount,
                'total_questions' => $totalQuestions,
                'earned_points' => $earnedPoints,
                'total_points' => $totalPoints,
                'status' => $status
            ]
        ]);
        
    } catch (Exception $e) {
        error_log("Quiz submit error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Failed to submit quiz: ' . $e->getMessage()]);
    }
    exit;
}


// ✅ GET QUIZ RESULTS
if ($action === 'get-results' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $quizId = $_GET['quiz_id'] ?? 0;
        
        if (!$quizId) {
            echo json_encode(['success' => false, 'error' => 'Quiz ID required']);
            exit;
        }
        
        $stmt = $db->prepare("
            SELECT * FROM quiz_attempts 
            WHERE user_id = ? AND quiz_id = ? 
            ORDER BY completed_at DESC
        ");
        $stmt->execute([$userId, $quizId]);
        $attempts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => $attempts
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Failed to get results']);
    }
    exit;
}

// ✅ GET QUIZ QUESTIONS (for builder)
if ($action === 'get-questions' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $quizId = $_GET['quiz_id'] ?? 0;
        
        if (!$quizId) {
            echo json_encode(['success' => false, 'error' => 'Quiz ID required']);
            exit;
        }
        
        $stmt = $db->prepare("SELECT * FROM quiz_questions WHERE quiz_id = ? ORDER BY sort_order ASC");
        $stmt->execute([$quizId]);
        $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => $questions
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Failed to get questions']);
    }
    exit;
}

echo json_encode(['success' => false, 'error' => 'Invalid action']);
