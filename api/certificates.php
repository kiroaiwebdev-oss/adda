<?php
header('Content-Type: application/json');
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
        __DIR__ . '/../app/controllers/' . $class . '.php',
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
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$userId = $auth->id();
$action = $_GET['action'] ?? '';

// ✅ GENERATE CERTIFICATE
if ($action === 'generate' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        $courseId = (int)($input['course_id'] ?? 0);
        
        if (!$courseId) {
            throw new Exception('Course ID required');
        }
        
        // Check mandatory topics completion
        $progressStmt = $db->prepare("
            SELECT 
                c.id,
                c.title,
                COUNT(DISTINCT t.id) as total_topics,
                COUNT(DISTINCT CASE WHEN t.is_mandatory = 1 THEN t.id END) as mandatory_topics,
                COUNT(DISTINCT tp.topic_id) as completed_topics,
                COUNT(DISTINCT CASE WHEN t.is_mandatory = 1 THEN tp.topic_id END) as completed_mandatory
            FROM courses c
            INNER JOIN chapters ch ON c.id = ch.course_id
            INNER JOIN topics t ON ch.id = t.chapter_id
            LEFT JOIN topic_progress tp ON t.id = tp.topic_id AND tp.user_id = ?
            WHERE c.id = ?
            GROUP BY c.id
        ");
        $progressStmt->execute([$userId, $courseId]);
        $course = $progressStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$course) {
            throw new Exception('Course not found');
        }
        
        $mandatoryTopics = (int)($course['mandatory_topics'] ?? 0);
        $completedMandatory = (int)($course['completed_mandatory'] ?? 0);
        
        // Check if all mandatory topics are completed
        $isComplete = $mandatoryTopics > 0 && $completedMandatory >= $mandatoryTopics;
        
        if (!$isComplete) {
            throw new Exception("Complete all mandatory topics first. ($completedMandatory/$mandatoryTopics completed)");
        }
        
        // Check if certificate already exists
        $checkStmt = $db->prepare("
            SELECT * FROM certificates 
            WHERE user_id = ? AND course_id = ? AND status = 'active'
        ");
        $checkStmt->execute([$userId, $courseId]);
        $existingCert = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existingCert) {
            echo json_encode([
                'success' => true,
                'message' => 'Certificate already exists',
                'certificate_id' => $existingCert['id'],
                'certificate_code' => $existingCert['certificate_number'] ?? $existingCert['certificate_code']
            ]);
            exit;
        }
        
        // Generate certificate
        $certificateCode = 'CERT-' . strtoupper(uniqid());
        $certificateNumber = $certificateCode . '-' . date('Y');
        $verificationCode = hash('sha256', $userId . $courseId . time());
        
        // Insert certificate
        $insertStmt = $db->prepare("
            INSERT INTO certificates 
            (user_id, course_id, certificate_code, certificate_number, verification_code, issued_date, completed_date, status)
            VALUES (?, ?, ?, ?, ?, NOW(), NOW(), 'active')
        ");
        $insertStmt->execute([$userId, $courseId, $certificateCode, $certificateNumber, $verificationCode]);
        $certificateId = $db->lastInsertId();
        
        // Update enrollment completion date if exists
        $updateStmt = $db->prepare("
            UPDATE enrollments 
            SET completed_at = NOW() 
            WHERE user_id = ? AND course_id = ? AND completed_at IS NULL
        ");
        $updateStmt->execute([$userId, $courseId]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Certificate generated successfully!',
            'certificate_id' => $certificateId,
            'certificate_code' => $certificateNumber,
            'certificate_number' => $certificateNumber
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ✅ AUTO-CHECK CERTIFICATE ELIGIBILITY
if ($action === 'auto-check' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        $courseId = (int)($input['course_id'] ?? 0);
        
        if (!$courseId) {
            throw new Exception('Course ID required');
        }
        
        // Check mandatory topics progress
        $progressStmt = $db->prepare("
            SELECT 
                COUNT(DISTINCT t.id) as total_topics,
                COUNT(DISTINCT CASE WHEN t.is_mandatory = 1 THEN t.id END) as mandatory_topics,
                COUNT(DISTINCT tp.topic_id) as completed_topics,
                COUNT(DISTINCT CASE WHEN t.is_mandatory = 1 THEN tp.topic_id END) as completed_mandatory
            FROM topics t
            JOIN chapters ch ON t.chapter_id = ch.id
            LEFT JOIN topic_progress tp ON tp.topic_id = t.id AND tp.user_id = ?
            WHERE ch.course_id = ?
        ");
        $progressStmt->execute([$userId, $courseId]);
        $progress = $progressStmt->fetch(PDO::FETCH_ASSOC);
        
        $totalTopics = (int)($progress['total_topics'] ?? 0);
        $mandatoryTopics = (int)($progress['mandatory_topics'] ?? 0);
        $completedTopics = (int)($progress['completed_topics'] ?? 0);
        $completedMandatory = (int)($progress['completed_mandatory'] ?? 0);
        
        // Course complete if all mandatory topics done
        $isComplete = $mandatoryTopics > 0 && $completedMandatory >= $mandatoryTopics;
        
        // Check if certificate exists
        $certStmt = $db->prepare("
            SELECT * FROM certificates 
            WHERE user_id = ? AND course_id = ? AND status = 'active'
        ");
        $certStmt->execute([$userId, $courseId]);
        $certificate = $certStmt->fetch(PDO::FETCH_ASSOC);
        $hasCertificate = $certificate !== false;
        
        echo json_encode([
            'success' => true,
            'is_complete' => $isComplete,
            'has_certificate' => $hasCertificate,
            'certificate_id' => $certificate['id'] ?? null,
            'progress' => [
                'completed' => $completedTopics,
                'total' => $totalTopics,
                'mandatory_completed' => $completedMandatory,
                'mandatory_total' => $mandatoryTopics
            ]
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ✅ GET USER CERTIFICATES LIST (FIXED - Removed thumbnail_url)
if ($action === 'list' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $stmt = $db->prepare("
            SELECT 
                cert.*,
                COALESCE(cert.certificate_number, cert.certificate_code) as display_number,
                c.title as course_title,
                c.description as course_description
            FROM certificates cert
            INNER JOIN courses c ON cert.course_id = c.id
            WHERE cert.user_id = ? AND cert.status = 'active'
            ORDER BY cert.issued_date DESC
        ");
        $stmt->execute([$userId]);
        $certificates = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'certificates' => $certificates
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ✅ VERIFY CERTIFICATE
if ($action === 'verify' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $certNumber = $_GET['certificate_number'] ?? '';
        
        if (!$certNumber) {
            throw new Exception('Certificate number required');
        }
        
        $stmt = $db->prepare("
            SELECT 
                cert.*,
                COALESCE(cert.certificate_number, cert.certificate_code) as display_number,
                u.name as user_name,
                c.title as course_title
            FROM certificates cert
            INNER JOIN users u ON cert.user_id = u.id
            INNER JOIN courses c ON cert.course_id = c.id
            WHERE (cert.certificate_number = ? OR cert.certificate_code = ?) 
            AND cert.status = 'active'
        ");
        $stmt->execute([$certNumber, $certNumber]);
        $certificate = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$certificate) {
            echo json_encode([
                'success' => false,
                'error' => 'Certificate not found or has been revoked'
            ]);
            exit;
        }
        
        echo json_encode([
            'success' => true,
            'certificate' => [
                'certificate_number' => $certificate['display_number'],
                'user_name' => $certificate['user_name'],
                'course_title' => $certificate['course_title'],
                'issued_date' => date('F d, Y', strtotime($certificate['issued_date'])),
                'is_valid' => true
            ]
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'error' => 'Invalid action']);
