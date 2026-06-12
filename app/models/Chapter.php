<?php
/**
 * Chapter Model
 */

class Chapter {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    public function create($courseId, $title) {
        // Get next sort order
        $stmt = $this->db->prepare("SELECT COALESCE(MAX(sort_order), -1) + 1 as next_order FROM chapters WHERE course_id = ?");
        $stmt->execute([$courseId]);
        $nextOrder = $stmt->fetchColumn();
        
        $stmt = $this->db->prepare("INSERT INTO chapters (course_id, title, sort_order) VALUES (?, ?, ?)");
        $stmt->execute([$courseId, $title, $nextOrder]);
        
        return $this->db->lastInsertId();
    }
    
    public function update($chapterId, $title) {
        $stmt = $this->db->prepare("UPDATE chapters SET title = ? WHERE id = ?");
        return $stmt->execute([$title, $chapterId]);
    }
    
    public function delete($chapterId) {
        $stmt = $this->db->prepare("DELETE FROM chapters WHERE id = ?");
        return $stmt->execute([$chapterId]);
    }
    
    public function reorder($chapterId, $newOrder) {
        $stmt = $this->db->prepare("UPDATE chapters SET sort_order = ? WHERE id = ?");
        return $stmt->execute([$newOrder, $chapterId]);
    }
}
