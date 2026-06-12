<?php

class InternshipCertificateRequest {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    /**
     * ✅ FIXED: Get all certificate requests (NO completion_percentage)
     */
    public function getAll($filters = []) {
        $sql = "SELECT 
            icr.id,
            icr.user_id,
            icr.internship_id,
            icr.enrollment_id,
            icr.student_name,
            icr.email,
            icr.phone,
            icr.linkedin_url,
            icr.address,
            icr.dob,
            icr.gender,
            icr.college_name,
            icr.degree,
            icr.notes,
            icr.request_date,
            icr.status,
            icr.admin_notes,
            icr.processed_by,
            icr.processed_at,
            icr.certificate_issued_at,
            i.title as internship_title,
            i.company_name,
            i.category,
            i.duration,
            u.name as user_name,
            u.email as user_email,
            ie.status as enrollment_status,
            ie.payment_status,
            admin.name as processed_by_name
            FROM internship_certificate_requests icr
            JOIN internships i ON icr.internship_id = i.id
            JOIN users u ON icr.user_id = u.id
            LEFT JOIN internship_enrollments ie ON icr.enrollment_id = ie.id
            LEFT JOIN users admin ON icr.processed_by = admin.id
            WHERE 1=1";
        
        $params = [];
        
        if (isset($filters['status'])) {
            $sql .= " AND icr.status = ?";
            $params[] = $filters['status'];
        }
        
        if (isset($filters['user_id'])) {
            $sql .= " AND icr.user_id = ?";
            $params[] = $filters['user_id'];
        }
        
        if (isset($filters['internship_id'])) {
            $sql .= " AND icr.internship_id = ?";
            $params[] = $filters['internship_id'];
        }
        
        $sql .= " ORDER BY icr.request_date DESC";
        
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("❌ Error in InternshipCertificateRequest::getAll - " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * ✅ FIXED: Get request by ID (NO completion_percentage, start_date, end_date)
     */
    public function getById($id) {
        $stmt = $this->db->prepare("
            SELECT 
                icr.id,
                icr.user_id,
                icr.internship_id,
                icr.enrollment_id,
                icr.student_name,
                icr.email,
                icr.phone,
                icr.linkedin_url,
                icr.address,
                icr.dob,
                icr.gender,
                icr.college_name,
                icr.degree,
                icr.notes,
                icr.request_date,
                icr.status,
                icr.admin_notes,
                icr.processed_by,
                icr.processed_at,
                icr.certificate_issued_at,
                i.title as internship_title,
                i.description as internship_description,
                i.company_name,
                i.category,
                i.duration,
                u.name as user_name,
                u.email as user_email,
                ie.status as enrollment_status,
                ie.payment_status,
                ie.created_at as enrollment_date
            FROM internship_certificate_requests icr
            JOIN internships i ON icr.internship_id = i.id
            JOIN users u ON icr.user_id = u.id
            LEFT JOIN internship_enrollments ie ON icr.enrollment_id = ie.id
            WHERE icr.id = ?
        ");
        
        try {
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("❌ Error in InternshipCertificateRequest::getById - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if request exists for enrollment
     */
    public function hasRequested($enrollmentId) {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM internship_certificate_requests 
            WHERE enrollment_id = ?
        ");
        
        try {
            $stmt->execute([$enrollmentId]);
            return $stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            error_log("❌ Error checking if requested: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if user has pending request for internship
     */
    public function hasPendingRequest($userId, $internshipId) {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM internship_certificate_requests 
            WHERE user_id = ? AND internship_id = ? AND status = 'pending'
        ");
        
        try {
            $stmt->execute([$userId, $internshipId]);
            return $stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            error_log("❌ Error checking pending request: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create certificate request
     */
    public function create($data) {
        // Check if already requested
        if ($this->hasRequested($data['enrollment_id'])) {
            return ['success' => false, 'message' => 'Certificate already requested for this enrollment'];
        }
        
        // Check for pending request
        if ($this->hasPendingRequest($data['user_id'], $data['internship_id'])) {
            return ['success' => false, 'message' => 'You already have a pending certificate request for this internship'];
        }
        
        $sql = "INSERT INTO internship_certificate_requests (
            user_id, 
            internship_id, 
            enrollment_id, 
            status
        ) VALUES (?, ?, ?, 'pending')";
        
        try {
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([
                $data['user_id'],
                $data['internship_id'],
                $data['enrollment_id']
            ]);
            
            if ($result) {
                return [
                    'success' => true, 
                    'request_id' => $this->db->lastInsertId(),
                    'message' => 'Certificate request submitted successfully'
                ];
            }
            
            return ['success' => false, 'message' => 'Failed to create request'];
            
        } catch (PDOException $e) {
            error_log("❌ Error creating certificate request: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
    
    /**
     * Approve request
     */
    public function approve($id, $adminId, $notes = null) {
        $stmt = $this->db->prepare("
            UPDATE internship_certificate_requests 
            SET status = 'approved', 
                processed_by = ?, 
                processed_at = NOW(),
                certificate_issued_at = NOW(),
                admin_notes = ?
            WHERE id = ? AND status = 'pending'
        ");
        
        try {
            return $stmt->execute([$adminId, $notes, $id]);
        } catch (PDOException $e) {
            error_log("❌ Error approving certificate request: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Reject request
     */
    public function reject($id, $adminId, $notes = null) {
        $stmt = $this->db->prepare("
            UPDATE internship_certificate_requests 
            SET status = 'rejected', 
                processed_by = ?, 
                processed_at = NOW(),
                admin_notes = ?
            WHERE id = ? AND status = 'pending'
        ");
        
        try {
            return $stmt->execute([$adminId, $notes, $id]);
        } catch (PDOException $e) {
            error_log("❌ Error rejecting certificate request: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get pending count
     */
    public function getPendingCount() {
        try {
            $stmt = $this->db->query("
                SELECT COUNT(*) 
                FROM internship_certificate_requests 
                WHERE status = 'pending'
            ");
            return (int)$stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("❌ Error getting pending count: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * ✅ FIXED: Get my certificate requests (NO completion_percentage)
     */
    public function getMyRequests($userId) {
        $stmt = $this->db->prepare("
            SELECT 
                icr.id,
                icr.user_id,
                icr.internship_id,
                icr.enrollment_id,
                icr.student_name,
                icr.email,
                icr.phone,
                icr.status,
                icr.request_date,
                icr.admin_notes,
                icr.processed_at,
                icr.certificate_issued_at,
                i.title as internship_title,
                i.company_name,
                i.category,
                i.duration,
                ie.status as enrollment_status,
                ic.id as certificate_id,
                ic.certificate_number
            FROM internship_certificate_requests icr
            JOIN internships i ON icr.internship_id = i.id
            LEFT JOIN internship_enrollments ie ON icr.enrollment_id = ie.id
            LEFT JOIN internship_certificates ic ON ic.user_id = icr.user_id AND ic.internship_id = icr.internship_id
            WHERE icr.user_id = ?
            ORDER BY icr.request_date DESC
        ");
        
        try {
            $stmt->execute([$userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("❌ Error in getMyRequests: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get requests by status
     */
    public function getByStatus($status) {
        $stmt = $this->db->prepare("
            SELECT 
                icr.*,
                i.title as internship_title,
                i.company_name,
                u.name as user_name,
                u.email as user_email
            FROM internship_certificate_requests icr
            JOIN internships i ON icr.internship_id = i.id
            JOIN users u ON icr.user_id = u.id
            WHERE icr.status = ?
            ORDER BY icr.request_date DESC
        ");
        
        try {
            $stmt->execute([$status]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("❌ Error in getByStatus: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Update request details
     */
    public function updateDetails($id, $data) {
        $fields = [];
        $params = [];
        
        if (isset($data['student_name'])) {
            $fields[] = "student_name = ?";
            $params[] = $data['student_name'];
        }
        
        if (isset($data['email'])) {
            $fields[] = "email = ?";
            $params[] = $data['email'];
        }
        
        if (isset($data['phone'])) {
            $fields[] = "phone = ?";
            $params[] = $data['phone'];
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $params[] = $id;
        $sql = "UPDATE internship_certificate_requests SET " . implode(', ', $fields) . " WHERE id = ?";
        
        try {
            $stmt = $this->db->prepare($sql);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            error_log("❌ Error updating request details: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete request
     */
    public function delete($id) {
        $stmt = $this->db->prepare("DELETE FROM internship_certificate_requests WHERE id = ?");
        
        try {
            return $stmt->execute([$id]);
        } catch (PDOException $e) {
            error_log("❌ Error deleting request: " . $e->getMessage());
            return false;
        }
    }
}
