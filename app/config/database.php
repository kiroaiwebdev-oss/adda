<?php
/**
 * Database Configuration
 * MySQL connection using PDO
 */

// Configuration array (compatible with old system)
$dbConfig = [
    'host' => 'localhost',
    'port' => '3306',
    'database' => 'u761369285_lms',
    'username' => 'u761369285_lm',
    'password' => '~sQLn+Kb*5',
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
];

// Create DSN
$dsn = sprintf(
    "mysql:host=%s;port=%s;dbname=%s;charset=%s",
    $dbConfig['host'],
    $dbConfig['port'],
    $dbConfig['database'],
    $dbConfig['charset']
);

// Create PDO connection
try {
    $db = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options']);
} catch (PDOException $e) {
    error_log("Database Connection Error: " . $e->getMessage());
    die("Database connection failed. Please contact administrator.");
}

// Return config for compatibility with old code
return $dbConfig;
