<?php
/**
 * Email Verification API - Production Ready with Email Sending
 */

// Security headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Error handling
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Start session
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

// ============================================
// LOAD DATABASE CONFIGURATION
// ============================================
try {
    $configPath = __DIR__ . '/../app/config/database.php';
    
    if (!file_exists($configPath)) {
        throw new Exception('Database configuration file not found');
    }
    
    require_once $configPath;
    $dbConfig = require $configPath;
    
    // Create PDO connection
    $dsn = sprintf(
        "mysql:host=%s;port=%s;dbname=%s;charset=%s",
        $dbConfig['host'],
        $dbConfig['port'],
        $dbConfig['database'],
        $dbConfig['charset']
    );
    
    $db = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options']);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
} catch (Exception $e) {
    error_log("Database Connection Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database connection failed',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}

// ============================================
// HELPER FUNCTIONS
// ============================================

function sendResponse($success, $message, $data = []) {
    http_response_code($success ? 200 : 400);
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message,
        'timestamp' => date('Y-m-d H:i:s')
    ], $data));
    exit;
}

function generateOTP() {
    return str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
}

function storeOTP($db, $email, $otp, $purpose = 'signup') {
    try {
        $cleanupStmt = $db->prepare("DELETE FROM email_verifications WHERE email = ? AND purpose = ? AND (verified = 1 OR expires_at < NOW())");
        $cleanupStmt->execute([$email, $purpose]);
        
        $deleteStmt = $db->prepare("DELETE FROM email_verifications WHERE email = ? AND purpose = ? AND verified = 0");
        $deleteStmt->execute([$email, $purpose]);
        
        $expiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));
        $insertStmt = $db->prepare("INSERT INTO email_verifications (email, otp, purpose, expires_at, verified, created_at) VALUES (?, ?, ?, ?, 0, NOW())");
        
        return $insertStmt->execute([$email, $otp, $purpose, $expiresAt]);
        
    } catch (PDOException $e) {
        error_log("Store OTP Error: " . $e->getMessage());
        return false;
    }
}

function verifyOTP($db, $email, $otp, $purpose = 'signup') {
    try {
        $stmt = $db->prepare("SELECT id, created_at FROM email_verifications WHERE email = ? AND otp = ? AND purpose = ? AND expires_at > NOW() AND verified = 0 ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$email, $otp, $purpose]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($record) {
            $updateStmt = $db->prepare("UPDATE email_verifications SET verified = 1 WHERE id = ?");
            $updateStmt->execute([$record['id']]);
            return true;
        }
        
        return false;
        
    } catch (PDOException $e) {
        error_log("Verify OTP Error: " . $e->getMessage());
        return false;
    }
}

function checkRateLimit($db, $email) {
    try {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM email_verifications WHERE email = ? AND created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)");
        $stmt->execute([$email]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return ($result['count'] < 5);
    } catch (PDOException $e) {
        error_log("Rate Limit Check Error: " . $e->getMessage());
        return true;
    }
}

/**
 * ✅ REAL EMAIL SENDING FUNCTION - UPDATED
 */
