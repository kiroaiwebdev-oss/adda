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
        $couponId = intval($_POST['coupon_id']);
        
        // Delete usage records first
        $stmt = $db->prepare("DELETE FROM coupon_usage WHERE coupon_id = ?");
        $stmt->execute([$couponId]);
        
        // Delete coupon
        $stmt = $db->prepare("DELETE FROM coupons WHERE id = ?");
        $stmt->execute([$couponId]);
        
        header('Location: list.php?success=1');
        exit;
    } catch (Exception $e) {
        header('Location: list.php?error=' . urlencode($e->getMessage()));
        exit;
    }
}
