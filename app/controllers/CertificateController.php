<?php
/**
 * CertificateController - Handles certificate operations
 */

class CertificateController {
    private $db;
    private $auth;
    
    public function __construct($db, $auth) {
        $this->db = $db;
        $this->auth = $auth;
    }
    
    /**
     * Generate certificate for completed course
     */
    public function generate($data) {
        $validator = new Validator($data);
        $validator->validate([
            'course_id' => 'required|numeric'
        ]);
        
        if ($validator->fails()) {
            return Response::validationError($validator->errors());
        }
        
        $userId = $this->auth->id();
        $courseId = $data['course_id'];
        
        // Check if course is completed
        $progressModel = new Progress($this->db);
        if (!$progressModel->isCourseCompleted($userId, $courseId)) {
            return Response::error('Course not completed yet', [], 400);
        }
        
        // Generate certificate
        $certificateModel = new Certificate($this->db);
        $certificateId = $certificateModel->generate($userId, $courseId);
        
        if ($certificateId) {
            $certificate = $certificateModel->findByUserAndCourse($userId, $courseId);
            return Response::success([
                'certificate_id' => $certificateId,
                'certificate_code' => $certificate['certificate_code']
            ], 'Certificate generated successfully', 201);
        } else {
            return Response::error('Failed to generate certificate');
        }
    }
    
    /**
     * Get user's certificates
     */
    public function getMyCertificates() {
        $userId = $this->auth->id();
        $certificateModel = new Certificate($this->db);
        
        $certificates = $certificateModel->getUserCertificates($userId);
        
        return Response::success(['certificates' => $certificates]);
    }
    
    /**
     * Verify certificate
     */
    public function verify($data) {
        $validator = new Validator($data);
        $validator->validate([
            'code' => 'required'
        ]);
        
        if ($validator->fails()) {
            return Response::validationError($validator->errors());
        }
        
        $certificateModel = new Certificate($this->db);
        $result = $certificateModel->verify($data['code']);
        
        return Response::success($result);
    }
    
    /**
     * Get all certificates (admin)
     */
    public function getAll() {
        if (!$this->auth->isAdmin()) {
            return Response::error('Unauthorized', [], 403);
        }
        
        $certificateModel = new Certificate($this->db);
        $certificates = $certificateModel->getAll();
        
        return Response::success(['certificates' => $certificates]);
    }
    
    /**
     * Revoke certificate (admin)
     */
    public function revoke($data) {
        if (!$this->auth->isAdmin()) {
            return Response::error('Unauthorized', [], 403);
        }
        
        $validator = new Validator($data);
        $validator->validate([
            'certificate_id' => 'required|numeric'
        ]);
        
        if ($validator->fails()) {
            return Response::validationError($validator->errors());
        }
        
        $certificateModel = new Certificate($this->db);
        $result = $certificateModel->revoke($data['certificate_id']);
        
        if ($result) {
            return Response::success([], 'Certificate revoked successfully');
        } else {
            return Response::error('Failed to revoke certificate');
        }
    }
    
    /**
     * Restore certificate (admin)
     */
    public function restore($data) {
        if (!$this->auth->isAdmin()) {
            return Response::error('Unauthorized', [], 403);
        }
        
        $validator = new Validator($data);
        $validator->validate([
            'certificate_id' => 'required|numeric'
        ]);
        
        if ($validator->fails()) {
            return Response::validationError($validator->errors());
        }
        
        $certificateModel = new Certificate($this->db);
        $result = $certificateModel->restore($data['certificate_id']);
        
        if ($result) {
            return Response::success([], 'Certificate restored successfully');
        } else {
            return Response::error('Failed to restore certificate');
        }
    }
}
