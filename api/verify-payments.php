<?php
session_start();
header('Content-Type: application/json');

// ✅ Get first topic URL for auto-redirect after enrollment
function getFirstTopicUrl($db, $courseId) {
    try {
        error_log("🔍 Finding first topic for course: $courseId");
        
        // ✅ Get first chapter (using chapter_order AND sort_order)
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
            error_log("⚠️ No chapters found for course: $courseId");
            return '/app/views/learner/my-courses.php';
        }
        
        error_log("✅ First chapter ID: $chapterId");
        
        // ✅ Get first topic of first chapter
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
            error_log("⚠️ No topics found for chapter: $chapterId");
            return '/app/views/learner/my-courses.php';
        }
        
        error_log("✅ First topic ID: $topicId");
        
        $redirectUrl = "/app/views/learner/course-player.php?id={$courseId}&topic={$topicId}";
        error_log("🚀 Redirecting to: $redirectUrl");
        
        return $redirectUrl;
        
    } catch (Exception $e) {
        error_log("❌ First topic URL error: " . $e->getMessage());
        return '/app/views/learner/my-courses.php';
    }
}

error_log("🔐 Verify payment API called");

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
    
    $orderId = $data['razorpay_order_id'] ?? null;
    $paymentId = $data['razorpay_payment_id'] ?? null;
    $signature = $data['razorpay_signature'] ?? null;
    $courseId = $data['course_id'] ?? null;
    $couponId = $data['coupon_id'] ?? null;
    
    if (!$orderId || !$paymentId || !$signature || !$courseId) {
        throw new Exception('Missing payment data');
    }
    
    error_log("Verifying - Order: $orderId, Payment: $paymentId, Course: $courseId");
    
    // ✅ GET PAYMENT AMOUNT FROM payment_orders TABLE
    $orderStmt = $db->prepare("SELECT amount, currency FROM payment_orders WHERE razorpay_order_id = ? AND user_id = ?");
    $orderStmt->execute([$orderId, $user['id']]);
    $order = $orderStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        throw new Exception('Payment order not found');
    }
    
    $amount = floatval($order['amount']);
    error_log("💰 Payment amount from order: ₹{$amount}");
    
    // Get Razorpay secret
    $stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = 'razorpay_key_secret'");
    $stmt->execute();
    $secret = $stmt->fetchColumn();
    
    if (!$secret) {
        throw new Exception('Razorpay secret not found');
    }
    
    // Verify signature
    $expectedSignature = hash_hmac('sha256', $orderId . '|' . $paymentId, $secret);
    
    if ($expectedSignature !== $signature) {
        error_log("❌ Signature mismatch - Expected: $expectedSignature, Got: $signature");
        throw new Exception('Invalid payment signature');
    }
    
    error_log("✅ Signature verified successfully");
    
    // Check if already enrolled
    $checkStmt = $db->prepare("SELECT id FROM enrollments WHERE user_id = ? AND course_id = ?");
    $checkStmt->execute([$user['id'], $courseId]);
    
    if ($checkStmt->rowCount() > 0) {
        error_log("⚠️ User already enrolled");
        echo json_encode([
            'success' => true,
            'message' => 'Already enrolled',
            'redirect' => getFirstTopicUrl($db, $courseId)
        ]);
        exit;
    }
    
    // ✅ Check which columns exist in enrollments table
    $columnsStmt = $db->query("SHOW COLUMNS FROM enrollments");
    $columns = $columnsStmt->fetchAll(PDO::FETCH_COLUMN);
    
    error_log("📋 Available columns: " . implode(', ', $columns));
    
    // Build dynamic INSERT based on available columns
    $insertColumns = ['user_id', 'course_id', 'enrolled_at'];
    $insertValues = [$user['id'], $courseId, date('Y-m-d H:i:s')];
    $insertPlaceholders = ['?', '?', '?'];
    
    // Add optional columns if they exist
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
        $insertValues[] = $paymentId;
        $insertPlaceholders[] = '?';
    }
    
    // ✅ CRITICAL: Add payment_method
    if (in_array('payment_method', $columns)) {
        $insertColumns[] = 'payment_method';
        $insertValues[] = 'razorpay';
        $insertPlaceholders[] = '?';
        error_log("✅ Adding payment_method = razorpay");
    }
    
    // ✅ CRITICAL: Add amount_paid
    if (in_array('amount_paid', $columns)) {
        $insertColumns[] = 'amount_paid';
        $insertValues[] = $amount;
        $insertPlaceholders[] = '?';
        error_log("✅ Adding amount_paid = {$amount}");
    }
    
    // ✅ Add coupon_id if provided
    if ($couponId && in_array('coupon_id', $columns)) {
        $insertColumns[] = 'coupon_id';
        $insertValues[] = $couponId;
        $insertPlaceholders[] = '?';
        error_log("✅ Adding coupon_id = {$couponId}");
    }
    
    // Create enrollment
    $sql = sprintf(
        "INSERT INTO enrollments (%s) VALUES (%s)",
        implode(', ', $insertColumns),
        implode(', ', $insertPlaceholders)
    );
    
    error_log("📝 SQL: $sql");
    error_log("📦 Values: " . json_encode($insertValues));
    
    $enrollStmt = $db->prepare($sql);
    
    if ($enrollStmt->execute($insertValues)) {
        error_log("✅ Enrollment created successfully");
        
        // Update course enrollment count
        try {
            $updateStmt = $db->prepare("UPDATE courses SET enrolled_count = COALESCE(enrolled_count, 0) + 1 WHERE id = ?");
            $updateStmt->execute([$courseId]);
            error_log("✅ Course count updated");
        } catch (Exception $e) {
            error_log("⚠️ Course count update warning: " . $e->getMessage());
        }
        
        // Update payment order status
        try {
            $paymentUpdateStmt = $db->prepare("UPDATE payment_orders SET razorpay_payment_id = ?, status = 'paid', updated_at = NOW() WHERE razorpay_order_id = ?");
            $paymentUpdateStmt->execute([$paymentId, $orderId]);
            error_log("✅ Payment order updated");
        } catch (Exception $e) {
            error_log("⚠️ Payment order update warning: " . $e->getMessage());
        }
        
        // Add coupon usage if provided
        if ($couponId) {
            try {
                // Update coupon used count
                $db->prepare("UPDATE coupons SET used_count = COALESCE(used_count, 0) + 1 WHERE id = ?")->execute([$couponId]);
                
                // Record usage
                $couponStmt = $db->prepare("INSERT INTO coupon_usage (user_id, coupon_id, course_id, used_at) VALUES (?, ?, ?, NOW())");
                $couponStmt->execute([$user['id'], $couponId, $courseId]);
                error_log("✅ Coupon usage recorded");
            } catch (Exception $e) {
                error_log("⚠️ Coupon usage warning: " . $e->getMessage());
            }
        }
        
        error_log("🎉 Enrollment completed successfully - Amount: ₹{$amount}");
        
        // ✅ CHANGED: Redirect to first topic instead of my-courses
        echo json_encode([
            'success' => true,
            'message' => 'Payment verified and enrolled successfully',
            'redirect' => getFirstTopicUrl($db, $courseId)
        ]);

    } else {
        throw new Exception('Failed to create enrollment');
    }
    
} catch (PDOException $e) {
    error_log("❌ Database error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => 'Database error: ' . $e->getMessage()
    ]);
    
} catch (Exception $e) {
    error_log("❌ Verification error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => $e->getMessage()
    ]);
}
?>
