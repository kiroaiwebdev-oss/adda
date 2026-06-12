<?php
session_start();
require_once __DIR__ . '/../../../config/database.php';

$dbConfig = require __DIR__ . '/../../../config/database.php';
$dsn = sprintf("mysql:host=%s;port=%s;dbname=%s;charset=%s",
    $dbConfig['host'], $dbConfig['port'], $dbConfig['database'], $dbConfig['charset']
);
$db = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options']);

spl_autoload_register(function ($class) {
    $paths = [
        __DIR__ . '/../../../core/' . $class . '.php',
        __DIR__ . '/../../../models/' . $class . '.php',
    ];
    foreach ($paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            return;
        }
    }
});

$auth = new Auth($db);
$auth->requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $couponId = (int)($_POST['coupon_id'] ?? 0);
        $code = strtoupper(trim($_POST['code'] ?? ''));
        $discountType = $_POST['discount_type'] ?? 'percentage';
        $discountValue = (float)($_POST['discount_value'] ?? 0);
        $minPurchase = (float)($_POST['min_purchase'] ?? 0);
        $maxDiscount = $discountType === 'percentage' ? ((float)($_POST['max_discount'] ?? 0)) : null;
        $usageLimit = (int)($_POST['usage_limit'] ?? 0);
        $validUntil = !empty($_POST['valid_until']) ? $_POST['valid_until'] : null;
        
        if (empty($code) || $discountValue <= 0 || $couponId <= 0) {
            throw new Exception('Invalid coupon data');
        }
        
        // Check if code already exists (excluding current coupon)
        $checkStmt = $db->prepare("SELECT id FROM coupons WHERE code = ? AND id != ?");
        $checkStmt->execute([$code, $couponId]);
        if ($checkStmt->fetch()) {
            throw new Exception('Coupon code already exists');
        }
        
        $stmt = $db->prepare("
            UPDATE coupons SET
                code = ?,
                discount_type = ?,
                discount_value = ?,
                min_purchase = ?,
                max_discount = ?,
                usage_limit = ?,
                valid_until = ?
            WHERE id = ?
        ");
        
        $stmt->execute([
            $code,
            $discountType,
            $discountValue,
            $minPurchase,
            $maxDiscount,
            $usageLimit,
            $validUntil,
            $couponId
        ]);
        
        header('Location: list.php?success=1');
        exit;
        
    } catch (Exception $e) {
        header('Location: list.php?error=' . urlencode($e->getMessage()));
        exit;
    }
} else {
    header('Location: list.php');
    exit;
}
