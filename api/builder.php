<?php
session_start();
require_once __DIR__ . '/../app/config/database.php';

$dbConfig = require __DIR__ . '/../app/config/database.php';
$dsn = sprintf("mysql:host=%s;port=%s;dbname=%s;charset=%s",
    $dbConfig['host'], $dbConfig['port'], $dbConfig['database'], $dbConfig['charset']
);
$db = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options']);

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
$auth->requireAdmin();

$action = $_GET['action'] ?? '';

header('Content-Type: application/json');

try {
    switch ($action) {
        // 🔥 FIXED: GET CONTENT
        case 'get_content':
            $topicId = isset($_GET['topic_id']) ? (int)$_GET['topic_id'] : 0;
            
            if (!$topicId) {
                echo json_encode(['success' => false, 'error' => 'Topic ID required']);
                exit;
            }
            
            // Get content block for this topic
            $stmt = $db->prepare("SELECT * FROM content_blocks WHERE topic_id = ? AND type = 'text' ORDER BY sort_order ASC LIMIT 1");
            $stmt->execute([$topicId]);
            $contentBlock = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($contentBlock) {
                echo json_encode([
                    'success' => true,
                    'data' => [
                        'content' => $contentBlock['content'] ?? '',
                        'block_id' => $contentBlock['id']
                    ]
                ]);
            } else {
                // No content yet - return empty
                echo json_encode([
                    'success' => true,
                    'data' => [
                        'content' => '',
                        'block_id' => null
                    ]
                ]);
            }
            break;
            
        // 🔥 FIXED: SAVE CONTENT
        case 'save_content':
            $topicId = isset($_POST['topic_id']) ? (int)$_POST['topic_id'] : 0;
            $content = $_POST['content'] ?? '';
            
            if (!$topicId) {
                echo json_encode(['success' => false, 'error' => 'Topic ID required']);
                exit;
            }
            
            // Check if content block already exists
            $stmt = $db->prepare("SELECT id FROM content_blocks WHERE topic_id = ? AND type = 'text' LIMIT 1");
            $stmt->execute([$topicId]);
            $existingBlock = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existingBlock) {
                // Update existing content
                $stmt = $db->prepare("UPDATE content_blocks SET content = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$content, $existingBlock['id']]);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Content updated successfully'
                ]);
            } else {
                // Create new content block
                $stmt = $db->prepare("INSERT INTO content_blocks (topic_id, type, content, sort_order, created_at) VALUES (?, 'text', ?, 0, NOW())");
                $stmt->execute([$topicId, $content]);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Content created successfully'
                ]);
            }
            break;
            
        // ADD CHAPTER
        case 'add_chapter':
            $courseId = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
            $title = trim($_POST['title'] ?? '');
            
            if (!$courseId || !$title) {
                echo json_encode(['success' => false, 'error' => 'Course ID and title required']);
                exit;
            }
            
            // Get next sort order
            $stmt = $db->prepare("SELECT COALESCE(MAX(sort_order), 0) + 1 as next_order FROM chapters WHERE course_id = ?");
            $stmt->execute([$courseId]);
            $nextOrder = $stmt->fetchColumn();
            
            $stmt = $db->prepare("INSERT INTO chapters (course_id, title, sort_order, created_at) VALUES (?, ?, ?, NOW())");
            $stmt->execute([$courseId, $title, $nextOrder]);
            
            echo json_encode(['success' => true, 'message' => 'Chapter created successfully']);
            break;
            
        // EDIT CHAPTER
        case 'edit_chapter':
            $chapterId = isset($_POST['chapter_id']) ? (int)$_POST['chapter_id'] : 0;
            $title = trim($_POST['title'] ?? '');
            
            if (!$chapterId || !$title) {
                echo json_encode(['success' => false, 'error' => 'Chapter ID and title required']);
                exit;
            }
            
            $stmt = $db->prepare("UPDATE chapters SET title = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$title, $chapterId]);
            
            echo json_encode(['success' => true, 'message' => 'Chapter updated successfully']);
            break;
            
        // DELETE CHAPTER
        case 'delete_chapter':
            $chapterId = isset($_GET['chapter_id']) ? (int)$_GET['chapter_id'] : 0;
            
            if (!$chapterId) {
                echo json_encode(['success' => false, 'error' => 'Chapter ID required']);
                exit;
            }
            
            // Get all topics in this chapter
            $stmt = $db->prepare("SELECT id FROM topics WHERE chapter_id = ?");
            $stmt->execute([$chapterId]);
            $topics = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Delete content blocks for each topic
            if (!empty($topics)) {
                $placeholders = str_repeat('?,', count($topics) - 1) . '?';
                $stmt = $db->prepare("DELETE FROM content_blocks WHERE topic_id IN ($placeholders)");
                $stmt->execute($topics);
            }
            
            // Delete topics
            $stmt = $db->prepare("DELETE FROM topics WHERE chapter_id = ?");
            $stmt->execute([$chapterId]);
            
            // Delete chapter
            $stmt = $db->prepare("DELETE FROM chapters WHERE id = ?");
            $stmt->execute([$chapterId]);
            
            echo json_encode(['success' => true, 'message' => 'Chapter deleted successfully']);
            break;
            
        // ADD TOPIC
        case 'add_topic':
            $chapterId = isset($_POST['chapter_id']) ? (int)$_POST['chapter_id'] : 0;
            $title = trim($_POST['title'] ?? '');
            $isMandatory = isset($_POST['is_mandatory']) ? 1 : 0;
            
            if (!$chapterId || !$title) {
                echo json_encode(['success' => false, 'error' => 'Chapter ID and title required']);
                exit;
            }
            
            // Get next sort order
            $stmt = $db->prepare("SELECT COALESCE(MAX(sort_order), 0) + 1 as next_order FROM topics WHERE chapter_id = ?");
            $stmt->execute([$chapterId]);
            $nextOrder = $stmt->fetchColumn();
            
            $stmt = $db->prepare("INSERT INTO topics (chapter_id, title, is_mandatory, sort_order, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->execute([$chapterId, $title, $isMandatory, $nextOrder]);
            
            echo json_encode(['success' => true, 'message' => 'Topic created successfully']);
            break;
            
        // EDIT TOPIC
        case 'edit_topic':
            $topicId = isset($_POST['topic_id']) ? (int)$_POST['topic_id'] : 0;
            $title = trim($_POST['title'] ?? '');
            $isMandatory = isset($_POST['is_mandatory']) ? 1 : 0;
            
            if (!$topicId || !$title) {
                echo json_encode(['success' => false, 'error' => 'Topic ID and title required']);
                exit;
            }
            
            $stmt = $db->prepare("UPDATE topics SET title = ?, is_mandatory = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$title, $isMandatory, $topicId]);
            
            echo json_encode(['success' => true, 'message' => 'Topic updated successfully']);
            break;
            
        // DELETE TOPIC
        case 'delete_topic':
            $topicId = isset($_GET['topic_id']) ? (int)$_GET['topic_id'] : 0;
            
            if (!$topicId) {
                echo json_encode(['success' => false, 'error' => 'Topic ID required']);
                exit;
            }
            
            // Delete content blocks
            $stmt = $db->prepare("DELETE FROM content_blocks WHERE topic_id = ?");
            $stmt->execute([$topicId]);
            
            // Delete topic
            $stmt = $db->prepare("DELETE FROM topics WHERE id = ?");
            $stmt->execute([$topicId]);
            
            echo json_encode(['success' => true, 'message' => 'Topic deleted successfully']);
            break;
            
        // GET QUIZ
        case 'get_quiz':
            $topicId = isset($_GET['topic_id']) ? (int)$_GET['topic_id'] : 0;
            
            if (!$topicId) {
                echo json_encode(['success' => false, 'error' => 'Topic ID required']);
                exit;
            }
            
            // Get quiz for this topic
            $stmt = $db->prepare("
                SELECT q.* 
                FROM quizzes q
                INNER JOIN content_blocks cb ON q.content_block_id = cb.id
                WHERE cb.topic_id = ? AND cb.type = 'quiz'
                LIMIT 1
            ");
            $stmt->execute([$topicId]);
            $quiz = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($quiz) {
                // Get questions
                $stmt = $db->prepare("SELECT * FROM quiz_questions WHERE quiz_id = ? ORDER BY sort_order ASC");
                $stmt->execute([$quiz['id']]);
                $quiz['questions'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode(['success' => true, 'data' => $quiz]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Quiz not found']);
            }
            break;
            
        // SAVE QUIZ
        case 'save_quiz':
            $topicId = isset($_POST['topic_id']) ? (int)$_POST['topic_id'] : 0;
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $passingScore = (int)($_POST['passing_score'] ?? 70);
            $timeLimit = (int)($_POST['time_limit'] ?? 30);
            $questions = $_POST['questions'] ?? [];
            
            if (!$topicId || !$title || empty($questions)) {
                echo json_encode(['success' => false, 'error' => 'Topic ID, title and questions required']);
                exit;
            }
            
            $db->beginTransaction();
            
            try {
                // Check if quiz already exists
                $stmt = $db->prepare("
                    SELECT cb.id as content_block_id, q.id as quiz_id
                    FROM content_blocks cb
                    LEFT JOIN quizzes q ON q.content_block_id = cb.id
                    WHERE cb.topic_id = ? AND cb.type = 'quiz'
                    LIMIT 1
                ");
                $stmt->execute([$topicId]);
                $existing = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($existing && $existing['quiz_id']) {
                    // Update existing quiz
                    $stmt = $db->prepare("UPDATE quizzes SET title = ?, description = ?, passing_score = ?, time_limit = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$title, $description, $passingScore, $timeLimit, $existing['quiz_id']]);
                    $quizId = $existing['quiz_id'];
                    
                    // Delete old questions
                    $stmt = $db->prepare("DELETE FROM quiz_questions WHERE quiz_id = ?");
                    $stmt->execute([$quizId]);
                } else {
                    // Create new content block
                    $stmt = $db->prepare("INSERT INTO content_blocks (topic_id, type, sort_order, created_at) VALUES (?, 'quiz', 1, NOW())");
                    $stmt->execute([$topicId]);
                    $contentBlockId = $db->lastInsertId();
                    
                    // Create new quiz
                    $stmt = $db->prepare("INSERT INTO quizzes (content_block_id, title, description, passing_score, time_limit, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                    $stmt->execute([$contentBlockId, $title, $description, $passingScore, $timeLimit]);
                    $quizId = $db->lastInsertId();
                }
                
                // Insert questions
                $sortOrder = 0;
                foreach ($questions as $questionData) {
                    $stmt = $db->prepare("
                        INSERT INTO quiz_questions 
                        (quiz_id, question_text, option1, option2, option3, option4, correct_option, explanation, sort_order, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([
                        $quizId,
                        $questionData['text'],
                        $questionData['option1'],
                        $questionData['option2'],
                        $questionData['option3'],
                        $questionData['option4'],
                        $questionData['correct'],
                        $questionData['explanation'] ?? '',
                        $sortOrder++
                    ]);
                }
                
                $db->commit();
                echo json_encode(['success' => true, 'message' => 'Quiz saved successfully']);
                
            } catch (Exception $e) {
                $db->rollBack();
                echo json_encode(['success' => false, 'error' => 'Failed to save quiz: ' . $e->getMessage()]);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
