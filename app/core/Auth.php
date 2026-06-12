<?php
/**
 * Auth - Authentication Engine
 * Handles user authentication, sessions, and authorization
 */

class Auth {
    private $db;
    private $config;
    
    public function __construct($db) {
        $this->db = $db;
        $this->config = require __DIR__ . '/../config/app.php';
        
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.cookie_httponly', 1);
            ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) ? 1 : 0);
            ini_set('session.use_only_cookies', 1);
            session_start();
        }
    }
    
    /**
     * Login user
     */
    public function login($email, $password, $remember = false) {
        // Find user by email
        $stmt = $this->db->prepare("
            SELECT id, email, password, role, status, name 
            FROM users 
            WHERE email = ? 
            LIMIT 1
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if (!$user) {
            return ['success' => false, 'error' => 'Invalid credentials'];
        }
        
        // Check if account is active
        if ($user['status'] !== 'active') {
            return ['success' => false, 'error' => 'Account is suspended'];
        }
        
        // Verify password
        if (!password_verify($password, $user['password'])) {
            return ['success' => false, 'error' => 'Invalid credentials'];
        }
        
        // Create session
        $this->createSession($user);
        
        // Update last login
        $stmt = $this->db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $stmt->execute([$user['id']]);
        
        return ['success' => true, 'user' => $this->getUserData($user)];
    }
    
    /**
     * Logout user
     */
    public function logout() {
        $_SESSION = [];
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 3600, '/');
        }
        session_destroy();
    }
    
    /**
     * Check if user is authenticated
     */
    public function check() {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }
    
    /**
     * Get current authenticated user
     */
    public function user() {
        if (!$this->check()) {
            return null;
        }
        
        $stmt = $this->db->prepare("
            SELECT id, email, name, role, avatar, created_at 
            FROM users 
            WHERE id = ? AND status = 'active' 
            LIMIT 1
        ");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch();
    }
    
    /**
     * Check if user has specific role
     */
    public function hasRole($role) {
        $user = $this->user();
        return $user && $user['role'] === $role;
    }
    
    /**
     * Check if current user is admin
     */
    public function isAdmin() {
        return $this->hasRole('admin');
    }
    
    /**
     * Check if current user is learner
     */
    public function isLearner() {
        return $this->hasRole('learner');
    }
    
    /**
     * Get user ID
     */
    public function id() {
        return $_SESSION['user_id'] ?? null;
    }
    
    /**
     * Create user session
     */
    private function createSession($user) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['logged_in_at'] = time();
    }
    
    /**
     * Get sanitized user data
     */
    private function getUserData($user) {
        return [
            'id' => $user['id'],
            'email' => $user['email'],
            'name' => $user['name'],
            'role' => $user['role']
        ];
    }
    
    /**
     * Require authentication - redirect if not logged in
     */
    public function requireAuth($redirectTo = '/login.php') {
        if (!$this->check()) {
            header('Location: ' . $redirectTo);
            exit;
        }
    }
    
    /**
     * Require admin role
     */
    public function requireAdmin() {
        $this->requireAuth();
        if (!$this->isAdmin()) {
            http_response_code(403);
            die('Access denied');
        }
    }
    
    /**
     * Require learner role
     */
    public function requireLearner() {
        $this->requireAuth();
        if (!$this->isLearner()) {
            http_response_code(403);
            die('Access denied');
        }
    }
}
