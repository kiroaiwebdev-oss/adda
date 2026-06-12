<?php
/**
 * CourseController - Handles course management
 */

class CourseController {
    private $db;
    private $courseModel;
    private $auth;
    
    public function __construct($db, $auth) {
        $this->db = $db;
        $this->courseModel = new Course($db);
        $this->auth = $auth;
    }
    
    /**
     * Create course
     */
    public function create($data) {
        $validator = new Validator($data);
        $validator->validate([
            'title' => 'required|min:3',
            'description' => 'required',
            'price' => 'required|numeric',
            'status' => 'required'
        ]);
        
        if ($validator->fails()) {
            return Response::validationError($validator->errors());
        }
        
        $data['created_by'] = $this->auth->id();
        
        $courseId = $this->courseModel->create([
            'title' => Security::clean($data['title']),
            'description' => Security::clean($data['description']),
            'price' => floatval($data['price']),
            'status' => Security::clean($data['status']),
            'created_by' => $data['created_by']
        ]);
        
        if ($courseId) {
            return Response::success(['course_id' => $courseId], 'Course created successfully', 201);
        } else {
            return Response::error('Failed to create course');
        }
    }
    
    /**
     * Update course
     */
    public function update($data) {
        $validator = new Validator($data);
        $validator->validate([
            'course_id' => 'required|numeric'
        ]);
        
        if ($validator->fails()) {
            return Response::validationError($validator->errors());
        }
        
        $updateData = [];
        $allowedFields = ['title', 'description', 'price', 'status'];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updateData[$field] = Security::clean($data[$field]);
            }
        }
        
        $result = $this->courseModel->update($data['course_id'], $updateData);
        
        if ($result) {
            return Response::success([], 'Course updated successfully');
        } else {
            return Response::error('Failed to update course');
        }
    }
    
    /**
     * Delete course
     */
    public function delete($data) {
        $validator = new Validator($data);
        $validator->validate(['course_id' => 'required|numeric']);
        
        if ($validator->fails()) {
            return Response::validationError($validator->errors());
        }
        
        $result = $this->courseModel->delete($data['course_id']);
        
        if ($result) {
            return Response::success([], 'Course deleted successfully');
        } else {
            return Response::error('Failed to delete course');
        }
    }
}
