<?php
/**
 * Quiz Model
 */

class Quiz {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * Create quiz for content block
     */
    public function create($contentBlockId, $data) {
        $stmt = $this->db->prepare("
            INSERT INTO quizzes (content_block_id, title, time_limit, pass_score, max_attempts) 
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $contentBlockId,
            $data['title'],
            $data['time_limit'] ?? 0,
            $data['pass_score'] ?? 70,
            $data['max_attempts'] ?? 0
        ]);
        
        return $this->db->lastInsertId();
    }
    
    /**
     * Get quiz by content block ID
     */
    public function findByContentBlock($contentBlockId) {
        $stmt = $this->db->prepare("SELECT * FROM quizzes WHERE content_block_id = ? LIMIT 1");
        $stmt->execute([$contentBlockId]);
        return $stmt->fetch();
    }
    
    /**
     * Get quiz by ID
     */
    public function findById($quizId) {
        $stmt = $this->db->prepare("SELECT * FROM quizzes WHERE id = ?");
        $stmt->execute([$quizId]);
        return $stmt->fetch();
    }
    
    /**
     * Update quiz
     */
    public function update($quizId, $data) {
        $fields = [];
        $params = [];
        
        $allowedFields = ['title', 'time_limit', 'pass_score', 'max_attempts'];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = ?";
                $params[] = $data[$field];
            }
        }
        
        if (empty($fields)) return false;
        
        $params[] = $quizId;
        $query = "UPDATE quizzes SET " . implode(', ', $fields) . " WHERE id = ?";
        
        $stmt = $this->db->prepare($query);
        return $stmt->execute($params);
    }
    
    /**
     * Delete quiz
     */
    public function delete($quizId) {
        $stmt = $this->db->prepare("DELETE FROM quizzes WHERE id = ?");
        return $stmt->execute([$quizId]);
    }
    
    /**
     * Get all quizzes with course info
     */
    public function getAllWithCourseInfo() {
        $stmt = $this->db->query("
            SELECT q.*, 
                   c.title as course_title,
                   ch.title as chapter_title,
                   t.title as topic_title,
                   (SELECT COUNT(*) FROM questions WHERE quiz_id = q.id) as question_count
            FROM quizzes q
            JOIN content_blocks cb ON q.content_block_id = cb.id
            JOIN topics t ON cb.topic_id = t.id
            JOIN chapters ch ON t.chapter_id = ch.id
            JOIN courses c ON ch.course_id = c.id
            ORDER BY q.created_at DESC
        ");
        
        return $stmt->fetchAll();
    }
    
    /**
     * Get user attempts count
     */
    public function getUserAttemptCount($quizId, $userId) {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM quiz_attempts 
            WHERE quiz_id = ? AND user_id = ?
        ");
        $stmt->execute([$quizId, $userId]);
        return $stmt->fetchColumn();
    }
    
    /**
     * Get user's best score
     */
    public function getUserBestScore($quizId, $userId) {
        $stmt = $this->db->prepare("
            SELECT MAX(score) FROM quiz_attempts 
            WHERE quiz_id = ? AND user_id = ? AND completed_at IS NOT NULL
        ");
        $stmt->execute([$quizId, $userId]);
        return $stmt->fetchColumn() ?: 0;
    }
}
