<?php
header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../app/config/database.php';

$dbConfig = require __DIR__ . '/../app/config/database.php';
$dsn = sprintf("mysql:host=%s;port=%s;dbname=%s;charset=%s",
    $dbConfig['host'], $dbConfig['port'], $dbConfig['database'], $dbConfig['charset']
);

$db = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options']);

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

$auth = new Auth($db);

if (!$auth->check()) {
    echo json_encode(['success' => false, 'error' => 'Please login first']);
    exit;
}

$userId = $auth->id();
$input = json_decode(file_get_contents('php://input'), true);
$couponCode = strtoupper(trim($input['coupon_code'] ?? ''));
$coursePrice = (float)($input['course_price'] ?? 0);

if (empty($couponCode)) {
    echo json_encode(['success' => false, 'error' => 'Please enter coupon code']);
    exit;
}

if ($coursePrice <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid course price']);
    exit;
}

try {
    // ✅ Check if it's a WELCOME signup discount code
    if (strpos($couponCode, 'WELCOME') === 0) {
        $stmt = $db->prepare("
            SELECT 
                r.id, 
                r.referral_code,
                rs.setting_value as discount_percent, 
                rs2.setting_value as max_discount
            FROM referrals r
            LEFT JOIN referral_settings rs ON rs.setting_key = 'signup_discount_percentage'
            LEFT JOIN referral_settings rs2 ON rs2.setting_key = 'max_discount_amount'
            WHERE r.referred_user_id = ? 
            AND r.signup_discount_applied = 0
        ");
        $stmt->execute([$userId]);
        $referral = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$referral) {
            echo json_encode(['success' => false, 'error' => 'This discount code is not valid for your account or already used']);
            exit;
        }
        
        // Verify the coupon code matches pattern
        $expectedCode = 'WELCOME' . strtoupper(substr($referral['referral_code'], 3, 6));
        if ($couponCode !== $expectedCode) {
            echo json_encode(['success' => false, 'error' => 'Invalid coupon code']);
            exit;
        }
        
        // Check if already purchased any course
        $purchaseCheck = $db->prepare("SELECT COUNT(*) FROM enrollments WHERE user_id = ?");
        $purchaseCheck->execute([$userId]);
        if ($purchaseCheck->fetchColumn() > 0) {
            echo json_encode(['success' => false, 'error' => 'Signup discount only valid for first purchase']);
            exit;
        }
        
        $discountPercent = (int)($referral['discount_percent'] ?? 40);
        $maxDiscount = (float)($referral['max_discount'] ?? 1000);
        
        $discountAmount = ($coursePrice * $discountPercent) / 100;
        $discountAmount = min($discountAmount, $maxDiscount);
        
        echo json_encode([
            'success' => true,
            'coupon_type' => 'signup_discount',
            'coupon_code' => $couponCode,
            'discount_amount' => $discountAmount,
            'discount_percent' => $discountPercent,
            'original_price' => $coursePrice,
            'final_price' => max(0, $coursePrice - $discountAmount),
            'message' => "🎉 {$discountPercent}% discount applied! You save ₹" . number_format($discountAmount, 2)
        ]);
        exit;
    }
    
    // Check regular coupons from coupons table
    $stmt = $db->prepare("
        SELECT * FROM coupons 
        WHERE code = ? 
        AND is_active = 1 
        AND (valid_until IS NULL OR valid_until >= NOW())
    ");
    $stmt->execute([$couponCode]);
    $coupon = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$coupon) {
        echo json_encode(['success' => false, 'error' => 'Invalid or expired coupon code']);
        exit;
    }
    
    // Check usage limit
    $usageStmt = $db->prepare("SELECT COUNT(*) FROM coupon_usage WHERE coupon_id = ?");
    $usageStmt->execute([$coupon['id']]);
    $usageCount = $usageStmt->fetchColumn();
    
    if ($coupon['usage_limit'] > 0 && $usageCount >= $coupon['usage_limit']) {
        echo json_encode(['success' => false, 'error' => 'This coupon has reached its usage limit']);
        exit;
    }
    
    // Calculate discount
    if ($coupon['discount_type'] === 'percentage') {
        $discountAmount = ($coursePrice * $coupon['discount_value']) / 100;
        if ($coupon['max_discount'] > 0) {
            $discountAmount = min($discountAmount, $coupon['max_discount']);
        }
    } else {
        $discountAmount = min($coupon['discount_value'], $coursePrice);
    }
    
    echo json_encode([
        'success' => true,
        'coupon_type' => 'regular',
        'coupon_id' => $coupon['id'],
        'coupon_code' => $couponCode,
        'discount_amount' => $discountAmount,
        'original_price' => $coursePrice,
        'final_price' => max(0, $coursePrice - $discountAmount),
        'message' => "✅ Coupon applied! You save ₹" . number_format($discountAmount, 2)
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Server error. Please try again.']);
}