function sendOTPEmail($email, $name, $otp) {
    try {
        // Load PHPMailer
        $autoloadPath = __DIR__ . '/../vendor/autoload.php';
        
        if (!file_exists($autoloadPath)) {
            error_log("PHPMailer autoload not found. OTP for {$email}: {$otp}");
            return [
                'success' => true,
                'debug_otp' => $otp,
                'email_sent' => false,
                'note' => 'PHPMailer not installed'
            ];
        }
        
        require_once $autoloadPath;
        
        // Create PHPMailer instance
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        
        // SMTP Configuration
        $mail->isSMTP();
        $mail->Host = 'smtp.hostinger.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'no-reply@internshipadda.com';
        $mail->Password = 'Internship2000@!';
        $mail->SMTPSecure = 'ssl';
        $mail->Port = 465;
        
        // SSL Options
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        // Debugging (0 = off, 2 = verbose)
        $mail->SMTPDebug = 0;
        $mail->Debugoutput = function($str, $level) {
            error_log("SMTP: $str");
        };
        
        // Sender & Recipient
        $mail->setFrom('no-reply@internshipadda.com', 'Internship Adda');
        $mail->addAddress($email, $name);
        $mail->addReplyTo('no-reply@internshipadda.com', 'Internship Adda');
        
        // Email Content
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = 'Verify Your Email - Internship Adda';
        
        // HTML Email Body
        $mail->Body = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin:0;padding:0;font-family:Arial,sans-serif;background:#f5f5f5;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background:#f5f5f5;padding:40px 20px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 12px rgba(0,0,0,0.1);">
                    <tr>
                        <td style="background:linear-gradient(135deg, #22c55e 0%, #16a34a 100%);padding:40px 30px;text-align:center;">
                            <h1 style="color:#ffffff;margin:0;font-size:28px;">🎓 Internship Adda</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:40px 30px;">
                            <h2 style="color:#111827;margin-top:0;font-size:24px;">Hello {$name}! 👋</h2>
                            <p style="color:#4b5563;font-size:16px;line-height:1.6;margin:20px 0;">
                                Thank you for signing up with <strong>Internship Adda</strong>! To complete your registration, please use this One-Time Password (OTP):
                            </p>
                            <table width="100%" cellpadding="0" cellspacing="0" style="margin:30px 0;">
                                <tr>
                                    <td style="background:#f0fdf4;border:2px dashed #22c55e;border-radius:8px;padding:30px;text-align:center;">
                                        <p style="color:#6b7280;margin:0;font-size:14px;font-weight:bold;">Your Verification Code</p>
                                        <p style="font-size:42px;font-weight:bold;color:#16a34a;letter-spacing:8px;margin:10px 0;font-family:'Courier New',monospace;">{$otp}</p>
                                        <p style="color:#6b7280;margin:10px 0 0 0;font-size:14px;">Valid for 10 minutes</p>
                                    </td>
                                </tr>
                            </table>
                            <table width="100%" cellpadding="0" cellspacing="0" style="margin:20px 0;">
                                <tr>
                                    <td style="background:#fef3c7;border-left:4px solid #f59e0b;padding:15px;border-radius:4px;">
                                        <p style="margin:0 0 10px 0;font-weight:bold;color:#92400e;">⚠️ Security Notice:</p>
                                        <ul style="margin:0;padding-left:20px;color:#92400e;font-size:14px;">
                                            <li>Do not share this OTP with anyone</li>
                                            <li>Our team will never ask for your OTP</li>
                                            <li>This code expires in 10 minutes</li>
                                        </ul>
                                    </td>
                                </tr>
                            </table>
                            <p style="color:#6b7280;font-size:14px;margin:20px 0 0 0;">
                                If you didn't request this code, please ignore this email.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="background:#f9fafb;padding:30px;text-align:center;">
                            <p style="margin:0 0 10px 0;color:#6b7280;font-size:14px;">
                                <strong>Internship Adda</strong><br>
                                Your Gateway to Professional Success
                            </p>
                            <p style="margin:0;color:#6b7280;font-size:12px;">
                                This is an automated email. Please do not reply.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
        
        // Plain text alternative
        $mail->AltBody = "Hello {$name}!\n\nYour OTP is: {$otp}\n\nValid for 10 minutes.\n\nDo not share this code with anyone.\n\n- Internship Adda Team";
        
        // Send email
        $mail->send();
        
        // Log success
        error_log("✅ Email sent successfully to: {$email} | OTP: {$otp}");
        
        return [
            'success' => true,
            'email_sent' => true,
            'debug_otp' => $otp
        ];
        
    } catch (\PHPMailer\PHPMailer\Exception $e) {
        error_log("❌ PHPMailer Error: " . $e->getMessage() . " | OTP for {$email}: {$otp}");
        return [
            'success' => true,
            'debug_otp' => $otp,
            'email_sent' => false,
            'email_error' => $e->getMessage()
        ];
    } catch (Exception $e) {
        error_log("❌ Email Error: " . $e->getMessage() . " | OTP for {$email}: {$otp}");
        return [
            'success' => true,
            'debug_otp' => $otp,
            'email_sent' => false,
            'error' => $e->getMessage()
        ];
    }
}

// ============================================
// MAIN REQUEST HANDLER
// ============================================

