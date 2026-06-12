<?php
/**
 * EnrollmentController - Handles course enrollments
 */

class EnrollmentController {
    private $db;
    private $auth;
    
    public function __construct($db, $auth) {
        $this->db = $db;
        $this->auth = $auth;
    }
    
    /**
     * Enroll in course
     */
    public function enroll($data) {
        $validator = new Validator($data);
        $validator->validate([
            'course_id' => 'required|numeric'
        ]);
        
        if ($validator->fails()) {
            return Response::validationError($validator->errors());
        }
        
        $userId = $this->auth->id();
        $enrollmentModel = new Enrollment($this->db);
        
        // Check if course exists
        $courseModel = new Course($this->db);
        $course = $courseModel->findById($data['course_id']);
        
        if (!$course) {
            return Response::notFound('Course not found');
        }
        
        // Check if already enrolled
        if ($enrollmentModel->isEnrolled($userId, $data['course_id'])) {
            return Response::error('Already enrolled in this course', [], 409);
        }
        
        $result = $enrollmentModel->create($userId, $data['course_id']);
        
        if ($result) {
            return Response::success([], 'Successfully enrolled in course', 201);
        } else {
            return Response::error('Failed to enroll');
        }
    }
    
    /**
     * Get user enrollments
     */
    public function getMyEnrollments() {
        $userId = $this->auth->id();
        $enrollmentModel = new Enrollment($this->db);
        
        $enrollments = $enrollmentModel->getUserEnrollments($userId);
        
        return Response::success(['enrollments' => $enrollments]);
    }
}
