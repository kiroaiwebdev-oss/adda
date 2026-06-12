<?php
/**
 * AuthController - Handles authentication logic
 */

class AuthController {
    private $db;
    private $auth;
    
    public function __construct($db) {
        $this->db = $db;
        $this->auth = new Auth($db);
    }
    
    /**
     * Register new user
     */
    public function register($data) {
        // Validate input
        $validator = new Validator($data);
        $validator->validate([
            'name' => 'required|min:2|max:255',
            'email' => 'required|email',
            'mobile' => 'required',
            'password' => 'required|min:6'
        ]);
        
        if ($validator->fails()) {
            return Response::validationError($validator->errors());
        }
        
        // Clean and validate mobile number
        $mobile = Security::clean($data['mobile']);
        if (!preg_match('/^[0-9]{10}$/', $mobile)) {
            return Response::error('Mobile number must be exactly 10 digits', [], 400);
        }
        
        $email = Security::clean($data['email']);
        
        // Check if email already exists
        $stmt = $this->db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        
        if ($stmt->fetch()) {
            return Response::error('Email already registered', [], 409);
        }
        
        // Check if mobile already exists
        $stmt = $this->db->prepare("SELECT id FROM users WHERE mobile = ?");
        $stmt->execute([$mobile]);
        
        if ($stmt->fetch()) {
            return Response::error('Mobile number already registered', [], 409);
        }
        
        // Create user
        try {
            $stmt = $this->db->prepare("
                INSERT INTO users (name, email, mobile, password, role, status, created_at, updated_at) 
                VALUES (?, ?, ?, ?, 'learner', 'active', NOW(), NOW())
            ");
            
            $stmt->execute([
                Security::clean($data['name']),
                $email,
                $mobile,
                Security::hashPassword($data['password'])
            ]);
            
            $userId = $this->db->lastInsertId();
            
            // Auto-login after registration
            $this->auth->login($email, $data['password']);
            
            return Response::success([
                'user_id' => $userId,
                'name' => $data['name'],
                'email' => $email,
                'mobile' => $mobile,
                'role' => 'learner'
            ], 'Account created successfully', 201);
            
        } catch (PDOException $e) {
            return Response::serverError('Failed to create account: ' . $e->getMessage());
        }
    }
    
    /**
     * Login user
     * ✅ FIXED: Returns 'data' key so login.php JS can read result.data.role
     */
    public function login($data) {
        // Validate input
        $validator = new Validator($data);
        $validator->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);
        
        if ($validator->fails()) {
            return Response::validationError($validator->errors());
        }
        
        // Rate limiting
        if (!Security::checkRateLimit('login_' . $data['email'], 5, 300)) {
            return Response::error('Too many login attempts. Please try again in 5 minutes.', [], 429);
        }
        
        // Attempt login
        $result = $this->auth->login(
            Security::clean($data['email']),
            $data['password'],
            $data['remember'] ?? false
        );
        
        if ($result['success']) {
            // ✅ FIXED: Wrap user inside 'data' key — login.php JS reads result.data.role
            echo json_encode([
                'success' => true,
                'message' => 'Login successful',
                'data' => $result['user']
            ]);
            exit;
        } else {
            return Response::error($result['error'], [], 401);
        }
    }
    
    /**
     * Logout user
     */
    public function logout() {
        $this->auth->logout();
        return Response::success([], 'Logged out successfully');
    }
    
    /**
     * Forgot password - send reset link
     */
    public function forgotPassword($data) {
        $validator = new Validator($data);
        $validator->validate(['email' => 'required|email']);
        
        if ($validator->fails()) {
            return Response::validationError($validator->errors());
        }
        
        $email = Security::clean($data['email']);
        
        // Find user
        $stmt = $this->db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        // Always return success to prevent email enumeration
        if (!$user) {
            return Response::success([], 'If the email exists, a reset link has been sent');
        }
        
        // Generate reset token
        $token = Security::generateToken(32);
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        // Save token
        $stmt = $this->db->prepare("
            UPDATE users 
            SET reset_token = ?, reset_token_expires = ? 
            WHERE id = ?
        ");
        $stmt->execute([$token, $expires, $user['id']]);
        
        return Response::success([], 'If the email exists, a reset link has been sent');
    }
    
    /**
     * Reset password with token
     */
    public function resetPassword($data) {
        $validator = new Validator($data);
        $validator->validate([
            'token' => 'required',
            'password' => 'required|min:6',
            'password_confirmation' => 'required|match:password'
        ]);
        
        if ($validator->fails()) {
            return Response::validationError($validator->errors());
        }
        
        // Verify token
        $stmt = $this->db->prepare("
            SELECT id FROM users 
            WHERE reset_token = ? 
            AND reset_token_expires > NOW()
        ");
        $stmt->execute([Security::clean($data['token'])]);
        $user = $stmt->fetch();
        
        if (!$user) {
            return Response::error('Invalid or expired reset token', [], 400);
        }
        
        // Update password
        $stmt = $this->db->prepare("
            UPDATE users 
            SET password = ?, reset_token = NULL, reset_token_expires = NULL 
            WHERE id = ?
        ");
        $stmt->execute([
            Security::hashPassword($data['password']),
            $user['id']
        ]);
        
        return Response::success([], 'Password reset successfully');
    }
    
    /**
     * Get current user profile
     */
    public function profile() {
        if (!$this->auth->check()) {
            return Response::unauthorized();
        }
        
        $user = $this->auth->user();
        return Response::success($user);
    }
}