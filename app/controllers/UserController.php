<?php
/**
 * UserController - Handles user management
 */

class UserController {
    private $db;
    private $userModel;
    
    public function __construct($db) {
        $this->db = $db;
        $this->userModel = new User($db);
    }
    
    /**
     * Update user
     */
    public function update($data) {
        $validator = new Validator($data);
        $validator->validate([
            'user_id' => 'required|numeric',
            'name' => 'required|min:2',
            'email' => 'required|email',
            'status' => 'required'
        ]);
        
        if ($validator->fails()) {
            return Response::validationError($validator->errors());
        }
        
        $result = $this->userModel->update($data['user_id'], [
            'name' => Security::clean($data['name']),
            'email' => Security::clean($data['email']),
            'status' => Security::clean($data['status'])
        ]);
        
        if ($result) {
            return Response::success([], 'User updated successfully');
        } else {
            return Response::error('Failed to update user');
        }
    }
    
    /**
     * Update user status
     */
    public function updateStatus($data) {
        $validator = new Validator($data);
        $validator->validate([
            'user_id' => 'required|numeric',
            'status' => 'required'
        ]);
        
        if ($validator->fails()) {
            return Response::validationError($validator->errors());
        }
        
        $result = $this->userModel->update($data['user_id'], [
            'status' => Security::clean($data['status'])
        ]);
        
        if ($result) {
            return Response::success([], 'Status updated successfully');
        } else {
            return Response::error('Failed to update status');
        }
    }
    
    /**
     * Delete user
     */
    public function delete($data) {
        $validator = new Validator($data);
        $validator->validate(['user_id' => 'required|numeric']);
        
        if ($validator->fails()) {
            return Response::validationError($validator->errors());
        }
        
        $result = $this->userModel->delete($data['user_id']);
        
        if ($result) {
            return Response::success([], 'User deleted successfully');
        } else {
            return Response::error('Failed to delete user');
        }
    }
}
