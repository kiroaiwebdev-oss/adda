<?php
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'message' => 'API is working!',
    'session' => $_SESSION ?? [],
    'get' => $_GET,
    'post' => file_get_contents('php://input')
]);
