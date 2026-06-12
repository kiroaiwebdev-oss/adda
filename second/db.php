<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('DB_HOST', 'localhost');
define('DB_NAME', 'u761369285_lms');
define('DB_USER', 'u761369285_lm');
define('DB_PASS', '~sQLn+Kb*5');

// ── getDB() function — manager panel ke liye ─────────────────────────────
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
    }
    return $pdo;
}

// ── $db variable — course_content_editor.php ke liye ─────────────────────
try {
    $db = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    die('<div style="font-family:sans-serif;padding:2rem;color:#dc2626;
        background:#fef2f2;border:1px solid #fecaca;border-radius:8px;margin:2rem">
        <strong>❌ DB Connection Failed</strong><br><br>'
        . htmlspecialchars($e->getMessage()) .
    '</div>');
}