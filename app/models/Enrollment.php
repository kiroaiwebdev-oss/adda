<?php
/**
 * Enrollment Model
 */

class Enrollment {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * Enroll user in course
     */
    public function create($userId, $courseId) {
        // Check if already enrolled
        if ($this->isEnrolled($userId, $courseId)) {
            return false;
        }
        
        $stmt = $this->db->prepare("
            INSERT INTO enrollments (user_id, course_id, enrolled_at) 
            VALUES (?, ?, NOW())
        ");
        
        return $stmt->execute([$userId, $courseId]);
    }
    
    /**
     * Check if user is enrolled
     */
    public function isEnrolled($userId, $courseId) {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM enrollments 
            WHERE user_id = ? AND course_id = ?
        ");
        $stmt->execute([$userId, $courseId]);
        return $stmt->fetchColumn() > 0;
    }
    
    /**
     * Get user enrollments with error handling
     */
    public function getUserEnrollments($userId) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    e.id,
                    e.user_id,
                    e.course_id,
                    e.enrolled_at,
                    e.completed_at,
                    e.progress_percent,
                    c.id as course_id,
                    c.title, 
                    c.description, 
                    c.price,
                    COALESCE(
                        (SELECT COUNT(*) 
                         FROM topics t 
                         JOIN chapters ch ON t.chapter_id = ch.id 
                         WHERE ch.course_id = c.id), 
                        0
                    ) as total_topics,
                    COALESCE(
                        (SELECT COUNT(*) 
                         FROM topic_progress tp 
                         JOIN topics t ON tp.topic_id = t.id 
                         JOIN chapters ch ON t.chapter_id = ch.id 
                         WHERE tp.user_id = e.user_id 
                           AND ch.course_id = c.id 
                           AND tp.is_completed = 1), 
                        0
                    ) as completed_topics
                FROM enrollments e
                JOIN courses c ON e.course_id = c.id
                WHERE e.user_id = ?
                ORDER BY e.enrolled_at DESC
            ");
            
            $stmt->execute([$userId]);
            $enrollments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Calculate progress percentage
            foreach ($enrollments as &$enrollment) {
                $totalTopics = isset($enrollment['total_topics']) ? intval($enrollment['total_topics']) : 0;
                $completedTopics = isset($enrollment['completed_topics']) ? intval($enrollment['completed_topics']) : 0;
                
                if ($totalTopics > 0) {
                    $enrollment['progress_percent'] = ($completedTopics / $totalTopics) * 100;
                } else {
                    $enrollment['progress_percent'] = 0;
                }
            }
            
            return $enrollments;
            
        } catch (PDOException $e) {
            error_log("Enrollment fetch error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get enrollment by ID
     */
    public function findById($enrollmentId) {
        try {
            $stmt = $this->db->prepare("
                SELECT e.*, c.title as course_title, c.description 
                FROM enrollments e 
                JOIN courses c ON e.course_id = c.id 
                WHERE e.id = ?
            ");
            $stmt->execute([$enrollmentId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Find enrollment error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get enrollment with full course structure
     */
    public function getEnrollmentWithStructure($userId, $courseId) {
        try {
            $enrollment = [
                'user_id' => $userId,
                'course_id' => $courseId,
                'is_enrolled' => $this->isEnrolled($userId, $courseId)
            ];
            
            // Get course details
            $stmt = $this->db->prepare("SELECT * FROM courses WHERE id = ?");
            $stmt->execute([$courseId]);
            $enrollment['course'] = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$enrollment['course']) {
                return null;
            }
            
            // Get chapters with topics
            $stmt = $this->db->prepare("SELECT * FROM chapters WHERE course_id = ? ORDER BY sort_order ASC");
            $stmt->execute([$courseId]);
            $chapters = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($chapters as &$chapter) {
                // Get topics
                $stmt = $this->db->prepare("SELECT * FROM topics WHERE chapter_id = ? ORDER BY sort_order ASC");
                $stmt->execute([$chapter['id']]);
                $topics = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($topics as &$topic) {
                    // Get content blocks
                    $stmt = $this->db->prepare("SELECT * FROM content_blocks WHERE topic_id = ? ORDER BY sort_order ASC");
                    $stmt->execute([$topic['id']]);
                    $topic['content_blocks'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Get progress
                    $stmt = $this->db->prepare("SELECT * FROM topic_progress WHERE user_id = ? AND topic_id = ?");
                    $stmt->execute([$userId, $topic['id']]);
                    $progress = $stmt->fetch(PDO::FETCH_ASSOC);
                    $topic['progress'] = $progress ? $progress : null;
                }
                
                $chapter['topics'] = $topics;
            }
            
            $enrollment['chapters'] = $chapters;
            
            return $enrollment;
            
        } catch (PDOException $e) {
            error_log("Enrollment structure fetch error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Update enrollment completion
     */
    public function markAsCompleted($userId, $courseId) {
        try {
            $stmt = $this->db->prepare("
                UPDATE enrollments 
                SET completed_at = NOW(), progress_percent = 100 
                WHERE user_id = ? AND course_id = ?
            ");
            return $stmt->execute([$userId, $courseId]);
        } catch (PDOException $e) {
            error_log("Mark completed error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get enrollment count for a course
     */
    public function getCourseEnrollmentCount($courseId) {
        try {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM enrollments WHERE course_id = ?");
            $stmt->execute([$courseId]);
            return $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("Enrollment count error: " . $e->getMessage());
            return 0;
        }
    }
}
