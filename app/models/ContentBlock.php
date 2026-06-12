<?php
/**
 * ContentBlock Model
 */

class ContentBlock {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    public function create($topicId, $type, $content) {
        // Get next sort order
        $stmt = $this->db->prepare("SELECT COALESCE(MAX(sort_order), -1) + 1 as next_order FROM content_blocks WHERE topic_id = ?");
        $stmt->execute([$topicId]);
        $nextOrder = $stmt->fetchColumn();
        
        $stmt = $this->db->prepare("INSERT INTO content_blocks (topic_id, type, content, sort_order) VALUES (?, ?, ?, ?)");
        $stmt->execute([$topicId, $type, $content, $nextOrder]);
        
        return $this->db->lastInsertId();
    }
    
    public function update($blockId, $content) {
        $stmt = $this->db->prepare("UPDATE content_blocks SET content = ? WHERE id = ?");
        return $stmt->execute([$content, $blockId]);
    }
    
    public function delete($blockId) {
        $stmt = $this->db->prepare("DELETE FROM content_blocks WHERE id = ?");
        return $stmt->execute([$blockId]);
    }
    
    public function reorder($blockId, $newOrder) {
        $stmt = $this->db->prepare("UPDATE content_blocks SET sort_order = ? WHERE id = ?");
        return $stmt->execute([$newOrder, $blockId]);
    }
    
    public function findById($blockId) {
        $stmt = $this->db->prepare("SELECT * FROM content_blocks WHERE id = ?");
        $stmt->execute([$blockId]);
        return $stmt->fetch();
    }
}
