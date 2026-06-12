<?php
/**
 * Contact Form Submission API
 * Simple version without external dependencies
 */

// Error reporting (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display, but log
ini_set('log_errors', 1);

// Start output buffering
ob_start();

// Set headers FIRST
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    ob_end_clean();
    exit;
}

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Function to send JSON response and exit
function sendResponse($success, $message, $data = null, $code = 200) {
    ob_clean();
    http_response_code($code);
    $response = [
        'success' => $success,
        'message' => $message
    ];
    if ($data !== null) {
        $response['data'] = $data;
    }
    echo json_encode($response);
    ob_end_flush();
    exit;
}

try {
    // Get database config from your existing file
    $configFile = __DIR__ . '/../app/config/database.php';
    
    if (file_exists($configFile)) {
        require_once $configFile;
        // Assuming $db is created in database.php
        if (!isset($db)) {
            throw new Exception('Database connection not established');
        }
    } else {
        // Manual database connection if config file doesn't exist
        // ⚠️ UPDATE THESE CREDENTIALS
        $host = 'localhost';
        $dbname = 'u761369285_lms';
        $username = 'u761369285_lms';
        $password = 'Sarun@2005#lms';
        
        try {
            $db = new PDO(
                "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
                $username,
                $password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (PDOException $e) {
            sendResponse(false, 'Database connection failed', null, 500);
        }
    }
    
    // Get raw POST data
    $rawInput = file_get_contents('php://input');
    
    if (empty($rawInput)) {
        sendResponse(false, 'No data received', null, 400);
    }
    
    $input = json_decode($rawInput, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        sendResponse(false, 'Invalid JSON: ' . json_last_error_msg(), null, 400);
    }
    
    // Validate required fields
    if (empty($input['name'])) {
        sendResponse(false, 'Name is required', null, 400);
    }
    
    if (empty($input['email'])) {
        sendResponse(false, 'Email is required', null, 400);
    }
    
    if (empty($input['subject'])) {
        sendResponse(false, 'Subject is required', null, 400);
    }
    
    if (empty($input['message'])) {
        sendResponse(false, 'Message is required', null, 400);
    }
    
    // Sanitize inputs
    $name = trim(strip_tags($input['name']));
    $email = trim(filter_var($input['email'], FILTER_SANITIZE_EMAIL));
    $phone = !empty($input['phone']) ? trim(strip_tags($input['phone'])) : null;
    $subject = trim(strip_tags($input['subject']));
    $message = trim(strip_tags($input['message']));
    
    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendResponse(false, 'Invalid email format', null, 400);
    }
    
    // Validate message length
    if (strlen($message) < 10) {
        sendResponse(false, 'Message too short (min 10 characters)', null, 400);
    }
    
    if (strlen($message) > 5000) {
        sendResponse(false, 'Message too long (max 5000 characters)', null, 400);
    }
    
    // Get client info
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    
    // Determine priority
    $priority = 'medium';
    $subjectLower = strtolower($subject);
    
    if (strpos($subjectLower, 'urgent') !== false || $subject === 'billing') {
        $priority = 'high';
    } elseif ($subject === 'general' || $subject === 'feedback') {
        $priority = 'low';
    }
    
    // Check if table exists
    $tableCheck = $db->query("SHOW TABLES LIKE 'contact_submissions'");
    if ($tableCheck->rowCount() === 0) {
        sendResponse(false, 'Contact submissions table not found. Please run database migrations.', null, 500);
    }
    
    // Insert into database
    $sql = "INSERT INTO contact_submissions 
            (name, email, phone, subject, message, priority, ip_address, user_agent, created_at) 
            VALUES (:name, :email, :phone, :subject, :message, :priority, :ip, :ua, NOW())";
    
    $stmt = $db->prepare($sql);
    
    $success = $stmt->execute([
        ':name' => $name,
        ':email' => $email,
        ':phone' => $phone,
        ':subject' => $subject,
        ':message' => $message,
        ':priority' => $priority,
        ':ip' => $ipAddress,
        ':ua' => $userAgent
    ]);
    
    if (!$success) {
        sendResponse(false, 'Failed to save submission', null, 500);
    }
    
    $submissionId = $db->lastInsertId();
    
    // Try sending email (optional, won't fail submission)
    try {
        $to = 'support@internshipadda.com';
        $emailSubject = "New Contact: {$subject}";
        $emailBody = "New contact submission #{$submissionId}\n\n";
        $emailBody .= "Name: {$name}\n";
        $emailBody .= "Email: {$email}\n";
        $emailBody .= "Phone: {$phone}\n";
        $emailBody .= "Subject: {$subject}\n";
        $emailBody .= "Priority: {$priority}\n\n";
        $emailBody .= "Message:\n{$message}\n\n";
        $emailBody .= "---\nIP: {$ipAddress}\nTime: " . date('Y-m-d H:i:s');
        
        $headers = "From: noreply@internshipadda.com\r\n";
        $headers .= "Reply-To: {$email}\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();
        
        @mail($to, $emailSubject, $emailBody, $headers);
        
        // User email
        $userBody = "Dear {$name},\n\n";
        $userBody .= "Thank you for contacting Internship Adda!\n\n";
        $userBody .= "We have received your message and will respond within 24 hours.\n\n";
        $userBody .= "Reference ID: #{$submissionId}\n\n";
        $userBody .= "Best Regards,\nInternship Adda Team\n\n";
        $userBody .= "---\n";
        $userBody .= "Email: support@internshipadda.com\n";
        $userBody .= "Phone: +91 72097 47479";
        
        @mail($email, "Thank you for contacting us", $userBody, "From: support@internshipadda.com");
        
    } catch (Exception $e) {
        error_log("Email send failed: " . $e->getMessage());
    }
    
    // Success response
    sendResponse(
        true,
        'Thank you! Your message has been sent successfully. We will get back to you within 24 hours.',
        [
            'submission_id' => $submissionId,
            'reference_id' => 'IA-' . str_pad($submissionId, 6, '0', STR_PAD_LEFT)
        ],
        201
    );
    
} catch (PDOException $e) {
    error_log("Database error in contact form: " . $e->getMessage());
    sendResponse(false, 'Database error: ' . $e->getMessage(), null, 500);
    
} catch (Exception $e) {
    error_log("Error in contact form: " . $e->getMessage());
    sendResponse(false, 'Server error: ' . $e->getMessage(), null, 500);
}

// Should never reach here
ob_end_clean();
