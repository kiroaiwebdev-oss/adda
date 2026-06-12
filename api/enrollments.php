<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ✅ Get first topic URL for FREE course auto-redirect
function getFirstTopicUrl($db, $courseId) {
    try {
        error_log("🔍 Finding first topic for FREE course: $courseId");
        
        $chapterStmt = $db->prepare("
            SELECT id 
            FROM chapters 
            WHERE course_id = ? 
            ORDER BY chapter_order ASC, sort_order ASC, id ASC 
            LIMIT 1
        ");
        $chapterStmt->execute([$courseId]);
        $chapterId = $chapterStmt->fetchColumn();
        
        if (!$chapterId) {
            error_log("⚠️ No chapters found");
            return '/app/views/learner/my-courses.php';
        }
        
        $topicStmt = $db->prepare("
            SELECT id 
            FROM topics 
            WHERE chapter_id = ? 
            ORDER BY topic_order ASC, sort_order ASC, id ASC 
            LIMIT 1
        ");
        $topicStmt->execute([$chapterId]);
        $topicId = $topicStmt->fetchColumn();
        
        if (!$topicId) {
            error_log("⚠️ No topics found");
            return '/app/views/learner/my-courses.php';
        }
        
        $redirectUrl = "/app/views/learner/course-player.php?id={$courseId}&topic={$topicId}";
        error_log("🚀 FREE course redirect: $redirectUrl");
        
        return $redirectUrl;
        
    } catch (Exception $e) {
        error_log("❌ Error: " . $e->getMessage());
        return '/app/views/learner/my-courses.php';
    }
}

try {
    // Database connection
    $configPaths = [
        __DIR__ . '/../app/config/database.php',
        dirname(dirname(__FILE__)) . '/app/config/database.php',
        $_SERVER['DOCUMENT_ROOT'] . '/app/config/database.php',
    ];
    
    $dbConfig = null;
    foreach ($configPaths as $path) {
        if (file_exists($path)) {
            $dbConfig = require $path;
            break;
        }
    }
    
    if (!$dbConfig || !isset($dbConfig['host'])) {
        throw new Exception('Database config not found or invalid');
    }
    
    $dsn = sprintf("mysql:host=%s;port=%s;dbname=%s;charset=%s",
        $dbConfig['host'], 
        $dbConfig['port'], 
        $dbConfig['database'], 
        $dbConfig['charset']
    );
    
    $db = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options']);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => 'Database connection failed'
    ]);
    exit;
} catch (Exception $e) {
    error_log("Config error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => $e->getMessage()
    ]);
    exit;
}

// Autoload classes
spl_autoload_register(function ($class) {
    $basePaths = [
        __DIR__ . '/../app/',
        dirname(dirname(__FILE__)) . '/app/',
        $_SERVER['DOCUMENT_ROOT'] . '/app/',
    ];
    
    $subPaths = ['core/', 'models/', 'controllers/'];
    
    foreach ($basePaths as $basePath) {
        foreach ($subPaths as $subPath) {
            $file = $basePath . $subPath . $class . '.php';
            if (file_exists($file)) {
                require_once $file;
                return;
            }
        }
    }
});

