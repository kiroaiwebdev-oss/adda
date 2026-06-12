<?php
/**
 * Course Model
 */

class Course {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * Get all courses
     */
    public function getAll($filters = []) {
        $query = "SELECT * FROM courses";
        $params = [];
        $where = [];
        
        if (!empty($filters['status'])) {
            $where[] = "status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['search'])) {
            $where[] = "(title LIKE ? OR description LIKE ?)";
            $params[] = "%{$filters['search']}%";
            $params[] = "%{$filters['search']}%";
        }
        
        if (!empty($where)) {
            $query .= " WHERE " . implode(' AND ', $where);
        }
        
        $query .= " ORDER BY created_at DESC";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Get course by ID
     */
    public function findById($id) {
        $stmt = $this->db->prepare("SELECT * FROM courses WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    /**
     * Get course by ID (Alias for consistency)
     */
    public function getById($id) {
        return $this->findById($id);
    }
    
    /**
     * Create course
     */
    public function create($data) {
        $stmt = $this->db->prepare("
            INSERT INTO courses (title, slug, description, price, status, created_by) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $slug = $this->generateSlug($data['title']);
        
        $stmt->execute([
            $data['title'],
            $slug,
            $data['description'] ?? '',
            $data['price'] ?? 0,
            $data['status'] ?? 'draft',
            $data['created_by']
        ]);
        
        return $this->db->lastInsertId();
    }
    
    /**
     * Update course
     */
    public function update($id, $data) {
        $fields = [];
        $params = [];
        
        $allowedFields = ['title', 'description', 'price', 'status', 'icon', 'preview_image'];
        
        foreach ($data as $key => $value) {
            if (in_array($key, $allowedFields)) {
                $fields[] = "$key = ?";
                $params[] = $value;
            }
        }
        
        if (empty($fields)) {
            return false;
        }
        
        // Update slug if title changed
        if (isset($data['title'])) {
            $fields[] = "slug = ?";
            $params[] = $this->generateSlug($data['title']);
        }
        
        $params[] = $id;
        $query = "UPDATE courses SET " . implode(', ', $fields) . " WHERE id = ?";
        
        $stmt = $this->db->prepare($query);
        return $stmt->execute($params);
    }
    
    /**
     * Delete course
     */
    public function delete($id) {
        $stmt = $this->db->prepare("DELETE FROM courses WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
    /**
     * Generate URL-friendly slug
     */
    private function generateSlug($title) {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));
        
        // Check if slug exists
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM courses WHERE slug = ?");
        $stmt->execute([$slug]);
        
        if ($stmt->fetchColumn() > 0) {
            $slug .= '-' . time();
        }
        
        return $slug;
    }
    
    /**
     * Get course enrollment count
     */
    public function getEnrollmentCount($courseId) {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM enrollments WHERE course_id = ?");
        $stmt->execute([$courseId]);
        return $stmt->fetchColumn();
    }
}
