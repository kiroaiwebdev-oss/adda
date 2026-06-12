<?php
session_start();
header('Content-Type: application/json');

error_log("📥 Create payment API called");

try {
    // Database connection
    $configPaths = [
        __DIR__ . '/../app/config/database.php',
        dirname(dirname(__FILE__)) . '/app/config/database.php',
    ];
    
    $dbConfig = null;
    foreach ($configPaths as $path) {
        if (file_exists($path)) {
            $dbConfig = require $path;
            break;
        }
    }
    
    if (!$dbConfig) {
        throw new Exception('Database config not found');
    }
    
    $dsn = sprintf("mysql:host=%s;port=%s;dbname=%s;charset=%s",
        $dbConfig['host'], $dbConfig['port'], $dbConfig['database'], $dbConfig['charset']
    );
    
    $db = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options']);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Autoload
    spl_autoload_register(function ($class) {
        $paths = [
            __DIR__ . '/../app/core/' . $class . '.php',
            __DIR__ . '/../app/models/' . $class . '.php',
            dirname(dirname(__FILE__)) . '/app/core/' . $class . '.php',
            dirname(dirname(__FILE__)) . '/app/models/' . $class . '.php',
        ];
        foreach ($paths as $path) {
            if (file_exists($path)) {
                require_once $path;
                return;
            }
        }
    });
    
    // Auth
    $auth = new Auth($db);
    if (!$auth->check()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Not logged in']);
        exit;
    }
    
    $user = $auth->user();
    
    // Get input
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    $courseId = $data['course_id'] ?? null;
    $amount = $data['amount'] ?? null;
    
    if (!$courseId || !$amount) {
        throw new Exception('Missing course_id or amount');
    }
    
    error_log("Course ID: $courseId, Amount: $amount");
    
    // Get course
    $stmt = $db->prepare("SELECT id, title, price FROM courses WHERE id = ?");
    $stmt->execute([$courseId]);
    $course = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$course) {
        throw new Exception('Course not found');
    }
    
    // Get Razorpay keys
    $keyStmt = $db->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('razorpay_key_id', 'razorpay_key_secret')");
    $keyStmt->execute();
    $settings = $keyStmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $keyId = $settings['razorpay_key_id'] ?? '';
    $keySecret = $settings['razorpay_key_secret'] ?? '';
    
    if (empty($keyId) || empty($keySecret)) {
        throw new Exception('Razorpay not configured');
    }
    
    // Create Razorpay order
    $orderId = 'order_' . time() . '_' . $user['id'];
    
    $ch = curl_init('https://api.razorpay.com/v1/orders');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERPWD, $keyId . ':' . $keySecret);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'amount' => $amount,
        'currency' => 'INR',
        'receipt' => $orderId,
        'notes' => ['course_id' => $courseId, 'user_id' => $user['id']]
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        error_log("Razorpay error: $response");
        throw new Exception('Payment gateway error');
    }
    
    $rzpOrder = json_decode($response, true);
    
    if (!isset($rzpOrder['id'])) {
        throw new Exception('Invalid Razorpay response');
    }
    
    // Save to DB
    try {
        $ins = $db->prepare("INSERT INTO payment_orders (user_id, course_id, razorpay_order_id, amount, status) VALUES (?, ?, ?, ?, 'created')");
        $ins->execute([$user['id'], $courseId, $rzpOrder['id'], $amount / 100]);
    } catch (Exception $e) {
        error_log("DB save warning: " . $e->getMessage());
    }
    
    echo json_encode([
        'success' => true,
        'order_id' => $rzpOrder['id'],
        'razorpay_key_id' => $keyId,
        'amount' => $amount,
        'user_name' => $user['name'] ?? '',
        'user_email' => $user['email'] ?? '',
        'user_phone' => $user['phone'] ?? ''
    ]);
    
} catch (Exception $e) {
    error_log("Payment error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
