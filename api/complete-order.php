<?php
/**
 * Complete Order and Award Referral Points
 * Call this after successful payment
 */
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

// This function should be called after order completion
function completeOrderAndAwardPoints($db, $orderId) {
    try {
        $db->beginTransaction();
        
        // Get order details
        $stmt = $db->prepare("SELECT user_id, course_id, amount FROM orders WHERE id = ? AND status = 'completed'");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$order) {
            $db->rollBack();
            return false;
        }
        
        $userId = $order['user_id'];
        
        // Check if this is user's first purchase
        $firstPurchaseStmt = $db->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ? AND status = 'completed'");
        $firstPurchaseStmt->execute([$userId]);
        $purchaseCount = $firstPurchaseStmt->fetchColumn();
        
        if ($purchaseCount == 1) { // First purchase
            // Check if user was referred
            $referralStmt = $db->prepare("
                SELECT id, referrer_user_id, referral_code 
                FROM referrals 
                WHERE referred_user_id = ? AND status = 'pending'
            ");
            $referralStmt->execute([$userId]);
            $referral = $referralStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($referral) {
                // Get points setting
                $pointsStmt = $db->prepare("
                    SELECT setting_value FROM referral_settings WHERE setting_key = 'points_per_referral'
                ");
                $pointsStmt->execute();
                $pointsToAward = (int)($pointsStmt->fetchColumn() ?: 100);
                
                // Update referral status
                $updateStmt = $db->prepare("
                    UPDATE referrals 
                    SET status = 'completed', first_purchase_date = NOW() 
                    WHERE id = ?
                ");
                $updateStmt->execute([$referral['id']]);
                
                // Award points
                $earningStmt = $db->prepare("
                    INSERT INTO referral_earnings 
                    (user_id, referral_id, points_earned, points_type, transaction_type, description, order_id)
                    VALUES (?, ?, ?, 'purchase_bonus', 'credit', ?, ?)
                ");
                $earningStmt->execute([
                    $referral['referrer_user_id'],
                    $referral['id'],
                    $pointsToAward,
                    "Referral purchase bonus for order #{$orderId}",
                    $orderId
                ]);
                
                // Mark order as referral purchase
                $markOrderStmt = $db->prepare("
                    UPDATE orders 
                    SET is_referral_purchase = 1, referred_by_user_id = ? 
                    WHERE id = ?
                ");
                $markOrderStmt->execute([$referral['referrer_user_id'], $orderId]);
            }
        }
        
        $db->commit();
        return true;
        
    } catch (Exception $e) {
        $db->rollBack();
        error_log("Complete order error: " . $e->getMessage());
        return false;
    }
}

// Example usage (call this after payment success):
// completeOrderAndAwardPoints($db, $orderId);
