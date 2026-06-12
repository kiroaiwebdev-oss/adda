<?php

class InternshipEnrollment {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    /**
     * Get all enrollments with filters
     */
    public function getAll($filters = []) {
        $sql = "SELECT 
            ie.*,
            i.title as internship_title,
            i.category as internship_category,
            i.duration_weeks,
            i.company_name,
            u.name as user_name,
            u.email as user_email
            FROM internship_enrollments ie
            JOIN internships i ON ie.internship_id = i.id
            JOIN users u ON ie.user_id = u.id
            WHERE 1=1";
        
        $params = [];
        
        if (isset($filters['internship_id'])) {
            $sql .= " AND ie.internship_id = ?";
            $params[] = $filters['internship_id'];
        }
        
        if (isset($filters['user_id'])) {
            $sql .= " AND ie.user_id = ?";
            $params[] = $filters['user_id'];
        }
        
        if (isset($filters['status'])) {
            $sql .= " AND ie.status = ?";
            $params[] = $filters['status'];
        }
        
        $sql .= " ORDER BY ie.created_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * ✅ FIXED: Get enrollment by ID (NO completion_percentage)
     */
    public function getById($id) {
        $stmt = $this->db->prepare("
            SELECT 
                ie.id,
                ie.user_id,
                ie.internship_id,
                ie.payment_id,
                ie.payment_amount,
                ie.payment_status,
                ie.status,
                ie.coupon_id,
                ie.coupon_discount,
                ie.final_amount,
                ie.created_at,
                i.title as internship_title,
                i.description as internship_description,
                i.company_name,
                i.duration,
                i.category,
                u.name as user_name,
                u.email as user_email
            FROM internship_enrollments ie
            JOIN internships i ON ie.internship_id = i.id
            JOIN users u ON ie.user_id = u.id
            WHERE ie.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Check if user is enrolled
     */
    public function isEnrolled($userId, $internshipId) {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM internship_enrollments 
            WHERE user_id = ? AND internship_id = ?
        ");
        $stmt->execute([$userId, $internshipId]);
        return $stmt->fetchColumn() > 0;
    }
    
    /**
     * Get user's enrollment for specific internship
     */
    public function getUserEnrollment($userId, $internshipId) {
        $stmt = $this->db->prepare("
            SELECT 
                ie.*,
                i.title as internship_title,
                i.company_name,
                i.duration
            FROM internship_enrollments ie
            JOIN internships i ON ie.internship_id = i.id
            WHERE ie.user_id = ? AND ie.internship_id = ?
        ");
        $stmt->execute([$userId, $internshipId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * ✅ FIXED: Create enrollment (NO completion_percentage, NO offer_letter_sent)
     */
    public function create($data) {
        // Check if already enrolled
        if ($this->isEnrolled($data['user_id'], $data['internship_id'])) {
            return ['success' => false, 'message' => 'Already enrolled in this internship'];
        }
        
        $sql = "INSERT INTO internship_enrollments (
            internship_id, 
            user_id, 
            payment_id,
            payment_amount,
            payment_status,
            status,
            coupon_id,
            coupon_discount,
            final_amount
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([
            $data['internship_id'],
            $data['user_id'],
            $data['payment_id'] ?? null,
            $data['payment_amount'] ?? 0,
            $data['payment_status'] ?? 'pending',
            $data['status'] ?? 'applied',
            $data['coupon_id'] ?? null,
            $data['coupon_discount'] ?? 0,
            $data['final_amount'] ?? 0
        ]);
        
        if ($result) {
            return ['success' => true, 'enrollment_id' => $this->db->lastInsertId()];
        }
        
        return ['success' => false, 'message' => 'Failed to create enrollment'];
    }
    
    /**
     * ✅ FIXED: Update enrollment (NO completion_percentage, NO offer_letter_sent)
     */
    public function update($id, $data) {
        $fields = [];
        $params = [];
        
        if (isset($data['status'])) {
            $fields[] = "status = ?";
            $params[] = $data['status'];
        }
        
        if (isset($data['payment_status'])) {
            $fields[] = "payment_status = ?";
            $params[] = $data['payment_status'];
        }
        
        if (isset($data['payment_id'])) {
            $fields[] = "payment_id = ?";
            $params[] = $data['payment_id'];
        }
        
        if (isset($data['payment_amount'])) {
            $fields[] = "payment_amount = ?";
            $params[] = $data['payment_amount'];
        }
        
        if (isset($data['final_amount'])) {
            $fields[] = "final_amount = ?";
            $params[] = $data['final_amount'];
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $params[] = $id;
        $sql = "UPDATE internship_enrollments SET " . implode(', ', $fields) . " WHERE id = ?";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }
    
    /**
     * ✅ REMOVED: updateProgress - completion_percentage column doesn't exist
     * Use updateStatus instead
     */
    public function updateStatus($id, $status) {
        $stmt = $this->db->prepare("
            UPDATE internship_enrollments 
            SET status = ?
            WHERE id = ?
        ");
        return $stmt->execute([$status, $id]);
    }
    
    /**
     * ✅ FIXED: Mark as completed (NO completion_percentage)
     */
    public function markCompleted($id) {
        $stmt = $this->db->prepare("
            UPDATE internship_enrollments 
            SET status = 'completed'
            WHERE id = ?
        ");
        return $stmt->execute([$id]);
    }
    
    /**
     * Cancel enrollment - Status change to rejected or remove
     */
    public function cancel($id) {
        $stmt = $this->db->prepare("
            UPDATE internship_enrollments 
            SET status = 'rejected' 
            WHERE id = ?
        ");
        return $stmt->execute([$id]);
    }
    
    /**
     * ✅ REMOVED: sendOfferLetter - offer_letter_sent column doesn't exist
     */
    
    /**
     * Get enrollments count by status
     */
    public function getCountByStatus($internshipId = null) {
        $sql = "SELECT status, COUNT(*) as count FROM internship_enrollments";
        $params = [];
        
        if ($internshipId) {
            $sql .= " WHERE internship_id = ?";
            $params[] = $internshipId;
        }
        
        $sql .= " GROUP BY status";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        $result = ['applied' => 0, 'accepted' => 0, 'rejected' => 0, 'completed' => 0];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $result[$row['status']] = (int)$row['count'];
        }
        
        return $result;
    }
    
    /**
     * ✅ FIXED: Get my internships (NO completion_percentage, NO offer_letter)
     */
    public function getMyInternships($userId) {
        $stmt = $this->db->prepare("
            SELECT 
                ie.id,
                ie.internship_id,
                ie.user_id,
                ie.status,
                ie.payment_status,
                ie.payment_amount,
                ie.final_amount,
                ie.created_at,
                i.title,
                i.slug,
                i.description,
                i.company_name,
                i.duration,
                i.category,
                i.thumbnail,
                (SELECT COUNT(*) FROM internship_certificate_requests 
                 WHERE enrollment_id = ie.id) as has_certificate_request
            FROM internship_enrollments ie
            JOIN internships i ON ie.internship_id = i.id
            WHERE ie.user_id = ?
            ORDER BY ie.created_at DESC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get enrollment by user and internship
     */
    public function getByUserAndInternship($userId, $internshipId) {
        $stmt = $this->db->prepare("
            SELECT * FROM internship_enrollments 
            WHERE user_id = ? AND internship_id = ?
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$userId, $internshipId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Delete enrollment
     */
    public function delete($id) {
        $stmt = $this->db->prepare("DELETE FROM internship_enrollments WHERE id = ?");
        return $stmt->execute([$id]);
    }
}
