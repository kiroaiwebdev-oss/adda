<?php
/**
 * Application Routes
 * Define all application routes here
 */

// Create router instance
$router = new Router();

// ============================================
// Public Routes
// ============================================

$router->get('/', function() {
    header('Location: /public/index.html');
    exit;
});

$router->get('/login.php', function() {
    include __DIR__ . '/views/public/login.php';
});

$router->get('/signup.php', function() {
    include __DIR__ . '/views/public/signup.php';
});

// ============================================
// Admin Routes - Banners
// ============================================

$router->get('/admin/banners', function() {
    require_once __DIR__ . '/views/admin/banners/list.php';
});

$router->get('/admin/banners/manage', function() {
    require_once __DIR__ . '/views/admin/banners/list.php';
});

// ============================================
// API Routes
// ============================================

$router->post('/api/auth/login', function() {
    echo json_encode(['message' => 'Auth API - Coming in Phase 2']);
});

$router->post('/api/auth/register', function() {
    echo json_encode(['message' => 'Auth API - Coming in Phase 2']);
});

// ============================================
// Dispatch Router
// ============================================

$router->dispatch();
