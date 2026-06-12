<?php
session_start();
header('Content-Type: application/json');
ini_set('display_errors', 0); ini_set('log_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../app/config/database.php';

$dbConfig = require __DIR__ . '/../app/config/database.php';
$dsn = sprintf("mysql:host=%s;port=%s;dbname=%s;charset=%s",
    $dbConfig['host'], $dbConfig['port'], $dbConfig['database'], $dbConfig['charset']
);

try {
    $db = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options']);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed: ' . $e->getMessage()]);
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

try {
    $auth = new Auth($db);
    $auth->requireAdmin();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Authentication failed: ' . $e->getMessage()]);
    exit;
}

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'approve':
            $data = json_decode(file_get_contents('php://input'), true);
            
            $requestId = $data['request_id'] ?? null;
            $studentName = $data['student_name'] ?? null;
            $templateId = $data['template_id'] ?? null;
            $adminNotes = $data['admin_notes'] ?? '';
            
            if (!$requestId || !$studentName || !$templateId) {
                throw new Exception('Missing required fields: request_id, student_name, and template_id');
            }
            
            // ✅ Get request details WITH proper joins
            $stmt = $db->prepare("
                SELECT 
                    icr.id,
                    icr.user_id,
                    icr.internship_id AS req_internship_id,
                    icr.enrollment_id,
                    icr.student_name,
                    icr.email,
                    icr.phone,
                    icr.linkedin_url,
                    icr.notes,
                    icr.status,
                    i.id as internship_id,
                    i.title as internship_title,
                    i.company_name as internship_company,
                    ie.created_at as enrollment_start_date,
                    u.email as user_email,
                    u.name as user_full_name
                FROM internship_certificate_requests icr
                INNER JOIN internships i ON icr.internship_id = i.id
                INNER JOIN internship_enrollments ie ON icr.enrollment_id = ie.id
                INNER JOIN users u ON icr.user_id = u.id
                WHERE icr.id = ? AND icr.status = 'pending'
            ");
            $stmt->execute([$requestId]);
            $request = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$request) {
                throw new Exception('Request not found or already processed');
            }
            
            // ✅ Get template details
            $tempStmt = $db->prepare("SELECT * FROM certificate_templates WHERE id = ? AND is_active = 1");
            $tempStmt->execute([$templateId]);
            $template = $tempStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$template) {
                throw new Exception('Template not found or inactive');
            }
            
            // ✅ IMPORTANT: Determine company name with fallback
            $companyName = null;
            if (!empty($request['internship_company'])) {
                $companyName = $request['internship_company'];
            } elseif (!empty($template['company_name'])) {
                $companyName = $template['company_name'];
            } else {
                $companyName = 'Tech Company'; // Default fallback
            }
            
            // ✅ Ensure internship title is not null
            $internshipTitle = $request['internship_title'] ?? 'Internship Program';
            
            // ✅ Start date
            $startDate = $request['enrollment_start_date'] ?? date('Y-m-d');
            
            $db->beginTransaction();
            
            try {
                // ✅ Update request status to approved
                $currentUser = $auth->user();
                $updateStmt = $db->prepare("
                    UPDATE internship_certificate_requests 
                    SET status = 'approved',
                        student_name = ?,
                        processed_by = ?,
                        processed_at = NOW(),
                        admin_notes = ?,
                        certificate_issued_at = NOW()
                    WHERE id = ?
                ");
                $updateStmt->execute([$studentName, $currentUser['id'], $adminNotes, $requestId]);
                
                // ✅ Generate unique certificate code and number
                $certificateCode = 'CERT-' . strtoupper(substr(uniqid(), -8));
                $certificateNumber = 'IA-' . date('Y') . '-' . str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT);
                
                // ✅ Calculate duration in months
                $startDateTime = new DateTime($startDate);
                $endDateTime = new DateTime();
                $interval = $startDateTime->diff($endDateTime);
                $durationMonths = ($interval->y * 12) + $interval->m;
                
                // Minimum 1 month
                if ($durationMonths < 1) {
                    $durationMonths = 1;
                }
                
                // ✅ CREATE CERTIFICATE ENTRY in internship_certificates table
                $insertCert = $db->prepare("
                    INSERT INTO internship_certificates (
                        user_id,
                        request_id,
                        certificate_code,
                        certificate_number,
                        student_name,
                        company_name,
                        internship_title,
                        start_date,
                        end_date,
                        duration_months,
                        template_id,
                        issued_at,
                        revoked
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, NOW(), 0)
                ");
                
                $insertResult = $insertCert->execute([
                    $request['user_id'],
                    $requestId,
                    $certificateCode,
                    $certificateNumber,
                    $studentName,
                    $companyName,
                    $internshipTitle,
                    $startDate,
                    $durationMonths,
                    $templateId
                ]);
                
                if (!$insertResult) {
                    throw new Exception('Failed to create certificate record');
                }
                
                $certificateId = $db->lastInsertId();
                
                $db->commit();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Certificate approved and issued successfully',
                    'certificate_id' => $certificateId,
                    'certificate_code' => $certificateCode,
                    'certificate_number' => $certificateNumber
                ]);
                
            } catch (Exception $e) {
                $db->rollBack();
                error_log("❌ Transaction Error: " . $e->getMessage());
                throw new Exception('Transaction failed: ' . $e->getMessage());
            }
            break;
            
        case 'reject':
            $data = json_decode(file_get_contents('php://input'), true);
            
            $requestId = $data['request_id'] ?? null;
            $adminNotes = $data['admin_notes'] ?? '';
            
            if (!$requestId) {
                throw new Exception('Request ID required');
            }
            
            if (empty($adminNotes)) {
                throw new Exception('Rejection reason is required');
            }
            
            // Check if request exists
            $checkStmt = $db->prepare("SELECT id, status FROM internship_certificate_requests WHERE id = ?");
            $checkStmt->execute([$requestId]);
            $request = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$request) {
                throw new Exception('Request not found');
            }
            
            if ($request['status'] !== 'pending') {
                throw new Exception('Request already processed');
            }
            
            // Update to rejected
            $currentUser = $auth->user();
            $updateStmt = $db->prepare("
                UPDATE internship_certificate_requests 
                SET status = 'rejected',
                    processed_by = ?,
                    processed_at = NOW(),
                    admin_notes = ?
                WHERE id = ?
            ");
            $updateStmt->execute([$currentUser['id'], $adminNotes, $requestId]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Request rejected successfully',
                'request_id' => $requestId
            ]);
            break;
            
        case 'revoke':
            $data = json_decode(file_get_contents('php://input'), true);
            $certificateId = $data['certificate_id'] ?? null;
            
            if (!$certificateId) {
                throw new Exception('Certificate ID required');
            }
            
            // Check if certificate exists
            $checkStmt = $db->prepare("SELECT id, revoked FROM internship_certificates WHERE id = ?");
            $checkStmt->execute([$certificateId]);
            $cert = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$cert) {
                throw new Exception('Certificate not found');
            }
            
            if ($cert['revoked']) {
                throw new Exception('Certificate already revoked');
            }
            
            $currentUser = $auth->user();
            $stmt = $db->prepare("
                UPDATE internship_certificates 
                SET revoked = 1, 
                    revoked_at = NOW(), 
                    revoked_by = ?
                WHERE id = ?
            ");
            $stmt->execute([$currentUser['id'], $certificateId]);
            
            echo json_encode([
                'success' => true, 
                'message' => 'Certificate revoked successfully'
            ]);
            break;
            
        case 'restore':
            $data = json_decode(file_get_contents('php://input'), true);
            $certificateId = $data['certificate_id'] ?? null;
            
            if (!$certificateId) {
                throw new Exception('Certificate ID required');
            }
            
            // Check if certificate exists
            $checkStmt = $db->prepare("SELECT id, revoked FROM internship_certificates WHERE id = ?");
            $checkStmt->execute([$certificateId]);
            $cert = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$cert) {
                throw new Exception('Certificate not found');
            }
            
            if (!$cert['revoked']) {
                throw new Exception('Certificate is not revoked');
            }
            
            $stmt = $db->prepare("
                UPDATE internship_certificates 
                SET revoked = 0, 
                    revoked_at = NULL, 
                    revoked_by = NULL
                WHERE id = ?
            ");
            $stmt->execute([$certificateId]);
            
            echo json_encode([
                'success' => true, 
                'message' => 'Certificate restored successfully'
            ]);
            break;
            
        case 'verify':
            $data = json_decode(file_get_contents('php://input'), true);
            $certificateCode = $data['certificate_code'] ?? null;
            
            if (!$certificateCode) {
                throw new Exception('Certificate code required');
            }
            
            $stmt = $db->prepare("
                SELECT 
                    ic.*,
                    u.email as user_email,
                    u.name as user_name,
                    ct.template_name,
                    ct.company_name as template_company
                FROM internship_certificates ic
                LEFT JOIN users u ON ic.user_id = u.id
                LEFT JOIN certificate_templates ct ON ic.template_id = ct.id
                WHERE ic.certificate_code = ?
            ");
            $stmt->execute([$certificateCode]);
            $cert = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$cert) {
                echo json_encode([
                    'success' => false, 
                    'valid' => false, 
                    'message' => 'Certificate not found in our system'
                ]);
            } elseif ($cert['revoked']) {
                echo json_encode([
                    'success' => true, 
                    'valid' => false, 
                    'message' => 'Certificate has been revoked',
                    'revoked_at' => $cert['revoked_at']
                ]);
            } else {
                echo json_encode([
                    'success' => true,
                    'valid' => true,
                    'certificate' => $cert,
                    'message' => 'Certificate is valid and active'
                ]);
            }
            break;
            
        default:
            throw new Exception('Invalid action: ' . $action);
    }
    
} catch (PDOException $e) {
    error_log("❌ Database Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("❌ Error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'error' => $e->getMessage()
    ]);
}
