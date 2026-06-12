<?php
/**
 * ProgressController - Handles progress tracking
 */

class ProgressController {
    private $db;
    private $auth;
    
    public function __construct($db, $auth) {
        $this->db = $db;
        $this->auth = $auth;
    }
    
    /**
     * Start topic
     */
    public function startTopic($data) {
        $validator = new Validator($data);
        $validator->validate([
            'topic_id' => 'required|numeric'
        ]);
        
        if ($validator->fails()) {
            return Response::validationError($validator->errors());
        }
        
        $userId = $this->auth->id();
        $progressModel = new Progress($this->db);
        
        $result = $progressModel->startTopic($userId, $data['topic_id']);
        
        if ($result) {
            return Response::success([], 'Topic started');
        } else {
            return Response::error('Failed to start topic');
        }
    }
    
    /**
     * Complete topic
     */
    public function completeTopic($data) {
        $validator = new Validator($data);
        $validator->validate([
            'topic_id' => 'required|numeric'
        ]);
        
        if ($validator->fails()) {
            return Response::validationError($validator->errors());
        }
        
        $userId = $this->auth->id();
        $progressModel = new Progress($this->db);
        
        $result = $progressModel->completeTopic($userId, $data['topic_id']);
        
        if ($result) {
            // Check if course is completed
            $stmt = $this->db->prepare("
                SELECT ch.course_id 
                FROM topics t 
                JOIN chapters ch ON t.chapter_id = ch.id 
                WHERE t.id = ?
            ");
            $stmt->execute([$data['topic_id']]);
            $courseId = $stmt->fetchColumn();
            
            if ($progressModel->isCourseCompleted($userId, $courseId)) {
                // Mark enrollment as completed
                $stmt = $this->db->prepare("
                    UPDATE enrollments 
                    SET completed_at = NOW() 
                    WHERE user_id = ? AND course_id = ? AND completed_at IS NULL
                ");
                $stmt->execute([$userId, $courseId]);
                
                return Response::success(['course_completed' => true], 'Topic completed! Course finished!');
            }
            
            return Response::success(['course_completed' => false], 'Topic completed');
        } else {
            return Response::error('Failed to complete topic');
        }
    }
    
    /**
     * Get course progress
     */
    public function getCourseProgress($data) {
        $validator = new Validator($data);
        $validator->validate([
            'course_id' => 'required|numeric'
        ]);
        
        if ($validator->fails()) {
            return Response::validationError($validator->errors());
        }
        
        $userId = $this->auth->id();
        $progressModel = new Progress($this->db);
        
        $progress = $progressModel->getCourseProgress($userId, $data['course_id']);
        
        return Response::success(['progress' => $progress]);
    }
}
