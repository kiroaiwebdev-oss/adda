<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../app/config/database.php';

function getFirstLessonUrl($db, $internshipId) {
    try {
        error_log("🔍 Generating redirect URL for internship: $internshipId");
        $redirectUrl = "/app/views/learner/internship-player.php?id={$internshipId}";
        error_log("🚀 Redirect URL: $redirectUrl");
        return $redirectUrl;
    } catch (Exception $e) {
        error_log("❌ Error: " . $e->getMessage());
        return '/app/views/learner/my-internships.php';
    }
}

$dbConfig = require __DIR__ . '/../app/config/database.php';
$dsn = sprintf("mysql:host=%s;port=%s;dbname=%s;charset=%s",
    $dbConfig['host'], $dbConfig['port'], $dbConfig['database'], $dbConfig['charset']
);

try {
    $db = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options']);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die(json_encode(['success' => false, 'error' => 'Database connection failed']));
}

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
    die(json_encode(['success' => false, 'error' => 'Please login first']));
}

$userId = $auth->id();
$action = $_GET['action'] ?? '';

error_log("=== INTERNSHIP ENROLLMENT API ===");
error_log("Action: $action, User ID: $userId");

if ($action === 'enroll' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $internshipId = filter_var($_POST['internship_id'] ?? 0, FILTER_VALIDATE_INT);
        $couponId = filter_var($_POST['coupon_id'] ?? null, FILTER_VALIDATE_INT);
        
        if (!$internshipId) throw new Exception('Invalid internship ID');
        
        error_log("💳 Enrollment - Internship: $internshipId, User: $userId");
        
        $stmt = $db->prepare("SELECT * FROM internships WHERE id = ? AND is_active = 1");
        $stmt->execute([$internshipId]);
        $internship = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$internship) throw new Exception('Internship not found');
        
        $checkStmt = $db->prepare("SELECT id FROM internship_enrollments WHERE user_id = ? AND internship_id = ?");
        $checkStmt->execute([$userId, $internshipId]);
        if ($checkStmt->fetch()) {
            error_log("⚠️ Already enrolled");
            $redirectUrl = getFirstLessonUrl($db, $internshipId);
            echo json_encode(['success' => false, 'error' => 'You have already applied for this internship', 'redirect' => $redirectUrl]);
            exit;
        }
        
        $price = floatval($internship['price'] ?? 0);
        $discountPrice = floatval($internship['discount_price'] ?? 0);
        $currentPrice = ($discountPrice > 0 && $discountPrice < $price) ? $discountPrice : $price;
        $isFree = $currentPrice == 0;
        
        error_log("💰 Price - Original: $price, Current: $currentPrice, Free: " . ($isFree ? 'YES' : 'NO'));
        
        // ✅ FREE INTERNSHIP
        if ($isFree) {
            error_log("✅ FREE internship");
            $db->beginTransaction();
            try {
                $insertStmt = $db->prepare("
                    INSERT INTO internship_enrollments 
                    (user_id, internship_id, payment_status, status, created_at) 
                    VALUES (?, ?, 'completed', 'applied', NOW())
                ");
                $insertStmt->execute([$userId, $internshipId]);
                
                try {
                    $db->prepare("UPDATE internships SET total_applications = total_applications + 1 WHERE id = ?")->execute([$internshipId]);
                } catch (Exception $e) { error_log('Stats: ' . $e->getMessage()); }
                
                $db->commit();
                
                // ✅ SEND OFFER LETTER EMAIL
                try {
                    $emailService = new EmailService($db);
                    $emailResult = $emailService->sendInternshipOfferLetter($userId, $internshipId);
                    error_log("📧 Offer letter email: " . ($emailResult['success'] ? '✅ Sent' : '❌ Failed - ' . ($emailResult['error'] ?? '')));
                } catch (Exception $emailEx) {
                    error_log("📧 Offer letter exception: " . $emailEx->getMessage());
                    // Email failure nahi rokegi enrollment success ko
                }
                
                $redirectUrl = getFirstLessonUrl($db, $internshipId);
                error_log("🎉 FREE enrollment success! Redirect: $redirectUrl");
                
                echo json_encode([
                    'success' => true,
                    'message' => '✅ Application submitted successfully!',
                    'redirect' => $redirectUrl
                ]);
                exit;
                
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
        }
        
        // ✅ PAID INTERNSHIP
        error_log("💳 PAID internship");
        
        $razorpayPaymentId = $_POST['razorpay_payment_id'] ?? null;
        $razorpayOrderId   = $_POST['razorpay_order_id'] ?? null;
        $razorpaySignature = $_POST['razorpay_signature'] ?? null;
        $amount            = floatval($_POST['amount'] ?? 0);
        
        if (!$razorpayPaymentId || !$razorpayOrderId || !$razorpaySignature) {
            throw new Exception('Payment verification failed: Missing payment details');
        }
        
        error_log("🔍 Payment - Order: $razorpayOrderId, Amount: $amount");
        
        $settingsModel = new Settings($db);
        $keySecret = $settingsModel->get('razorpay_key_secret', '');
        
        if (empty($keySecret)) throw new Exception('Payment gateway not configured');
        
        $generatedSignature = hash_hmac('sha256', $razorpayOrderId . '|' . $razorpayPaymentId, $keySecret);
        
        if ($generatedSignature !== $razorpaySignature) {
            error_log('❌ Signature mismatch');
            throw new Exception('Payment verification failed: Invalid signature');
        }
        
        error_log("✅ Payment verified");
        
        $db->beginTransaction();
        try {
            $insertStmt = $db->prepare("
                INSERT INTO internship_enrollments 
                (user_id, internship_id, payment_id, payment_amount, payment_status, status, created_at) 
                VALUES (?, ?, ?, ?, 'completed', 'applied', NOW())
            ");
            $insertStmt->execute([$userId, $internshipId, $razorpayPaymentId, $amount]);
            
            if ($couponId) {
                try {
                    $db->prepare("INSERT INTO coupon_usage (coupon_id, user_id, order_type, order_id, used_at) VALUES (?, ?, 'internship', ?, NOW())")->execute([$couponId, $userId, $internshipId]);
                    $db->prepare("UPDATE coupons SET usage_count = usage_count + 1 WHERE id = ?")->execute([$couponId]);
                } catch (Exception $e) { error_log('Coupon: ' . $e->getMessage()); }
            }
            
            try {
                $db->prepare("UPDATE internships SET total_applications = total_applications + 1 WHERE id = ?")->execute([$internshipId]);
            } catch (Exception $e) { error_log('Stats: ' . $e->getMessage()); }
            
            try {
                $db->prepare("UPDATE payment_orders SET status = 'completed', razorpay_payment_id = ?, completed_at = NOW() WHERE razorpay_order_id = ?")->execute([$razorpayPaymentId, $razorpayOrderId]);
            } catch (Exception $e) { error_log('Order: ' . $e->getMessage()); }

            // ✅ Award referral points on first paid purchase
            try {
                $countStmt = $db->prepare("
                    SELECT
                        (SELECT COUNT(*) FROM enrollments WHERE user_id = ? AND status IN ('active','completed')) +
                        (SELECT COUNT(*) FROM internship_enrollments WHERE user_id = ? AND payment_status = 'completed') AS total
                ");
                $countStmt->execute([$userId, $userId]);
                $totalPurchases = (int) $countStmt->fetchColumn();

                if ($totalPurchases <= 1) {
                    $refStmt = $db->prepare("
                        SELECT id, referrer_user_id, referral_code
                        FROM referrals
                        WHERE referred_user_id = ? AND status = 'pending'
                        LIMIT 1
                    ");
                    $refStmt->execute([$userId]);
                    $pendingRef = $refStmt->fetch(PDO::FETCH_ASSOC);

                    if ($pendingRef) {
                        $pointsToAward = 100;
                        try {
                            $pStmt = $db->prepare("SELECT setting_value FROM referral_settings WHERE setting_key = 'points_per_referral'");
                            $pStmt->execute();
                            $val = $pStmt->fetchColumn();
                            if ($val !== false && $val !== null) {
                                $pointsToAward = (int) $val ?: 100;
                            }
                        } catch (Exception $e) { /* defaults */ }

                        $db->prepare("UPDATE referrals SET status = 'completed', first_purchase_date = NOW() WHERE id = ?")
                           ->execute([$pendingRef['id']]);

                        $db->prepare("
                            INSERT INTO referral_earnings
                                (user_id, referral_id, points_earned, points_type, transaction_type, description)
                            VALUES (?, ?, ?, 'purchase_bonus', 'credit', ?)
                        ")->execute([
                            $pendingRef['referrer_user_id'],
                            $pendingRef['id'],
                            $pointsToAward,
                            'Referral purchase bonus (internship)'
                        ]);

                        error_log("🎁 Referral completed: referrer={$pendingRef['referrer_user_id']} user={$userId} points={$pointsToAward}");
                    }
                }
            } catch (Exception $e) {
                error_log('Referral award skipped: ' . $e->getMessage());
            }

            $db->commit();
            
            // ✅ SEND OFFER LETTER EMAIL
            try {
                $emailService = new EmailService($db);
                $emailResult = $emailService->sendInternshipOfferLetter($userId, $internshipId);
                error_log("📧 Offer letter email: " . ($emailResult['success'] ? '✅ Sent' : '❌ Failed - ' . ($emailResult['error'] ?? '')));
            } catch (Exception $emailEx) {
                error_log("📧 Offer letter exception: " . $emailEx->getMessage());
            }
            
            $redirectUrl = getFirstLessonUrl($db, $internshipId);
            error_log("🎉 PAID enrollment success! Redirect: $redirectUrl");
            
            echo json_encode([
                'success' => true,
                'message' => '🎉 Payment successful! Application submitted!',
                'redirect' => $redirectUrl
            ]);
            exit;
            
        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }
        
    } catch (Exception $e) {
        error_log('❌ Error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

echo json_encode(['success' => false, 'error' => 'Invalid request']);
?>