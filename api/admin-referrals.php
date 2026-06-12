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

if (!$auth->check() || !$auth->isAdmin()) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? '';
$referralModel = new Referral($db);

// ✅ GET ALL REFERRALS
if ($action === 'list' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $limit = (int)($_GET['limit'] ?? 100);
        $offset = (int)($_GET['offset'] ?? 0);
        
        $referrals = $referralModel->getAllReferrals($limit, $offset);
        
        echo json_encode([
            'success' => true,
            'referrals' => $referrals
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ✅ GET REFERRAL COUPONS
if ($action === 'coupons' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $limit = (int)($_GET['limit'] ?? 100);
        $coupons = $referralModel->getReferralCoupons($limit);
        
        echo json_encode([
            'success' => true,
            'coupons' => $coupons
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ✅ GET REFERRAL SETTINGS
if ($action === 'settings' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $settings = $referralModel->getSettings();
        
        echo json_encode([
            'success' => true,
            'settings' => $settings
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ✅ UPDATE SETTINGS
if ($action === 'update-settings' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        foreach ($input as $key => $value) {
            $referralModel->updateSetting($key, $value);
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Settings updated successfully'
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ✅ GET STATS
if ($action === 'stats' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $stats = [
            'total_referrals' => $db->query("SELECT COUNT(*) FROM referrals")->fetchColumn(),
            'completed_referrals' => $db->query("SELECT COUNT(*) FROM referrals WHERE status = 'completed'")->fetchColumn(),
            'pending_referrals' => $db->query("SELECT COUNT(*) FROM referrals WHERE status = 'pending'")->fetchColumn(),
            'total_points_awarded' => $db->query("SELECT COALESCE(SUM(points_earned), 0) FROM referral_earnings WHERE transaction_type = 'credit'")->fetchColumn(),
            'total_points_redeemed' => $db->query("SELECT COALESCE(SUM(points_earned), 0) FROM referral_earnings WHERE transaction_type = 'debit'")->fetchColumn(),
            'total_coupons_generated' => $db->query("SELECT COUNT(*) FROM coupons WHERE is_referral_coupon = 1")->fetchColumn(),
            'coupons_used' => $db->query("SELECT COUNT(DISTINCT coupon_id) FROM coupon_usage cu INNER JOIN coupons c ON cu.coupon_id = c.id WHERE c.is_referral_coupon = 1")->fetchColumn(),
        ];
        
        echo json_encode([
            'success' => true,
            'stats' => $stats
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'error' => 'Invalid action']);
