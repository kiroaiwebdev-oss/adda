<?php
ini_set('session.cookie_path', '/');
ini_set('session.cookie_domain', 'internshipadda.com');
session_name('ai_studio_session');
session_start();
header('Content-Type: application/json');

echo json_encode([
    'session_id'      => session_id(),
    'studio_user_id'  => $_SESSION['studio_user_id'] ?? 'NOT FOUND',
    'all_session'     => $_SESSION,
    'cookie_received' => $_COOKIE,
]);