<?php
/**
 * Question Model
 */

class Question {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * Add question to quiz
     */
    public function create($quizId, $data) {
        // Get next sort order
        $stmt = $this->db->prepare("SELECT COALESCE(MAX(sort_order), -1) + 1 as next_order FROM questions WHERE quiz_id = ?");
        $stmt->execute([$quizId]);
        $nextOrder = $stmt->fetchColumn();
        
        // Encode options as JSON
        $options = isset($data['options']) ? json_encode($data['options']) : null;
        
        $stmt = $this->db->prepare("
            INSERT INTO questions (quiz_id, question, type, options, correct_answer, sort_order) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $quizId,
            $data['question'],
            $data['type'],
            $options,
            $data['correct_answer'],
            $nextOrder
        ]);
        
        return $this->db->lastInsertId();
    }
    
    /**
     * Get all questions for a quiz
     */
    public function getByQuiz($quizId) {
        $stmt = $this->db->prepare("SELECT * FROM questions WHERE quiz_id = ? ORDER BY sort_order ASC");
        $stmt->execute([$quizId]);
        $questions = $stmt->fetchAll();
        
        // Decode options JSON
        foreach ($questions as &$question) {
            if ($question['options']) {
                $question['options'] = json_decode($question['options'], true);
            }
        }
        
        return $questions;
    }
    
    /**
     * Get question by ID
     */
    public function findById($questionId) {
        $stmt = $this->db->prepare("SELECT * FROM questions WHERE id = ?");
        $stmt->execute([$questionId]);
        $question = $stmt->fetch();
        
        if ($question && $question['options']) {
            $question['options'] = json_decode($question['options'], true);
        }
        
        return $question;
    }
    
    /**
     * Update question
     */
    public function update($questionId, $data) {
        $fields = [];
        $params = [];
        
        if (isset($data['question'])) {
            $fields[] = "question = ?";
            $params[] = $data['question'];
        }
        
        if (isset($data['type'])) {
            $fields[] = "type = ?";
            $params[] = $data['type'];
        }
        
        if (isset($data['options'])) {
            $fields[] = "options = ?";
            $params[] = json_encode($data['options']);
        }
        
        if (isset($data['correct_answer'])) {
            $fields[] = "correct_answer = ?";
            $params[] = $data['correct_answer'];
        }
        
        if (empty($fields)) return false;
        
        $params[] = $questionId;
        $query = "UPDATE questions SET " . implode(', ', $fields) . " WHERE id = ?";
        
        $stmt = $this->db->prepare($query);
        return $stmt->execute($params);
    }
    
    /**
     * Delete question
     */
    public function delete($questionId) {
        $stmt = $this->db->prepare("DELETE FROM questions WHERE id = ?");
        return $stmt->execute([$questionId]);
    }
    
    /**
     * Check if answer is correct
     */
    public function checkAnswer($questionId, $userAnswer) {
        $question = $this->findById($questionId);
        
        if (!$question) {
            return false;
        }
        
        return trim(strtolower($userAnswer)) === trim(strtolower($question['correct_answer']));
    }
}
