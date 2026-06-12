<?php
/**
 * Create Razorpay Order API
 * Creates a Razorpay order for course/internship payment
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    // Database connection
    $dbConfig = require __DIR__ . '/../app/config/database.php';
    $dsn = sprintf("mysql:host=%s;port=%s;dbname=%s;charset=%s",
        $dbConfig['host'], $dbConfig['port'], $dbConfig['database'], $dbConfig['charset']
    );
    $db = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options']);
    
    // Autoload classes
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
    
    // Check authentication
    $auth = new Auth($db);
    if (!$auth->check()) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }
    
    $userId = $auth->id();
    
    // Get request data (support both JSON and FormData)
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    
    if (strpos($contentType, 'application/json') !== false) {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
            exit;
        }
    } else {
        // FormData (from fetch with FormData)
        $data = $_POST;
    }
    
    // ✅ NEW: Support both course_id and internship_id
    $courseId = $data['course_id'] ?? null;
    $internshipId = $data['internship_id'] ?? null;
    $amount = $data['amount'] ?? null;
    $couponId = $data['coupon_id'] ?? null;
    
    if ((!$courseId && !$internshipId) || !$amount) {
        echo json_encode(['success' => false, 'error' => 'Missing course_id/internship_id or amount']);
        exit;
    }
    
    // Get Razorpay settings from database
    $settingsModel = new Settings($db);
    $razorpayEnabled = $settingsModel->get('razorpay_enabled', 'false');
    $razorpayKeyId = $settingsModel->get('razorpay_key_id', '');
    $razorpayKeySecret = $settingsModel->get('razorpay_key_secret', '');
    $razorpayMode = $settingsModel->get('razorpay_mode', 'test');
    $currency = $settingsModel->get('currency', 'INR');
    
    // Check if Razorpay is enabled
    if ($razorpayEnabled !== 'true') {
        echo json_encode(['success' => false, 'error' => 'Payment gateway not enabled']);
        exit;
    }
    
    // Check if keys are configured
    if (empty($razorpayKeyId) || empty($razorpayKeySecret)) {
        echo json_encode(['success' => false, 'error' => 'Payment gateway not configured']);
        exit;
    }
    
    // ✅ NEW: Handle COURSE or INTERNSHIP
    $orderType = '';
    $itemTitle = '';
    $itemPrice = 0;
    
    if ($courseId) {
        // COURSE Payment
        $orderType = 'course';
        
        // Get course details
        $stmt = $db->prepare("SELECT id, title, price FROM courses WHERE id = ? AND status = 'published'");
        $stmt->execute([$courseId]);
        $course = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$course) {
            echo json_encode(['success' => false, 'error' => 'Course not found']);
            exit;
        }
        
        // Check if already enrolled
        $stmt = $db->prepare("SELECT id FROM enrollments WHERE user_id = ? AND course_id = ?");
        $stmt->execute([$userId, $courseId]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'error' => 'Already enrolled']);
            exit;
        }
        
        $itemTitle = $course['title'];
        $itemPrice = $course['price'];
        
    } elseif ($internshipId) {
        // ✅ NEW: INTERNSHIP Payment
        $orderType = 'internship';
        
        // Get internship details
        $stmt = $db->prepare("SELECT id, title, price, discount_price FROM internships WHERE id = ? AND is_active = 1");
        $stmt->execute([$internshipId]);
        $internship = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$internship) {
            echo json_encode(['success' => false, 'error' => 'Internship not found']);
            exit;
        }
        
        // Check if already applied
        $stmt = $db->prepare("SELECT id FROM internship_enrollments WHERE user_id = ? AND internship_id = ?");
        $stmt->execute([$userId, $internshipId]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'error' => 'Already applied']);
            exit;
        }
        
        $itemTitle = $internship['title'];
        
        // Use discount price if available
        $price = floatval($internship['price'] ?? 0);
        $discountPrice = floatval($internship['discount_price'] ?? 0);
        $itemPrice = ($discountPrice > 0 && $discountPrice < $price) ? $discountPrice : $price;
    }
    
    // Validate amount (allow slight difference for coupon discounts)
    $expectedAmount = $itemPrice * 100; // In paise
    $receivedAmount = floatval($amount) * 100; // In paise
    
    // Allow amount to be less than or equal to expected (for coupons)
    if ($receivedAmount > $expectedAmount || $receivedAmount <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid amount']);
        exit;
    }
    
    // ✅ Create Razorpay Order
    $receiptPrefix = $orderType === 'course' ? 'course_' : 'internship_';
    $itemId = $orderType === 'course' ? $courseId : $internshipId;
    
    $orderData = [
        'amount' => (int)($receivedAmount), // Amount in paise
        'currency' => $currency,
        'receipt' => $receiptPrefix . $itemId . '_user_' . $userId . '_' . time(),
        'notes' => [
            'order_type' => $orderType,
            'course_id' => $courseId ?? 0,
            'internship_id' => $internshipId ?? 0,
            'user_id' => $userId,
            'item_title' => $itemTitle,
            'coupon_id' => $couponId ?? 0
        ]
    ];
    
    $ch = curl_init('https://api.razorpay.com/v1/orders');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($orderData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_USERPWD, $razorpayKeyId . ':' . $razorpayKeySecret);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        $error = json_decode($response, true);
        echo json_encode([
            'success' => false, 
            'error' => 'Razorpay error: ' . ($error['error']['description'] ?? 'Unknown error')
        ]);
        exit;
    }
    
    $order = json_decode($response, true);
    
    // ✅ Save order to database for tracking
    if ($orderType === 'course') {
        // Course payment order
        $stmt = $db->prepare("
            INSERT INTO payment_orders (user_id, course_id, razorpay_order_id, amount, currency, status, created_at)
            VALUES (?, ?, ?, ?, ?, 'created', NOW())
        ");
        $stmt->execute([
            $userId,
            $courseId,
            $order['id'],
            $receivedAmount / 100, // Store in rupees
            $currency
        ]);
    } else {
        // ✅ NEW: Internship payment order
        try {
            $stmt = $db->prepare("
                INSERT INTO payment_orders (user_id, internship_id, razorpay_order_id, amount, currency, status, created_at)
                VALUES (?, ?, ?, ?, ?, 'created', NOW())
            ");
            $stmt->execute([
                $userId,
                $internshipId,
                $order['id'],
                $receivedAmount / 100, // Store in rupees
                $currency
            ]);
        } catch (PDOException $e) {
            // If internship_id column doesn't exist, use course_id column (backward compatibility)
            $stmt = $db->prepare("
                INSERT INTO payment_orders (user_id, course_id, razorpay_order_id, amount, currency, status, created_at)
                VALUES (?, ?, ?, ?, ?, 'created', NOW())
            ");
            $stmt->execute([
                $userId,
                $internshipId, // Store internship_id in course_id column
                $order['id'],
                $receivedAmount / 100,
                $currency
            ]);
        }
    }
    
    // Get user details for prefill
    $userStmt = $db->prepare("SELECT name, email FROM users WHERE id = ?");
    $userStmt->execute([$userId]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);
    
    // Return order details
    echo json_encode([
        'success' => true,
        'order_id' => $order['id'],
        'amount' => $order['amount'],
        'currency' => $order['currency'],
        'key_id' => $razorpayKeyId,
        'mode' => $razorpayMode,
        'prefill' => [
            'name' => $user['name'] ?? '',
            'email' => $user['email'] ?? '',
            'contact' => ''
        ]
    ]);
    
} catch (Exception $e) {
    error_log('Razorpay order creation error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
