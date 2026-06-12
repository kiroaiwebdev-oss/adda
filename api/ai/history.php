<?php
session_name('ai_studio_session');
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../../ai-studio/config/database.php';

$aiDb  = getAIDb();
$lmsDb = getLMSDb();

if (!isset($_SESSION['studio_user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? '';

// ── STATS ─────────────────────────────────────────
if ($action === 'stats') {
    $total  = $aiDb->query("SELECT COUNT(*) FROM ai_generated_history")->fetchColumn();
    $saved  = $aiDb->query("SELECT COUNT(*) FROM ai_generated_history WHERE status='saved'")->fetchColumn();
    $courses= $aiDb->query("SELECT COUNT(*) FROM ai_generated_history WHERE generate_for='course'")->fetchColumn();
    $intern = $aiDb->query("SELECT COUNT(*) FROM ai_generated_history WHERE generate_for='internship'")->fetchColumn();
    $month  = $aiDb->query("SELECT COUNT(*) FROM ai_generated_history WHERE MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())")->fetchColumn();

    echo json_encode([
        'success'     => true,
        'total'       => $total,
        'saved'       => $saved,
        'courses'     => $courses,
        'internships' => $intern,
        'this_month'  => $month
    ]);
    exit;
}

// ── LIST ──────────────────────────────────────────
if ($action === 'list') {
    $stmt = $aiDb->prepare("
        SELECT * FROM ai_generated_history
        ORDER BY created_at DESC
        LIMIT 50
    ");
    $stmt->execute();
    $rows = $stmt->fetchAll();
    echo json_encode(['success' => true, 'data' => $rows]);
    exit;
}

// ── GET COURSE FROM LMS ───────────────────────────
if ($action === 'get-course') {
    $historyId = (int)($_GET['id'] ?? 0);
    if (!$historyId) {
        echo json_encode(['success' => false, 'message' => 'ID required']);
        exit;
    }

    $stmt = $aiDb->prepare("SELECT * FROM ai_generated_history WHERE id = ? LIMIT 1");
    $stmt->execute([$historyId]);
    $history = $stmt->fetch();

    if (!$history) {
        echo json_encode(['success' => false, 'message' => 'Record not found']);
        exit;
    }

    $lmsId = (int)($history['lms_id'] ?? 0);
    $type  = $history['generate_for'] ?? 'course';

    if (!$lmsId) {
        echo json_encode([
            'success' => false,
            'message' => 'Ye course LMS mein save nahi hua tha. Status: ' . $history['status']
        ]);
        exit;
    }

    $courseData = [];

    try {
        if ($type === 'course') {
            // Chapters
            $chapters = $lmsDb->prepare("
                SELECT * FROM chapters WHERE course_id = ? ORDER BY sort_order ASC
            ");
            $chapters->execute([$lmsId]);
            $chapList = $chapters->fetchAll();

            $dayNum = 0;
            foreach ($chapList as $chap) {
                $lessons = $lmsDb->prepare("
                    SELECT * FROM lessons WHERE chapter_id = ? ORDER BY sort_order ASC
                ");
                $lessons->execute([$chap['id']]);
                $lessonList = $lessons->fetchAll();

                foreach ($lessonList as $lesson) {
                    $dayNum++;

                    // Content
                    $contentStmt = $lmsDb->prepare("
                        SELECT content FROM lesson_content
                        WHERE lesson_id = ? ORDER BY sort_order ASC LIMIT 1
                    ");
                    $contentStmt->execute([$lesson['id']]);
                    $contentRow = $contentStmt->fetch();

                    // Quiz
                    $quizStmt = $lmsDb->prepare("
                        SELECT * FROM lesson_quiz WHERE lesson_id = ? ORDER BY sort_order ASC
                    ");
                    $quizStmt->execute([$lesson['id']]);
                    $quizList = $quizStmt->fetchAll();

                    $quiz = array_map(function($q) {
                        return [
                            'question'    => $q['question']    ?? '',
                            'options'     => json_decode($q['options'] ?? '[]', true),
                            'correct'     => (int)($q['correct_option'] ?? 0),
                            'explanation' => $q['explanation'] ?? ''
                        ];
                    }, $quizList);

                    $rawTitle = $lesson['title'] ?? "Day {$dayNum}";
                    $title    = preg_replace('/^Day\s*\d+\s*:\s*/i', '', $rawTitle);

                    $courseData[] = [
                        'day'         => $dayNum,
                        'title'       => $title,
                        'topics'      => [],
                        'has_quiz'    => count($quiz) > 0,
                        'content'     => $contentRow['content'] ?? '',
                        'image_query' => $history['topic'] ?? '',
                        'quiz'        => $quiz
                    ];
                }
            }

        } else {
            // Internship
            $modules = $lmsDb->prepare("
                SELECT * FROM internship_modules
                WHERE internship_id = ? ORDER BY sort_order ASC
            ");
            $modules->execute([$lmsId]);
            $modList = $modules->fetchAll();

            $dayNum = 0;
            foreach ($modList as $mod) {
                $lessons = $lmsDb->prepare("
                    SELECT * FROM internship_lessons
                    WHERE module_id = ? ORDER BY sort_order ASC
                ");
                $lessons->execute([$mod['id']]);
                $lessonList = $lessons->fetchAll();

                foreach ($lessonList as $lesson) {
                    $dayNum++;

                    $contentStmt = $lmsDb->prepare("
                        SELECT content FROM internship_content
                        WHERE lesson_id = ? LIMIT 1
                    ");
                    $contentStmt->execute([$lesson['id']]);
                    $contentRow = $contentStmt->fetch();

                    $rawTitle = $lesson['title'] ?? "Day {$dayNum}";
                    $title    = preg_replace('/^Day\s*\d+\s*:\s*/i', '', $rawTitle);

                    $courseData[] = [
                        'day'         => $dayNum,
                        'title'       => $title,
                        'topics'      => [],
                        'has_quiz'    => false,
                        'content'     => $contentRow['content'] ?? '',
                        'image_query' => $history['topic'] ?? '',
                        'quiz'        => []
                    ];
                }
            }
        }
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'LMS DB error: ' . $e->getMessage()
        ]);
        exit;
    }

    if (empty($courseData)) {
        echo json_encode([
            'success' => false,
            'message' => 'Course content LMS mein nahi mila. lms_id: ' . $lmsId
        ]);
        exit;
    }

    echo json_encode([
        'success'    => true,
        'course'     => $courseData,
        'total_days' => count($courseData),
        'meta'       => [
            'topic'        => $history['topic'],
            'days'         => $history['duration_days'],
            'level'        => $history['level']       ?? 'Beginner',
            'language'     => $history['language']    ?? 'English',
            'type'         => $history['generate_for'],
            'history_id'   => $history['id'],
            'lms_id'       => $lmsId,
            'include_quiz' => false,
            'batch_size'   => 7,
            'provider'     => $history['ai_provider'] ?? 'gemini'
        ]
    ]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);