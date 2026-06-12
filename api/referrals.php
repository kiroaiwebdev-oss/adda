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
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$userId = $auth->id();
$action = $_GET['action'] ?? '';
$referralModel = new Referral($db);

// ✅ GET USER REFERRAL DASHBOARD DATA
if ($action === 'dashboard' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $referralCode = $referralModel->getUserReferralCode($userId);
        $totalPoints = $referralModel->getUserPoints($userId);
        $stats = $referralModel->getUserReferralStats($userId);
        $settings = $referralModel->getSettings();
        
        $referralLink = 'https://' . $_SERVER['HTTP_HOST'] . '/public/signup.php?ref=' . $referralCode;
        
        echo json_encode([
            'success' => true,
            'data' => [
                'referral_code' => $referralCode,
                'referral_link' => $referralLink,
                'total_points' => $totalPoints,
                'points_value' => $totalPoints * (float)($settings['point_value_in_rupees'] ?? 1),
                'stats' => $stats,
                'settings' => [
                    'points_per_referral' => (int)($settings['points_per_referral'] ?? 100),
                    'signup_discount' => (int)($settings['signup_discount_percentage'] ?? 40),
                    'min_redemption' => (int)($settings['min_points_for_redemption'] ?? 50),
                    'point_value' => (float)($settings['point_value_in_rupees'] ?? 1)
                ]
            ]
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ✅ GET USER REFERRALS LIST
if ($action === 'list' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $limit = (int)($_GET['limit'] ?? 50);
        $offset = (int)($_GET['offset'] ?? 0);
        
        $referrals = $referralModel->getUserReferrals($userId, $limit, $offset);
        
        echo json_encode([
            'success' => true,
            'referrals' => $referrals
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ✅ GET EARNINGS HISTORY
if ($action === 'earnings' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $limit = (int)($_GET['limit'] ?? 50);
        $earnings = $referralModel->getEarningsHistory($userId, $limit);
        
        echo json_encode([
            'success' => true,
            'earnings' => $earnings
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ✅ REDEEM POINTS
if ($action === 'redeem' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        $pointsToRedeem = (int)($input['points'] ?? 0);
        
        if ($pointsToRedeem <= 0) {
            throw new Exception('Invalid points amount');
        }
        
        $result = $referralModel->redeemPoints($userId, $pointsToRedeem);
        
        echo json_encode([
            'success' => true,
            'message' => 'Points redeemed successfully!',
            'coupon' => $result
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ✅ GET MY COUPONS (NEW)
if ($action === 'my-coupons' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $stmt = $db->prepare("
            SELECT 
                c.*,
                cu.used_at,
                cu.order_id as used_order_id,
                CASE 
                    WHEN cu.id IS NOT NULL THEN 1
                    ELSE 0
                END as used_count
            FROM coupons c
            LEFT JOIN coupon_usage cu ON cu.coupon_id = c.id
            WHERE c.is_referral_coupon = 1 
            AND c.generated_by_user_id = ?
            ORDER BY c.created_at DESC
        ");
        $stmt->execute([$userId]);
        $coupons = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'coupons' => $coupons
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'error' => 'Invalid action']);
