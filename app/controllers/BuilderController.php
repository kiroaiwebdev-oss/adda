<?php
/**
 * BuilderController - Handles course builder operations
 */

class BuilderController {
    private $db;
    private $auth;
    
    public function __construct($db, $auth) {
        $this->db = $db;
        $this->auth = $auth;
    }
    
    /**
     * Add Chapter
     */
    public function addChapter($data) {
        $validator = new Validator($data);
        $validator->validate([
            'course_id' => 'required|numeric',
            'title' => 'required|min:2'
        ]);
        
        if ($validator->fails()) {
            return Response::validationError($validator->errors());
        }
        
        $chapterModel = new Chapter($this->db);
        $chapterId = $chapterModel->create(
            $data['course_id'],
            Security::clean($data['title'])
        );
        
        if ($chapterId) {
            return Response::success(['chapter_id' => $chapterId], 'Chapter added successfully', 201);
        } else {
            return Response::error('Failed to add chapter');
        }
    }
    
    /**
     * Update Chapter
     */
    public function updateChapter($data) {
        $validator = new Validator($data);
        $validator->validate([
            'chapter_id' => 'required|numeric',
            'title' => 'required|min:2'
        ]);
        
        if ($validator->fails()) {
            return Response::validationError($validator->errors());
        }
        
        $chapterModel = new Chapter($this->db);
        $result = $chapterModel->update(
            $data['chapter_id'],
            Security::clean($data['title'])
        );
        
        if ($result) {
            return Response::success([], 'Chapter updated successfully');
        } else {
            return Response::error('Failed to update chapter');
        }
    }
    
    /**
     * Delete Chapter
     */
    public function deleteChapter($data) {
        $validator = new Validator($data);
        $validator->validate(['chapter_id' => 'required|numeric']);
        
        if ($validator->fails()) {
            return Response::validationError($validator->errors());
        }
        
        $chapterModel = new Chapter($this->db);
        $result = $chapterModel->delete($data['chapter_id']);
        
        if ($result) {
            return Response::success([], 'Chapter deleted successfully');
        } else {
            return Response::error('Failed to delete chapter');
        }
    }
    
    /**
     * Add Topic
     */
    public function addTopic($data) {
        $validator = new Validator($data);
        $validator->validate([
            'chapter_id' => 'required|numeric',
            'title' => 'required|min:2'
        ]);
        
        if ($validator->fails()) {
            return Response::validationError($validator->errors());
        }
        
        $topicModel = new Topic($this->db);
        $topicId = $topicModel->create(
            $data['chapter_id'],
            Security::clean($data['title']),
            isset($data['is_mandatory']) ? (bool)$data['is_mandatory'] : true
        );
        
        if ($topicId) {
            return Response::success(['topic_id' => $topicId], 'Topic added successfully', 201);
        } else {
            return Response::error('Failed to add topic');
        }
    }
    
    /**
     * Update Topic
     */
    public function updateTopic($data) {
        $validator = new Validator($data);
        $validator->validate([
            'topic_id' => 'required|numeric',
            'title' => 'required|min:2'
        ]);
        
        if ($validator->fails()) {
            return Response::validationError($validator->errors());
        }
        
        $topicModel = new Topic($this->db);
        $result = $topicModel->update($data['topic_id'], [
            'title' => Security::clean($data['title']),
            'is_mandatory' => isset($data['is_mandatory']) ? (bool)$data['is_mandatory'] : true
        ]);
        
        if ($result) {
            return Response::success([], 'Topic updated successfully');
        } else {
            return Response::error('Failed to update topic');
        }
    }
    
    /**
     * Delete Topic
     */
    public function deleteTopic($data) {
        $validator = new Validator($data);
        $validator->validate(['topic_id' => 'required|numeric']);
        
        if ($validator->fails()) {
            return Response::validationError($validator->errors());
        }
        
        $topicModel = new Topic($this->db);
        $result = $topicModel->delete($data['topic_id']);
        
        if ($result) {
            return Response::success([], 'Topic deleted successfully');
        } else {
            return Response::error('Failed to delete topic');
        }
    }
    
    /**
     * Add Content Block
     */
    public function addContent($data) {
        $validator = new Validator($data);
        $validator->validate([
            'topic_id' => 'required|numeric',
            'type' => 'required'
        ]);
        
        if ($validator->fails()) {
            return Response::validationError($validator->errors());
        }
        
        $contentModel = new ContentBlock($this->db);
        $blockId = $contentModel->create(
            $data['topic_id'],
            $data['type'],
            $data['content'] ?? ''
        );
        
        if ($blockId) {
            return Response::success(['content_block_id' => $blockId], 'Content added successfully', 201);
        } else {
            return Response::error('Failed to add content');
        }
    }
    
    /**
     * Delete Content Block
     */
    public function deleteContent($data) {
        $validator = new Validator($data);
        $validator->validate(['content_block_id' => 'required|numeric']);
        
        if ($validator->fails()) {
            return Response::validationError($validator->errors());
        }
        
        $contentModel = new ContentBlock($this->db);
        $result = $contentModel->delete($data['content_block_id']);
        
        if ($result) {
            return Response::success([], 'Content deleted successfully');
        } else {
            return Response::error('Failed to delete content');
        }
    }
}
