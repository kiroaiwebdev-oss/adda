<?php
header('Content-Type: application/json');

try {
    require_once __DIR__ . '/../vendor/autoload.php';
    
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    
    // Server settings
    $mail->isSMTP();
    $mail->Host = 'smtp.hostinger.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'no-reply@internshipadda.com';
    $mail->Password = 'Internship2000@!';
    $mail->SMTPSecure = 'ssl';
    $mail->Port = 465;
    
    // Disable SSL verification (for testing)
    $mail->SMTPOptions = array(
        'ssl' => array(
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        )
    );
    
    // Enable debugging
    $mail->SMTPDebug = 2;
    $mail->Debugoutput = function($str, $level) {
        error_log("SMTP: $str");
    };
    
    // Recipients
    $mail->setFrom('no-reply@internshipadda.com', 'Internship Adda');
    $mail->addAddress('kush894576845@gmail.com', 'Test User'); // ⚠️ Your email
    
    // Content
    $mail->isHTML(true);
    $mail->Subject = 'Test Email from Internship Adda';
    $mail->Body = '<h1>Test Successful! ✅</h1><p>Your OTP email system is working!</p>';
    $mail->AltBody = 'Test Successful! Your OTP email system is working!';
    
    // Send
    $mail->send();
    
    echo json_encode([
        'success' => true,
        'message' => 'Email sent successfully! Check your inbox.',
        'recipient' => 'kush894576845@gmail.com'
    ]);
    
} catch (\PHPMailer\PHPMailer\Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'error_info' => $mail->ErrorInfo ?? 'No additional info'
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
