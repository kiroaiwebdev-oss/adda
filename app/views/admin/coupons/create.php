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
        $stmt = $db->prepare("
            INSERT INTO coupons 
            (code, discount_type, discount_value, min_purchase, max_discount, usage_limit, valid_until, created_by) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            strtoupper($_POST['code']),
            $_POST['discount_type'],
            floatval($_POST['discount_value']),
            floatval($_POST['min_purchase'] ?: 0),
            $_POST['max_discount'] ? floatval($_POST['max_discount']) : null,
            $_POST['usage_limit'] ? intval($_POST['usage_limit']) : null,
            $_POST['valid_until'] ?: null,
            $_SESSION['user_id']
        ]);
        
        header('Location: list.php?success=1');
        exit;
    } catch (Exception $e) {
        header('Location: list.php?error=' . urlencode($e->getMessage()));
        exit;
    }
}
