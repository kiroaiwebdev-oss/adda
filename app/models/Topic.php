<?php
/**
 * Topic Model
 */

class Topic {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    public function create($chapterId, $title, $isMandatory = true) {
        // Get next sort order
        $stmt = $this->db->prepare("SELECT COALESCE(MAX(sort_order), -1) + 1 as next_order FROM topics WHERE chapter_id = ?");
        $stmt->execute([$chapterId]);
        $nextOrder = $stmt->fetchColumn();
        
        $stmt = $this->db->prepare("INSERT INTO topics (chapter_id, title, is_mandatory, sort_order) VALUES (?, ?, ?, ?)");
        $stmt->execute([$chapterId, $title, $isMandatory ? 1 : 0, $nextOrder]);
        
        return $this->db->lastInsertId();
    }
    
    public function update($topicId, $data) {
        $fields = [];
        $params = [];
        
        if (isset($data['title'])) {
            $fields[] = "title = ?";
            $params[] = $data['title'];
        }
        
        if (isset($data['is_mandatory'])) {
            $fields[] = "is_mandatory = ?";
            $params[] = $data['is_mandatory'] ? 1 : 0;
        }
        
        if (empty($fields)) return false;
        
        $params[] = $topicId;
        $query = "UPDATE topics SET " . implode(', ', $fields) . " WHERE id = ?";
        
        $stmt = $this->db->prepare($query);
        return $stmt->execute($params);
    }
    
    public function delete($topicId) {
        $stmt = $this->db->prepare("DELETE FROM topics WHERE id = ?");
        return $stmt->execute([$topicId]);
    }
    
    public function reorder($topicId, $newOrder) {
        $stmt = $this->db->prepare("UPDATE topics SET sort_order = ? WHERE id = ?");
        return $stmt->execute([$newOrder, $topicId]);
    }
}