// Auth check
try {
    if (!class_exists('Auth')) {
        throw new Exception('Auth class not found');
    }
    
    $auth = new Auth($db);
    
    if (!$auth->check()) {
        http_response_code(401);
        echo json_encode([
            'success' => false, 
            'error' => 'Please login to enroll',
            'redirect' => '/public/login.php'
        ]);
        exit;
    }
    
    $userId = $auth->user()['id'];
    $action = $_GET['action'] ?? 'enroll';
    
    // Get course ID from multiple sources
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    $courseId = $data['course_id'] ?? $_POST['course_id'] ?? $_GET['course_id'] ?? $data['courseid'] ?? $_POST['courseid'] ?? null;
    
    switch ($action) {
        case 'enroll':
            if (!$courseId) {
                echo json_encode(['success' => false, 'error' => 'Course ID required']);
                exit;
            }
            
            // Check if already enrolled
            $checkStmt = $db->prepare("SELECT id FROM enrollments WHERE user_id = ? AND course_id = ?");
            $checkStmt->execute([$userId, $courseId]);
            
            if ($checkStmt->rowCount() > 0) {
                echo json_encode([
                    'success' => false, 
                    'error' => 'Already enrolled',
                    'redirect' => getFirstTopicUrl($db, $courseId) // ✅ CHANGED
                ]);
                exit;
            }
            
            // Get course details
            $courseStmt = $db->prepare("SELECT * FROM courses WHERE id = ? AND status = 'published'");
            $courseStmt->execute([$courseId]);
            $course = $courseStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$course) {
                echo json_encode(['success' => false, 'error' => 'Course not found']);
                exit;
            }
            
            // Get actual price (with admin discount)
            $actualPrice = floatval($course['price'] ?? 0);
            
            // ✅ FREE COURSE ENROLLMENT
            if ($actualPrice == 0) {
                // Detect available columns
                $columnsStmt = $db->query("SHOW COLUMNS FROM enrollments");
                $columns = $columnsStmt->fetchAll(PDO::FETCH_COLUMN);
                
                $insertColumns = ['user_id', 'course_id', 'enrolled_at'];
                $insertValues = [$userId, $courseId, date('Y-m-d H:i:s')];
                $insertPlaceholders = ['?', '?', '?'];
                
                if (in_array('status', $columns)) {
                    $insertColumns[] = 'status';
                    $insertValues[] = 'active';
                    $insertPlaceholders[] = '?';
                }
                
                if (in_array('progress', $columns)) {
                    $insertColumns[] = 'progress';
                    $insertValues[] = 0;
                    $insertPlaceholders[] = '?';
                }
                
                if (in_array('payment_status', $columns)) {
                    $insertColumns[] = 'payment_status';
                    $insertValues[] = 'paid';
                    $insertPlaceholders[] = '?';
                }
                
                if (in_array('payment_id', $columns)) {
                    $insertColumns[] = 'payment_id';
                    $insertValues[] = null;
                    $insertPlaceholders[] = '?';
                }
                
                if (in_array('payment_method', $columns)) {
                    $insertColumns[] = 'payment_method';
                    $insertValues[] = null;
                    $insertPlaceholders[] = '?';
                }
                
                // ✅ STORE amount_paid = 0 for FREE
                if (in_array('amount_paid', $columns)) {
                    $insertColumns[] = 'amount_paid';
                    $insertValues[] = 0;
                    $insertPlaceholders[] = '?';
                }
                
                // Coupon support for free courses
                $couponId = $data['coupon_id'] ?? $data['couponid'] ?? $_POST['coupon_id'] ?? null;
                if ($couponId && in_array('coupon_id', $columns)) {
                    $insertColumns[] = 'coupon_id';
                    $insertValues[] = $couponId;
                    $insertPlaceholders[] = '?';
                }
                
                $sql = sprintf(
                    "INSERT INTO enrollments (%s) VALUES (%s)",
                    implode(', ', $insertColumns),
                    implode(', ', $insertPlaceholders)
                );
                
                $enrollStmt = $db->prepare($sql);
                
                if ($enrollStmt->execute($insertValues)) {
                    // Update course count
                    $db->prepare("UPDATE courses SET enrolled_count = COALESCE(enrolled_count, 0) + 1 WHERE id = ?")->execute([$courseId]);
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Enrolled successfully!',
                        'redirect' => getFirstTopicUrl($db, $courseId) // ✅ CHANGED
                    ]);
                } else {
                    throw new Exception('Enrollment failed');
                }
            } else {
                // ✅ PAID COURSE - Redirect to payment
                echo json_encode([
                    'success' => true,
                    'message' => 'Redirecting to payment...',
                    'requires_payment' => true
                ]);
            }
            break;
            
        case 'check':
            if (!$courseId) {
                echo json_encode(['success' => false, 'error' => 'Course ID required']);
                exit;
            }
            
            $checkStmt = $db->prepare("SELECT * FROM enrollments WHERE user_id = ? AND course_id = ?");
            $checkStmt->execute([$userId, $courseId]);
            $enrollment = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'enrolled' => $enrollment ? true : false,
                'enrollment' => $enrollment
            ]);
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
            break;
    }
    
} catch (Exception $e) {
    error_log("Enrollment error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => $e->getMessage()
    ]);
}
?>
