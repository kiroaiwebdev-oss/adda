<?php
/**
 * Contact Submission Model
 * Handles all contact form submission operations
 */

class ContactSubmission {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    /**
     * Get all contact submissions with filters
     */
    public function getAll($filters = []) {
        $sql = "SELECT cs.*, u.name as assigned_admin_name 
                FROM contact_submissions cs
                LEFT JOIN users u ON cs.assigned_to = u.id
                WHERE 1=1";
        
        $params = [];
        
        // Status filter
        if (!empty($filters['status'])) {
            $sql .= " AND cs.status = ?";
            $params[] = $filters['status'];
        }
        
        // Priority filter
        if (!empty($filters['priority'])) {
            $sql .= " AND cs.priority = ?";
            $params[] = $filters['priority'];
        }
        
        // Search filter
        if (!empty($filters['search'])) {
            $sql .= " AND (cs.name LIKE ? OR cs.email LIKE ? OR cs.subject LIKE ? OR cs.message LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        // Date filter
        if (!empty($filters['date_from'])) {
            $sql .= " AND DATE(cs.created_at) >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $sql .= " AND DATE(cs.created_at) <= ?";
            $params[] = $filters['date_to'];
        }
        
        $sql .= " ORDER BY cs.created_at DESC";
        
        // Pagination
        if (!empty($filters['limit'])) {
            $sql .= " LIMIT ?";
            $params[] = (int)$filters['limit'];
            
            if (!empty($filters['offset'])) {
                $sql .= " OFFSET ?";
                $params[] = (int)$filters['offset'];
            }
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get submission by ID
     */
    public function getById($id) {
        $stmt = $this->db->prepare("
            SELECT cs.*, u.name as assigned_admin_name, u.email as assigned_admin_email
            FROM contact_submissions cs
            LEFT JOIN users u ON cs.assigned_to = u.id
            WHERE cs.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get submission statistics
     */
    public function getStatistics() {
        $stmt = $this->db->query("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
                SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved,
                SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed,
                SUM(CASE WHEN priority = 'urgent' THEN 1 ELSE 0 END) as urgent,
                SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today,
                SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as this_week
            FROM contact_submissions
        ");
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Update submission status
     */
    public function updateStatus($id, $status, $adminId = null) {
        $sql = "UPDATE contact_submissions SET status = ?, updated_at = NOW()";
        $params = [$status];
        
        if ($status === 'resolved' || $status === 'closed') {
            $sql .= ", resolved_at = NOW()";
        }
        
        if ($adminId !== null) {
            $sql .= ", assigned_to = ?";
            $params[] = $adminId;
        }
        
        $sql .= " WHERE id = ?";
        $params[] = $id;
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }
    
    /**
     * Update admin notes
     */
    public function updateNotes($id, $notes) {
        $stmt = $this->db->prepare("UPDATE contact_submissions SET admin_notes = ?, updated_at = NOW() WHERE id = ?");
        return $stmt->execute([$notes, $id]);
    }
    
    /**
     * Assign to admin
     */
    public function assignToAdmin($id, $adminId) {
        $stmt = $this->db->prepare("UPDATE contact_submissions SET assigned_to = ?, updated_at = NOW() WHERE id = ?");
        return $stmt->execute([$adminId, $id]);
    }
    
    /**
     * Add reply
     */
    public function addReply($submissionId, $adminId, $replyMessage, $sendEmail = false) {
        $stmt = $this->db->prepare("
            INSERT INTO contact_replies (submission_id, admin_id, reply_message, sent_via_email)
            VALUES (?, ?, ?, ?)
        ");
        return $stmt->execute([$submissionId, $adminId, $replyMessage, $sendEmail ? 1 : 0]);
    }
    
    /**
     * Get all replies for a submission
     */
    public function getReplies($submissionId) {
        $stmt = $this->db->prepare("
            SELECT cr.*, u.name as admin_name, u.email as admin_email
            FROM contact_replies cr
            JOIN users u ON cr.admin_id = u.id
            WHERE cr.submission_id = ?
            ORDER BY cr.created_at DESC
        ");
        $stmt->execute([$submissionId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Delete submission
     */
    public function delete($id) {
        $stmt = $this->db->prepare("DELETE FROM contact_submissions WHERE id = ?");
        return $stmt->execute([$id]);
    }
}
