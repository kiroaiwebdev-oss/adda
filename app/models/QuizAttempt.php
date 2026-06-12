<?php
/**
 * QuizAttempt Model
 */

class QuizAttempt {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * Start new attempt
     */
    public function start($quizId, $userId) {
        // Get attempt number
        $stmt = $this->db->prepare("
            SELECT COALESCE(MAX(attempt_number), 0) + 1 as next_attempt 
            FROM quiz_attempts 
            WHERE quiz_id = ? AND user_id = ?
        ");
        $stmt->execute([$quizId, $userId]);
        $attemptNumber = $stmt->fetchColumn();
        
        $stmt = $this->db->prepare("
            INSERT INTO quiz_attempts (user_id, quiz_id, attempt_number, started_at) 
            VALUES (?, ?, ?, NOW())
        ");
        
        $stmt->execute([$userId, $quizId, $attemptNumber]);
        
        return $this->db->lastInsertId();
    }
    
    /**
     * Submit attempt
     */
    public function submit($attemptId, $score, $answers, $isPassed) {
        $stmt = $this->db->prepare("
            UPDATE quiz_attempts 
            SET score = ?, answers = ?, is_passed = ?, completed_at = NOW() 
            WHERE id = ?
        ");
        
        return $stmt->execute([
            $score,
            json_encode($answers),
            $isPassed ? 1 : 0,
            $attemptId
        ]);
    }
    
    /**
     * Get attempt by ID
     */
    public function findById($attemptId) {
        $stmt = $this->db->prepare("SELECT * FROM quiz_attempts WHERE id = ?");
        $stmt->execute([$attemptId]);
        $attempt = $stmt->fetch();
        
        if ($attempt && $attempt['answers']) {
            $attempt['answers'] = json_decode($attempt['answers'], true);
        }
        
        return $attempt;
    }
    
    /**
     * Get user attempts for quiz
     */
    public function getUserAttempts($quizId, $userId) {
        $stmt = $this->db->prepare("
            SELECT * FROM quiz_attempts 
            WHERE quiz_id = ? AND user_id = ? 
            ORDER BY attempt_number DESC
        ");
        $stmt->execute([$quizId, $userId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get all attempts for quiz (admin)
     */
    public function getQuizAttempts($quizId) {
        $stmt = $this->db->prepare("
            SELECT qa.*, u.name as user_name, u.email as user_email 
            FROM quiz_attempts qa 
            JOIN users u ON qa.user_id = u.id 
            WHERE qa.quiz_id = ? 
            ORDER BY qa.started_at DESC
        ");
        $stmt->execute([$quizId]);
        return $stmt->fetchAll();
    }
}
