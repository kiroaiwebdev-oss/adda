<?php
/**
 * Internship Certificate Model
 */

class InternshipCertificate {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * Generate certificate code
     */
    private function generateCode() {
        do {
            $code = 'IC-' . date('Y') . '-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM internship_certificates WHERE certificate_code = ?");
            $stmt->execute([$code]);
            $exists = $stmt->fetchColumn() > 0;
        } while ($exists);
        return $code;
    }
    
    /**
     * Create internship certificate
     */
    public function create($data) {
        $code = $this->generateCode();
        $number = 'IA/IC/' . date('Y') . '/' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        $stmt = $this->db->prepare("
            INSERT INTO internship_certificates 
            (user_id, request_id, certificate_code, certificate_number, student_name, 
             company_name, internship_title, start_date, end_date, duration_months, 
             template_id, custom_fields) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $customFields = isset($data['custom_fields']) ? json_encode($data['custom_fields']) : null;
        
        $stmt->execute([
            $data['user_id'],
            $data['request_id'] ?? null,
            $code,
            $number,
            $data['student_name'],
            $data['company_name'],
            $data['internship_title'],
            $data['start_date'],
            $data['end_date'],
            $data['duration_months'],
            $data['template_id'] ?? 1,
            $customFields
        ]);
        
        return $this->db->lastInsertId();
    }
    
    /**
     * Get all certificates
     */
    public function getAll($filters = []) {
        $sql = "
            SELECT ic.*, u.name as user_name, u.email as user_email
            FROM internship_certificates ic
            LEFT JOIN users u ON ic.user_id = u.id
            WHERE 1=1
        ";
        $params = [];
        
        if (!empty($filters['revoked'])) {
            $sql .= " AND ic.revoked = ?";
            $params[] = $filters['revoked'] == 'yes' ? 1 : 0;
        }
        
        if (!empty($filters['search'])) {
            $sql .= " AND (ic.student_name LIKE ? OR ic.certificate_code LIKE ? OR u.email LIKE ?)";
            $search = '%' . $filters['search'] . '%';
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }
        
        $sql .= " ORDER BY ic.issued_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get by ID
     */
    public function getById($id) {
        $stmt = $this->db->prepare("
            SELECT ic.*, u.name as user_name, u.email as user_email
            FROM internship_certificates ic
            LEFT JOIN users u ON ic.user_id = u.id
            WHERE ic.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get by certificate code
     */
    public function getByCode($code) {
        $stmt = $this->db->prepare("
            SELECT ic.*, u.name as user_name, u.email as user_email
            FROM internship_certificates ic
            LEFT JOIN users u ON ic.user_id = u.id
            WHERE ic.certificate_code = ?
        ");
        $stmt->execute([$code]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Update certificate
     */
    public function update($id, $data) {
        $fields = [];
        $params = [];
        
        $allowedFields = ['student_name', 'company_name', 'internship_title', 'start_date', 'end_date', 'duration_months', 'template_id', 'custom_fields'];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = ?";
                $params[] = $field === 'custom_fields' ? json_encode($data[$field]) : $data[$field];
            }
        }
        
        if (empty($fields)) return false;
        
        $params[] = $id;
        $sql = "UPDATE internship_certificates SET " . implode(', ', $fields) . " WHERE id = ?";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }
    
    /**
     * Revoke certificate
     */
    public function revoke($id, $revokedBy) {
        $stmt = $this->db->prepare("UPDATE internship_certificates SET revoked = 1, revoked_at = NOW(), revoked_by = ? WHERE id = ?");
        return $stmt->execute([$revokedBy, $id]);
    }
    
    /**
     * Restore certificate
     */
    public function restore($id) {
        $stmt = $this->db->prepare("UPDATE internship_certificates SET revoked = 0, revoked_at = NULL, revoked_by = NULL WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
    /**
     * Delete certificate
     */
    public function delete($id) {
        $stmt = $this->db->prepare("DELETE FROM internship_certificates WHERE id = ?");
        return $stmt->execute([$id]);
    }
}
