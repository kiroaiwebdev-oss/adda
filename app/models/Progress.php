<?php
/**
 * Progress Model
 */

class Progress {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * Mark topic as started
     */
    public function startTopic($userId, $topicId) {
        // Check if progress exists
        $stmt = $this->db->prepare("SELECT id FROM topic_progress WHERE user_id = ? AND topic_id = ?");
        $stmt->execute([$userId, $topicId]);
        
        if ($stmt->fetch()) {
            return true; // Already started
        }
        
        $stmt = $this->db->prepare("
            INSERT INTO topic_progress (user_id, topic_id, started_at) 
            VALUES (?, ?, NOW())
        ");
        
        return $stmt->execute([$userId, $topicId]);
    }
    
    /**
     * Mark topic as completed
     */
    public function completeTopic($userId, $topicId) {
        $stmt = $this->db->prepare("
            INSERT INTO topic_progress (user_id, topic_id, started_at, completed_at, completed) 
            VALUES (?, ?, NOW(), NOW(), 1)
            ON DUPLICATE KEY UPDATE completed_at = NOW(), completed = 1
        ");
        
        return $stmt->execute([$userId, $topicId]);
    }
    
    /**
     * Get topic progress
     */
    public function getTopicProgress($userId, $topicId) {
        $stmt = $this->db->prepare("SELECT * FROM topic_progress WHERE user_id = ? AND topic_id = ?");
        $stmt->execute([$userId, $topicId]);
        return $stmt->fetch();
    }
    
    /**
     * Get course progress summary
     */
    public function getCourseProgress($userId, $courseId) {
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(DISTINCT t.id) as total_topics,
                COUNT(DISTINCT tp.topic_id) as started_topics,
                SUM(CASE WHEN tp.completed = 1 THEN 1 ELSE 0 END) as completed_topics
            FROM chapters ch
            JOIN topics t ON ch.id = t.chapter_id
            LEFT JOIN topic_progress tp ON t.id = tp.topic_id AND tp.user_id = ?
            WHERE ch.course_id = ?
        ");
        $stmt->execute([$userId, $courseId]);
        $progress = $stmt->fetch();
        
        if ($progress['total_topics'] > 0) {
            $progress['progress_percent'] = ($progress['completed_topics'] / $progress['total_topics']) * 100;
        } else {
            $progress['progress_percent'] = 0;
        }
        
        return $progress;
    }
    
    /**
     * Check if course is completed
     */
    public function isCourseCompleted($userId, $courseId) {
        $progress = $this->getCourseProgress($userId, $courseId);
        return $progress['total_topics'] > 0 && $progress['completed_topics'] == $progress['total_topics'];
    }
    
    /**
     * Get user's overall statistics
     */
    public function getUserStats($userId) {
        // Total enrollments
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM enrollments WHERE user_id = ?");
        $stmt->execute([$userId]);
        $totalEnrollments = $stmt->fetchColumn();
        
        // Completed courses
        $stmt = $this->db->prepare("
            SELECT COUNT(DISTINCT e.course_id) 
            FROM enrollments e
            WHERE e.user_id = ? AND e.completed_at IS NOT NULL
        ");
        $stmt->execute([$userId]);
        $completedCourses = $stmt->fetchColumn();
        
        // Total certificates
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM certificates WHERE user_id = ? AND revoked = 0");
        $stmt->execute([$userId]);
        $totalCertificates = $stmt->fetchColumn();
        
        // Quiz attempts
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM quiz_attempts WHERE user_id = ?");
        $stmt->execute([$userId]);
        $totalQuizAttempts = $stmt->fetchColumn();
        
        // Average quiz score
        $stmt = $this->db->prepare("SELECT AVG(score) FROM quiz_attempts WHERE user_id = ? AND completed_at IS NOT NULL");
        $stmt->execute([$userId]);
        $avgQuizScore = $stmt->fetchColumn() ?: 0;
        
        return [
            'total_enrollments' => $totalEnrollments,
            'completed_courses' => $completedCourses,
            'in_progress' => $totalEnrollments - $completedCourses,
            'total_certificates' => $totalCertificates,
            'total_quiz_attempts' => $totalQuizAttempts,
            'avg_quiz_score' => round($avgQuizScore, 2)
        ];
    }
}
