<?php
header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../app/config/database.php';

$dbConfig = require __DIR__ . '/../app/config/database.php';
$dsn = sprintf("mysql:host=%s;port=%s;dbname=%s;charset=%s",
    $dbConfig['host'], $dbConfig['port'], $dbConfig['database'], $dbConfig['charset']
);

try {
    $db = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options']);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

$action = $_GET['action'] ?? '';

// Validate Coupon (PUBLIC - No login required)
if ($action === 'validate' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Support both JSON and form-data
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        
        if (strpos($contentType, 'application/json') !== false) {
            $input = json_decode(file_get_contents('php://input'), true);
            $couponCode = strtoupper(trim($input['coupon_code'] ?? ''));
            $courseId = (int)($input['course_id'] ?? 0);
            $internshipId = (int)($input['internship_id'] ?? 0);
            $amount = (float)($input['amount'] ?? 0);
        } else {
            // Form data (for internships)
            $couponCode = strtoupper(trim($_POST['code'] ?? ''));
            $courseId = (int)($_POST['course_id'] ?? 0);
            $internshipId = (int)($_POST['internship_id'] ?? 0);
            $amount = (float)($_POST['amount'] ?? 0);
        }
        
        if (empty($couponCode)) {
            throw new Exception('Coupon code is required');
        }
        
        // Get coupon details
        $stmt = $db->prepare("
            SELECT * FROM coupons 
            WHERE code = ? AND is_active = 1
            AND (valid_until IS NULL OR valid_until >= NOW())
        ");
        $stmt->execute([$couponCode]);
        $coupon = $stmt->fetch();
        
        if (!$coupon) {
            throw new Exception('Invalid or expired coupon code');
        }
        
        // Check usage limit
        $usageStmt = $db->prepare("SELECT COUNT(*) FROM coupon_usage WHERE coupon_id = ?");
        $usageStmt->execute([$coupon['id']]);
        $usageCount = $usageStmt->fetchColumn();
        
        if ($coupon['usage_limit'] > 0 && $usageCount >= $coupon['usage_limit']) {
            throw new Exception('Coupon usage limit reached');
        }
        
        $originalPrice = 0;
        
        // ✅ FIXED: For courses - Use discount_price if available, else price
        if ($courseId > 0) {
            $courseStmt = $db->prepare("SELECT price, discount_price FROM courses WHERE id = ?");
            $courseStmt->execute([$courseId]);
            $course = $courseStmt->fetch();
            
            if (!$course) {
                throw new Exception('Course not found');
            }
            
            // ✅ KEY FIX: Use discount_price if set, otherwise use regular price
            if (!empty($course['discount_price']) && $course['discount_price'] > 0) {
                $originalPrice = (float)$course['discount_price'];
            } else {
                $originalPrice = (float)$course['price'];
            }
        }
        // ✅ FIXED: For internships - Use discount_price if available, else price
        elseif ($internshipId > 0) {
            $internshipStmt = $db->prepare("SELECT price, discount_price FROM internships WHERE id = ?");
            $internshipStmt->execute([$internshipId]);
            $internship = $internshipStmt->fetch();
            
            if (!$internship) {
                throw new Exception('Internship not found');
            }
            
            // ✅ KEY FIX: Use discount_price if set, otherwise use regular price
            if (!empty($internship['discount_price']) && $internship['discount_price'] > 0) {
                $originalPrice = (float)$internship['discount_price'];
            } else {
                $originalPrice = (float)$internship['price'];
            }
        }
        // Direct amount (fallback)
        elseif ($amount > 0) {
            $originalPrice = $amount;
        }
        else {
            throw new Exception('Course ID, Internship ID, or amount required');
        }
        
        // Check minimum purchase
        if ($coupon['min_purchase'] > 0 && $originalPrice < $coupon['min_purchase']) {
            throw new Exception('Minimum purchase amount not met (₹' . number_format($coupon['min_purchase'], 2) . ' required)');
        }
        
        // Calculate discount
        $discount = 0;
        if ($coupon['discount_type'] === 'percentage') {
            $discount = ($originalPrice * $coupon['discount_value']) / 100;
            // Apply max discount limit if set
            if ($coupon['max_discount'] > 0 && $discount > $coupon['max_discount']) {
                $discount = $coupon['max_discount'];
            }
        } else {
            // Fixed discount
            $discount = min($coupon['discount_value'], $originalPrice);
        }
        
        $finalPrice = max(0, $originalPrice - $discount);
        
        echo json_encode([
            'success' => true,
            'coupon' => [
                'id' => $coupon['id'],
                'code' => $coupon['code'],
                'discount_type' => $coupon['discount_type'],
                'discount_value' => $coupon['discount_value']
            ],
            'originalAmount' => $originalPrice,
            'original_price' => $originalPrice, // Backward compatibility
            'discount' => $discount,
            'finalAmount' => $finalPrice,
            'final_price' => $finalPrice, // Backward compatibility
            'message' => 'Coupon applied successfully! You saved ₹' . number_format($discount, 2)
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'error' => 'Invalid action']);
?>