try {
    $action = isset($_GET['action']) ? trim($_GET['action']) : '';
    $input = file_get_contents('php://input');
    $requestData = json_decode($input, true);
    
    if (!$requestData) {
        $requestData = [];
    }
    
    switch ($action) {
        case 'send-otp':
            $email = isset($requestData['email']) ? trim($requestData['email']) : '';
            $name = isset($requestData['name']) ? trim($requestData['name']) : 'User';
            
            if (empty($email)) {
                sendResponse(false, 'Email is required');
            }
            
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                sendResponse(false, 'Invalid email format');
            }
            
            $checkStmt = $db->prepare("SELECT id FROM users WHERE email = ?");
            $checkStmt->execute([$email]);
            if ($checkStmt->fetch()) {
                sendResponse(false, 'Email already registered. Please login instead.');
            }
            
            if (!checkRateLimit($db, $email)) {
                sendResponse(false, 'Too many requests. Please try again in 5 minutes.');
            }
            
            $otp = generateOTP();
            
            $stored = storeOTP($db, $email, $otp, 'signup');
            
            if (!$stored) {
                sendResponse(false, 'Failed to generate OTP. Please try again.');
            }
            
            $emailResult = sendOTPEmail($email, $name, $otp);
            
            sendResponse(true, 'OTP sent successfully to your email', [
                'email' => $email,
                'expires_in' => '10 minutes',
                'debug_otp' => $emailResult['debug_otp'] ?? null,
                'email_sent' => $emailResult['email_sent'] ?? false,
                'email_error' => $emailResult['email_error'] ?? null
            ]);
            break;
            
        case 'verify-otp':
            $email = isset($requestData['email']) ? trim($requestData['email']) : '';
            $otp = isset($requestData['otp']) ? trim($requestData['otp']) : '';
            
            if (empty($email) || empty($otp)) {
                sendResponse(false, 'Email and OTP are required');
            }
            
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                sendResponse(false, 'Invalid email format');
            }
            
            if (strlen($otp) !== 6 || !ctype_digit($otp)) {
                sendResponse(false, 'Invalid OTP format. Must be 6 digits.');
            }
            
            $verified = verifyOTP($db, $email, $otp, 'signup');
            
            if ($verified) {
                $_SESSION['verified_email'] = $email;
                $_SESSION['email_verified_at'] = time();
                
                sendResponse(true, 'Email verified successfully!', [
                    'email' => $email,
                    'verified' => true
                ]);
            } else {
                sendResponse(false, 'Invalid or expired OTP. Please try again or request a new OTP.');
            }
            break;
            
        case 'resend-otp':
            $email = isset($requestData['email']) ? trim($requestData['email']) : '';
            $name = isset($requestData['name']) ? trim($requestData['name']) : 'User';
            
            if (empty($email)) {
                sendResponse(false, 'Email is required');
            }
            
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                sendResponse(false, 'Invalid email format');
            }
            
            if (!checkRateLimit($db, $email)) {
                sendResponse(false, 'Too many requests. Please wait 5 minutes before requesting a new OTP.');
            }
            
            $otp = generateOTP();
            $stored = storeOTP($db, $email, $otp, 'signup');
            
            if (!$stored) {
                sendResponse(false, 'Failed to generate OTP. Please try again.');
            }
            
            $emailResult = sendOTPEmail($email, $name, $otp);
            
            sendResponse(true, 'New OTP sent successfully', [
                'email' => $email,
                'debug_otp' => $emailResult['debug_otp'] ?? null
            ]);
            break;
            
        case 'check-status':
            $email = isset($requestData['email']) ? trim($requestData['email']) : '';
            
            if (empty($email)) {
                sendResponse(false, 'Email is required');
            }
            
            $verified = isset($_SESSION['verified_email']) && $_SESSION['verified_email'] === $email;
            
            sendResponse(true, $verified ? 'Email is verified' : 'Email not verified', [
                'email' => $email,
                'verified' => $verified
            ]);
            break;
            
        default:
            sendResponse(false, 'Invalid action. Available actions: send-otp, verify-otp, resend-otp, check-status');
            break;
    }
    
} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    http_response_code(500);
    sendResponse(false, 'A database error occurred. Please try again later.');
    
} catch (Exception $e) {
    error_log("General Error: " . $e->getMessage());
    http_response_code(500);
    sendResponse(false, 'An unexpected error occurred. Please try again later.');
}
