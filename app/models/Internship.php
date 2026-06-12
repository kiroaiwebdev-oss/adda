<?php

class Internship {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    /**
     * Get all internships with optional filters
     */
    public function getAll($filters = []) {
        $sql = "SELECT * FROM internships WHERE 1=1";
        $params = [];
        
        if (isset($filters['is_active'])) {
            $sql .= " AND is_active = ?";
            $params[] = $filters['is_active'];
        }
        
        if (isset($filters['category'])) {
            $sql .= " AND category = ?";
            $params[] = $filters['category'];
        }
        
        if (isset($filters['skill_level'])) {
            $sql .= " AND skill_level = ?";
            $params[] = $filters['skill_level'];
        }
        
        $sql .= " ORDER BY created_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get internship by ID
     */
    public function getById($id) {
        $stmt = $this->db->prepare("SELECT * FROM internships WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get internship by slug
     */
    public function getBySlug($slug) {
        $stmt = $this->db->prepare("SELECT * FROM internships WHERE slug = ?");
        $stmt->execute([$slug]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Create new internship
     */
    public function create($data) {
        $sql = "INSERT INTO internships (
            title, slug, description, duration_weeks, cover_image, 
            skill_level, category, is_active, enrollment_limit, 
            requirements, what_you_learn, certificate_template_id,
            price, discount_price, discount_percentage
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([
            $data['title'],
            $data['slug'],
            $data['description'],
            $data['duration_weeks'],
            $data['cover_image'] ?? null,
            $data['skill_level'],
            $data['category'],
            $data['is_active'] ?? 1,
            $data['enrollment_limit'] ?? null,
            $data['requirements'] ?? null,
            $data['what_you_learn'] ?? null,
            $data['certificate_template_id'] ?? null,
            $data['price'] ?? 0,
            $data['discount_price'] ?? null,
            $data['discount_percentage'] ?? null
        ]);
        
        return $result ? $this->db->lastInsertId() : false;
    }
    
    /**
     * Update internship
     */
    public function update($id, $data) {
        $sql = "UPDATE internships SET 
            title = ?, slug = ?, description = ?, duration_weeks = ?, 
            cover_image = ?, skill_level = ?, category = ?, is_active = ?, 
            enrollment_limit = ?, requirements = ?, what_you_learn = ?, 
            certificate_template_id = ?, price = ?, discount_price = ?, 
            discount_percentage = ?, updated_at = CURRENT_TIMESTAMP
            WHERE id = ?";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            $data['title'],
            $data['slug'],
            $data['description'],
            $data['duration_weeks'],
            $data['cover_image'] ?? null,
            $data['skill_level'],
            $data['category'],
            $data['is_active'] ?? 1,
            $data['enrollment_limit'] ?? null,
            $data['requirements'] ?? null,
            $data['what_you_learn'] ?? null,
            $data['certificate_template_id'] ?? null,
            $data['price'] ?? 0,
            $data['discount_price'] ?? null,
            $data['discount_percentage'] ?? null,
            $id
        ]);
    }
    
    /**
     * Delete internship
     */
    public function delete($id) {
        $stmt = $this->db->prepare("DELETE FROM internships WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
    /**
     * Toggle active status
     */
    public function toggleStatus($id) {
        $stmt = $this->db->prepare("UPDATE internships SET is_active = NOT is_active WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
    /**
     * Get enrollment count
     */
    public function getEnrollmentCount($id) {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM internship_enrollments WHERE internship_id = ? AND status != 'cancelled'");
        $stmt->execute([$id]);
        return $stmt->fetchColumn();
    }
    
    /**
     * Check if enrollment limit reached
     */
    public function isEnrollmentFull($id) {
        $internship = $this->getById($id);
        if (!$internship || !$internship['enrollment_limit']) {
            return false;
        }
        
        $currentCount = $this->getEnrollmentCount($id);
        return $currentCount >= $internship['enrollment_limit'];
    }
    
    /**
     * Get internship stats
     */
    public function getStats($id) {
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(DISTINCT ie.user_id) as total_enrolled,
                COUNT(CASE WHEN ie.status = 'completed' THEN 1 END) as completed,
                COUNT(CASE WHEN ie.status = 'active' THEN 1 END) as active,
                COUNT(CASE WHEN icr.status = 'pending' THEN 1 END) as pending_certificates
            FROM internship_enrollments ie
            LEFT JOIN internship_certificate_requests icr ON ie.id = icr.enrollment_id
            WHERE ie.internship_id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Generate unique slug
     */
    public function generateSlug($title, $excludeId = null) {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));
        $originalSlug = $slug;
        $counter = 1;
        
        while ($this->slugExists($slug, $excludeId)) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }
        
        return $slug;
    }
    
    /**
     * Check if slug exists
     */
    private function slugExists($slug, $excludeId = null) {
        $sql = "SELECT COUNT(*) FROM internships WHERE slug = ?";
        $params = [$slug];
        
        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn() > 0;
    }
}
