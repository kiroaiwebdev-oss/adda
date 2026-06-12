<?php
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'message' => 'File is working!',
    'php_version' => PHP_VERSION
]);