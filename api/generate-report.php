<?php
session_start();
header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(E_ALL);

$configPath = __DIR__ . '/../app/config/database.php';

if (!file_exists($configPath)) {
    die(json_encode(['success' => false, 'message' => 'Config not found']));
}

try {
    require_once $configPath;
    $dbConfig = require $configPath;
    $dsn = sprintf("mysql:host=%s;port=%s;dbname=%s;charset=%s",
        $dbConfig['host'], $dbConfig['port'], $dbConfig['database'], $dbConfig['charset']
    );
    $db = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options']);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die(json_encode(['success' => false, 'message' => 'DB error']));
}

spl_autoload_register(function ($class) {
    $paths = [
        __DIR__ . '/../app/core/' . $class . '.php',
        __DIR__ . '/../app/models/' . $class . '.php',
        __DIR__ . '/../app/controllers/' . $class . '.php',
    ];
    foreach ($paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            return;
        }
    }
});

try {
    $auth = new Auth($db);
    if (!$auth->check()) {
        die(json_encode(['success' => false, 'message' => 'Please login']));
    }
    $userId = $auth->id();
} catch (Exception $e) {
    die(json_encode(['success' => false, 'message' => 'Auth error']));
}

try {
    $db->beginTransaction();
    
    // Total courses
    $stmt = $db->prepare("SELECT COUNT(*) FROM enrollments WHERE user_id = ?");
    $stmt->execute([$userId]);
    $totalCourses = intval($stmt->fetchColumn());
    
    // Completed courses
    $stmt = $db->prepare("SELECT COUNT(*) FROM enrollments WHERE user_id = ? AND completed_at IS NOT NULL");
    $stmt->execute([$userId]);
    $completedCourses = intval($stmt->fetchColumn());
    
    // Total topics
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT t.id) 
        FROM topics t
        JOIN chapters ch ON t.chapter_id = ch.id
        JOIN courses c ON ch.course_id = c.id
        JOIN enrollments e ON c.id = e.course_id
        WHERE e.user_id = ?
    ");
    $stmt->execute([$userId]);
    $totalTopics = intval($stmt->fetchColumn());
    
    // Completed topics
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT topic_id)
        FROM topic_progress
        WHERE user_id = ? AND completed_at IS NOT NULL
    ");
    $stmt->execute([$userId]);
    $completedTopics = intval($stmt->fetchColumn());
    
    // Certificates
    $stmt = $db->prepare("SELECT COUNT(*) FROM certificates WHERE user_id = ?");
    $stmt->execute([$userId]);
    $certificatesEarned = intval($stmt->fetchColumn());
    
    // Overall progress
    $overallProgress = 0;
    if ($totalTopics > 0) {
        $overallProgress = ($completedTopics / $totalTopics) * 100;
    }
    
    // Course details
    $stmt = $db->prepare("
        SELECT 
            c.id as course_id,
            c.title as course_title,
            e.enrolled_at,
            e.completed_at,
            e.progress_percent
        FROM enrollments e
        JOIN courses c ON e.course_id = c.id
        WHERE e.user_id = ?
        ORDER BY e.enrolled_at DESC
    ");
    $stmt->execute([$userId]);
    $courseDetails = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $reportData = [
        'courses' => $courseDetails,
        'summary' => [
            'total_courses' => $totalCourses,
            'completed_courses' => $completedCourses,
            'total_topics' => $totalTopics,
            'completed_topics' => $completedTopics,
            'average_quiz_score' => 0,
            'certificates_earned' => $certificatesEarned,
            'overall_progress' => round($overallProgress, 2)
        ]
    ];
    
    // Insert report
    $stmt = $db->prepare("
        INSERT INTO internship_reports (
            user_id, report_type, report_data, total_courses, completed_courses,
            total_topics, completed_topics, average_quiz_score, certificates_earned,
            overall_progress, generated_at
        ) VALUES (?, 'progress', ?, ?, ?, ?, ?, 0, ?, ?, NOW())
    ");
    
    $stmt->execute([
        $userId,
        json_encode($reportData),
        $totalCourses,
        $completedCourses,
        $totalTopics,
        $completedTopics,
        $certificatesEarned,
        $overallProgress
    ]);
    
    $reportId = $db->lastInsertId();
    
    // Insert course details
    if (!empty($courseDetails)) {
        foreach ($courseDetails as $course) {
            $stmt = $db->prepare("
                INSERT INTO report_course_details (
                    report_id, course_id, course_title, enrolled_at, completed_at,
                    progress_percent, total_topics, completed_topics, quiz_attempts,
                    average_score, certificate_code, time_spent_minutes
                ) VALUES (?, ?, ?, ?, ?, ?, 0, 0, 0, 0, NULL, 0)
            ");
            
            $stmt->execute([
                $reportId,
                $course['course_id'],
                $course['course_title'],
                $course['enrolled_at'],
                $course['completed_at'],
                $course['progress_percent'] ?? 0
            ]);
        }
    }
    
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Report generated successfully!',
        'report_id' => $reportId,
        'data' => $reportData['summary']
    ]);
    
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
