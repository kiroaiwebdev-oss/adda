<?php

class InternshipController {
    private $db;
    private $auth;
    private $internshipModel;
    private $enrollmentModel;
    
    public function __construct($database, $authInstance) {
        $this->db = $database;
        $this->auth = $authInstance;
        
        require_once __DIR__ . '/../models/Internship.php';
        require_once __DIR__ . '/../models/InternshipEnrollment.php';
        
        $this->internshipModel = new Internship($this->db);
        $this->enrollmentModel = new InternshipEnrollment($this->db);
    }
    
    /**
     * Admin: List all internships
     */
    public function adminList() {
        if (!$this->auth->check() || $this->auth->user()['role'] !== 'admin') {
            header('Location: /public/login.php');
            exit;
        }
        
        $internships = $this->internshipModel->getAll();
        
        // Add stats for each internship
        foreach ($internships as &$internship) {
            $internship['stats'] = $this->internshipModel->getStats($internship['id']);
        }
        
        require_once __DIR__ . '/../views/admin/internships/list.php';
    }
    
    /**
     * Admin: Create internship form
     */
    public function adminCreate() {
        if (!$this->auth->check() || $this->auth->user()['role'] !== 'admin') {
            header('Location: /public/login.php');
            exit;
        }
        
        require_once __DIR__ . '/../views/admin/internships/create.php';
    }
    
    /**
     * Admin: Edit internship form
     */
    public function adminEdit($id) {
        if (!$this->auth->check() || $this->auth->user()['role'] !== 'admin') {
            header('Location: /public/login.php');
            exit;
        }
        
        $internship = $this->internshipModel->getById($id);
        if (!$internship) {
            header('Location: /app/views/admin/internships/list.php');
            exit;
        }
        
        require_once __DIR__ . '/../views/admin/internships/edit.php';
    }
    
    /**
     * Admin: View enrollments
     */
    public function adminEnrollments() {
        if (!$this->auth->check() || $this->auth->user()['role'] !== 'admin') {
            header('Location: /public/login.php');
            exit;
        }
        
        $internshipId = $_GET['internship_id'] ?? null;
        
        $filters = [];
        if ($internshipId) {
            $filters['internship_id'] = $internshipId;
        }
        
        $enrollments = $this->enrollmentModel->getAll($filters);
        $internships = $this->internshipModel->getAll(['is_active' => 1]);
        
        require_once __DIR__ . '/../views/admin/internships/enrollments.php';
    }
    
    /**
     * Learner: View my internships
     */
    public function myInternships() {
        if (!$this->auth->check()) {
            header('Location: /public/login.php');
            exit;
        }
        
        $user = $this->auth->user();
        $myInternships = $this->enrollmentModel->getMyInternships($user['id']);
        
        require_once __DIR__ . '/../views/learner/my-internships.php';
    }
    
    /**
     * Learner: Browse available internships
     */
    public function browse() {
        if (!$this->auth->check()) {
            header('Location: /public/login.php');
            exit;
        }
        
        $internships = $this->internshipModel->getAll(['is_active' => 1]);
        
        require_once __DIR__ . '/../views/learner/internships.php';
    }
}
