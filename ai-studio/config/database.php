<?php
/**
 * AI Studio — Database Config
 * Database: u761369285_ai_studio
 */

define('AI_DB_HOST', 'localhost');
define('AI_DB_NAME', 'u761369285_ai_studio');
define('AI_DB_USER', 'u761369285_ai');
define('AI_DB_PASS', 'FzMlt>s@6#');

// LMS Database (for saving courses)
define('LMS_DB_HOST', 'localhost');
define('LMS_DB_NAME', 'u761369285_lms');
define('LMS_DB_USER', 'u761369285_lm');
define('LMS_DB_PASS', '~sQLn+Kb*5');

function getAIDb() {
    static $db = null;
    if ($db) return $db;
    try {
        $db = new PDO(
            "mysql:host=" . AI_DB_HOST . ";dbname=" . AI_DB_NAME . ";charset=utf8mb4",
            AI_DB_USER, AI_DB_PASS,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]
        );
    } catch (PDOException $e) {
        http_response_code(500);
        die(json_encode(['success' => false, 'message' => 'AI DB Error: ' . $e->getMessage()]));
    }
    return $db;
}

function getLMSDb() {
    static $lmsDb = null;
    if ($lmsDb) return $lmsDb;
    try {
        $lmsDb = new PDO(
            "mysql:host=" . LMS_DB_HOST . ";dbname=" . LMS_DB_NAME . ";charset=utf8mb4",
            LMS_DB_USER, LMS_DB_PASS,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]
        );
    } catch (PDOException $e) {
        http_response_code(500);
        die(json_encode(['success' => false, 'message' => 'LMS DB Error: ' . $e->getMessage()]));
    }
    return $lmsDb;
}