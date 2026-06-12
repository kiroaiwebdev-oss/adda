<?php
/**
 * Certificate Model - FIXED for External Students Support
 */

class Certificate {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * Generate certificate for user
     */
    public function generate($userId, $courseId) {
        // Check if already has certificate
        $existing = $this->findByUserAndCourse($userId, $courseId);
        if ($existing && (!isset($existing['revoked']) || !$existing['revoked'])) {
            return $existing['id'];
        }
        
        // Generate unique certificate code
        $certificateCode = $this->generateUniqueCode();
        $certificateNumber = $this->generateCertificateNumber();
        
        $stmt = $this->db->prepare("
            INSERT INTO certificates (user_id, course_id, certificate_code, certificate_number, issued_date) 
            VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)
        ");
        
        $stmt->execute([$userId, $courseId, $certificateCode, $certificateNumber]);
        
        return $this->db->lastInsertId();
    }
    
    /**
     * Generate unique certificate code
     */
    private function generateUniqueCode() {
        do {
            $code = 'CERT-' . date('Y') . '-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));
            
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM certificates WHERE certificate_code = ?");
            $stmt->execute([$code]);
            $exists = $stmt->fetchColumn() > 0;
        } while ($exists);
        
        return $code;
    }
    
    /**
     * Generate certificate number
     */
    private function generateCertificateNumber() {
        $year = date('Y');
        
        $stmt = $this->db->prepare("
            SELECT certificate_number 
            FROM certificates 
            WHERE certificate_number LIKE ? 
            ORDER BY id DESC 
            LIMIT 1
        ");
        $stmt->execute(["IA/CC/$year/%"]);
        $lastNumber = $stmt->fetchColumn();
        
        if ($lastNumber) {
            $parts = explode('/', $lastNumber);
            $sequence = isset($parts[3]) ? intval($parts[3]) + 1 : 1;
        } else {
            $sequence = 1;
        }
        
        return sprintf("IA/CC/%s/%04d", $year, $sequence);
    }
    
    /**
     * Find certificate by user and course
     */
    public function findByUserAndCourse($userId, $courseId) {
        $stmt = $this->db->prepare("
            SELECT c.*, 
                   c.issued_date,
                   co.title as course_title, 
                   co.description as course_description,
                   COALESCE(u.name, c.student_name) as user_name, 
                   COALESCE(u.email, c.student_email) as user_email
            FROM certificates c
            JOIN courses co ON c.course_id = co.id
            LEFT JOIN users u ON c.user_id = u.id
            WHERE c.user_id = ? AND c.course_id = ?
            ORDER BY c.id DESC
            LIMIT 1
        ");
        $stmt->execute([$userId, $courseId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * ✅ FIXED: Find certificate by code or number (supports external students)
     */
    public function findByCode($code) {
        $stmt = $this->db->prepare("
            SELECT c.*, 
                   c.issued_date,
                   co.title as course_title, 
                   co.description as course_description,
                   COALESCE(u.name, c.student_name) as user_name, 
                   COALESCE(u.email, c.student_email) as user_email,
                   CASE WHEN c.user_id IS NULL THEN 'External' ELSE 'Registered' END as student_type
            FROM certificates c
            JOIN courses co ON c.course_id = co.id
            LEFT JOIN users u ON c.user_id = u.id
            WHERE c.certificate_code = ? OR c.certificate_number = ?
        ");
        $stmt->execute([$code, $code]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get user certificates
     */
    public function getUserCertificates($userId) {
        $stmt = $this->db->prepare("
            SELECT c.*, 
                   c.issued_date,
                   co.title as course_title, 
                   co.description as course_description
            FROM certificates c
            JOIN courses co ON c.course_id = co.id
            WHERE c.user_id = ?
            ORDER BY c.id DESC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * ✅ FIXED: Get all certificates (admin) - supports external students
     */
    public function getAll() {
        $stmt = $this->db->query("
            SELECT c.*, 
                   c.issued_date,
                   co.title as course_title,
                   co.description as course_description,
                   COALESCE(u.name, c.student_name) as user_name,
                   COALESCE(u.email, c.student_email) as user_email,
                   CASE WHEN c.user_id IS NULL THEN 'External' ELSE 'Registered' END as student_type
            FROM certificates c
            JOIN courses co ON c.course_id = co.id
            LEFT JOIN users u ON c.user_id = u.id
            ORDER BY c.id DESC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Revoke certificate
     */
    public function revoke($certificateId) {
        $stmt = $this->db->prepare("UPDATE certificates SET revoked = 1, revoked_at = CURRENT_TIMESTAMP WHERE id = ?");
        return $stmt->execute([$certificateId]);
    }
    
    /**
     * Restore certificate
     */
    public function restore($certificateId) {
        $stmt = $this->db->prepare("UPDATE certificates SET revoked = 0, revoked_at = NULL WHERE id = ?");
        return $stmt->execute([$certificateId]);
    }
    
    /**
     * Delete certificate
     */
    public function delete($certificateId) {
        $stmt = $this->db->prepare("DELETE FROM certificates WHERE id = ?");
        return $stmt->execute([$certificateId]);
    }
    
    /**
     * ✅ Verify certificate (works for both registered & external students)
     */
    public function verify($code) {
        $certificate = $this->findByCode($code);
        
        if (!$certificate) {
            return [
                'valid' => false,
                'message' => 'Certificate not found'
            ];
        }
        
        if (isset($certificate['revoked']) && $certificate['revoked']) {
            return [
                'valid' => false,
                'message' => 'Certificate has been revoked',
                'certificate' => $certificate
            ];
        }
        
        return [
            'valid' => true,
            'message' => 'Certificate is valid',
            'certificate' => $certificate
        ];
    }
}
