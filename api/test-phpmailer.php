<?php
header('Content-Type: application/json');

try {
    // Load autoloader
    $autoloadPath = __DIR__ . '/../vendor/autoload.php';
    
    if (!file_exists($autoloadPath)) {
        throw new Exception('Autoload file not found at: ' . $autoloadPath);
    }
    
    require_once $autoloadPath;
    
    // Test PHPMailer
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    
    echo json_encode([
        'success' => true,
        'message' => 'PHPMailer loaded successfully! ✅',
        'version' => \PHPMailer\PHPMailer\PHPMailer::VERSION,
        'path' => $autoloadPath
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
