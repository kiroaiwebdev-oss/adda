<?php
error_reporting(E_ALL);
ini_set('display_errors', 0); ini_set('log_errors', 1);

session_start();
require_once __DIR__ . '/../app/config/database.php';

$dbConfig = require __DIR__ . '/../app/config/database.php';
$dsn = sprintf("mysql:host=%s;port=%s;dbname=%s;charset=%s",
    $dbConfig['host'], $dbConfig['port'], $dbConfig['database'], $dbConfig['charset']
);

try {
    $db = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options']);
} catch (PDOException $e) {
    die(json_encode(['success' => false, 'error' => 'Database connection failed']));
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

header('Content-Type: application/json');

// Check authentication
$auth = new Auth($db);
if (!$auth->check()) {
    echo json_encode(['success' => false, 'error' => 'Please login to apply']);
    exit;
}

$userId = $auth->id();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

$internshipId = intval($_POST['internship_id'] ?? 0);
$couponId = intval($_POST['coupon_id'] ?? 0);

if (!$internshipId) {
    echo json_encode(['success' => false, 'error' => 'Internship ID is required']);
    exit;
}

try {
    // Check if internship exists and is active
    $stmt = $db->prepare("SELECT * FROM internships WHERE id = ? AND is_active = 1");
    $stmt->execute([$internshipId]);
    $internship = $stmt->fetch();
    
    if (!$internship) {
        echo json_encode(['success' => false, 'error' => 'Internship not found']);
        exit;
    }
    
    // Check if already applied
    $checkStmt = $db->prepare("SELECT id FROM internship_enrollments WHERE user_id = ? AND internship_id = ?");
    $checkStmt->execute([$userId, $internshipId]);
    
    if ($checkStmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'You have already applied for this internship']);
        exit;
    }
    
    // Calculate final amount
    $originalPrice = floatval($internship['price'] ?? 0);
    $finalAmount = $originalPrice;
    $couponDiscount = 0;
    
    // Apply coupon if provided
    if ($couponId > 0) {
        $couponStmt = $db->prepare("
            SELECT * FROM coupons 
            WHERE id = ? 
            AND is_active = 1 
            AND (valid_until IS NULL OR valid_until >= NOW())
        ");
        $couponStmt->execute([$couponId]);
        $coupon = $couponStmt->fetch();
        
        if ($coupon) {
            if ($coupon['discount_type'] === 'percentage') {
                $couponDiscount = ($originalPrice * $coupon['discount_value']) / 100;
                
                if ($coupon['max_discount'] > 0 && $couponDiscount > $coupon['max_discount']) {
                    $couponDiscount = $coupon['max_discount'];
                }
            } else {
                $couponDiscount = $coupon['discount_value'];
            }
            
            if ($couponDiscount > $originalPrice) {
                $couponDiscount = $originalPrice;
            }
            
            $finalAmount = $originalPrice - $couponDiscount;
            
            // Update coupon usage count (if column exists)
            try {
                $updateCoupon = $db->prepare("UPDATE coupons SET usage_count = usage_count + 1 WHERE id = ?");
                $updateCoupon->execute([$couponId]);
            } catch (PDOException $e) {
                // Column might not exist, ignore
            }
            
            // Record coupon usage
            try {
                $usageStmt = $db->prepare("INSERT INTO coupon_usage (coupon_id, user_id, course_id) VALUES (?, ?, NULL)");
                $usageStmt->execute([$couponId, $userId]);
            } catch (PDOException $e) {
                // Table might not exist, ignore
            }
        }
    }
    
    // Insert enrollment
    $insertStmt = $db->prepare("
        INSERT INTO internship_enrollments 
        (user_id, internship_id, status, coupon_id, coupon_discount, final_amount) 
        VALUES (?, ?, 'applied', ?, ?, ?)
    ");
    
    $insertStmt->execute([
        $userId,
        $internshipId,
        $couponId > 0 ? $couponId : null,
        $couponDiscount,
        $finalAmount
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Application submitted successfully',
        'enrollment_id' => $db->lastInsertId()
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>
