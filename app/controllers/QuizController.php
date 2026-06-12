<?php
/**
 * QuizController - Handles quiz management
 */

class QuizController {
    private $db;
    private $auth;
    
    public function __construct($db, $auth) {
        $this->db = $db;
        $this->auth = $auth;
    }
    
    /**
     * Create quiz
     */
    public function create($data) {
        $validator = new Validator($data);
        $validator->validate([
            'content_block_id' => 'required|numeric',
            'title' => 'required|min:3'
        ]);
        
        if ($validator->fails()) {
            return Response::validationError($validator->errors());
        }
        
        $quizModel = new Quiz($this->db);
        
        // Check if quiz already exists for this content block
        $existing = $quizModel->findByContentBlock($data['content_block_id']);
        if ($existing) {
            return Response::error('Quiz already exists for this content block', [], 409);
        }
        
        $quizId = $quizModel->create($data['content_block_id'], [
            'title' => Security::clean($data['title']),
            'time_limit' => isset($data['time_limit']) ? (int)$data['time_limit'] : 0,
            'pass_score' => isset($data['pass_score']) ? (int)$data['pass_score'] : 70,
            'max_attempts' => isset($data['max_attempts']) ? (int)$data['max_attempts'] : 0
        ]);
        
        if ($quizId) {
            return Response::success(['quiz_id' => $quizId], 'Quiz created successfully', 201);
        } else {
            return Response::error('Failed to create quiz');
        }
    }
    
    /**
     * Update quiz
     */
    public function update($data) {
        $validator = new Validator($data);
        $validator->validate([
            'quiz_id' => 'required|numeric',
            'title' => 'required|min:3'
        ]);
        
        if ($validator->fails()) {
            return Response::validationError($validator->errors());
        }
        
        $quizModel = new Quiz($this->db);
        $result = $quizModel->update($data['quiz_id'], [
            'title' => Security::clean($data['title']),
            'time_limit' => isset($data['time_limit']) ? (int)$data['time_limit'] : 0,
            'pass_score' => isset($data['pass_score']) ? (int)$data['pass_score'] : 70,
            'max_attempts' => isset($data['max_attempts']) ? (int)$data['max_attempts'] : 0
        ]);
        
        if ($result) {
            return Response::success([], 'Quiz updated successfully');
        } else {
            return Response::error('Failed to update quiz');
        }
    }
    
    /**
     * Delete quiz
     */
    public function delete($data) {
        $validator = new Validator($data);
        $validator->validate(['quiz_id' => 'required|numeric']);
        
        if ($validator->fails()) {
            return Response::validationError($validator->errors());
        }
        
        $quizModel = new Quiz($this->db);
        $result = $quizModel->delete($data['quiz_id']);
        
        if ($result) {
            return Response::success([], 'Quiz deleted successfully');
        } else {
            return Response::error('Failed to delete quiz');
        }
    }
    
    /**
     * Add question to quiz
     */
    public function addQuestion($data) {
        $validator = new Validator($data);
        $validator->validate([
            'quiz_id' => 'required|numeric',
            'question' => 'required|min:5',
            'type' => 'required',
            'correct_answer' => 'required'
        ]);
        
        if ($validator->fails()) {
            return Response::validationError($validator->errors());
        }
        
        $questionModel = new Question($this->db);
        $questionId = $questionModel->create($data['quiz_id'], [
            'question' => Security::clean($data['question']),
            'type' => $data['type'],
            'options' => isset($data['options']) ? $data['options'] : null,
            'correct_answer' => Security::clean($data['correct_answer'])
        ]);
        
        if ($questionId) {
            return Response::success(['question_id' => $questionId], 'Question added successfully', 201);
        } else {
            return Response::error('Failed to add question');
        }
    }
    
    /**
     * Update question
     */
    public function updateQuestion($data) {
        $validator = new Validator($data);
        $validator->validate([
            'question_id' => 'required|numeric',
            'question' => 'required|min:5'
        ]);
        
        if ($validator->fails()) {
            return Response::validationError($validator->errors());
        }
        
        $questionModel = new Question($this->db);
        $result = $questionModel->update($data['question_id'], [
            'question' => Security::clean($data['question']),
            'type' => isset($data['type']) ? $data['type'] : null,
            'options' => isset($data['options']) ? $data['options'] : null,
            'correct_answer' => isset($data['correct_answer']) ? Security::clean($data['correct_answer']) : null
        ]);
        
        if ($result) {
            return Response::success([], 'Question updated successfully');
        } else {
            return Response::error('Failed to update question');
        }
    }
    
    /**
     * Delete question
     */
    public function deleteQuestion($data) {
        $validator = new Validator($data);
        $validator->validate(['question_id' => 'required|numeric']);
        
        if ($validator->fails()) {
            return Response::validationError($validator->errors());
        }
        
        $questionModel = new Question($this->db);
        $result = $questionModel->delete($data['question_id']);
        
        if ($result) {
            return Response::success([], 'Question deleted successfully');
        } else {
            return Response::error('Failed to delete question');
        }
    }
    
    /**
     * Submit quiz attempt (for learners)
     */
    public function submitAttempt($data) {
        $validator = new Validator($data);
        $validator->validate([
            'quiz_id' => 'required|numeric',
            'answers' => 'required'
        ]);
        
        if ($validator->fails()) {
            return Response::validationError($validator->errors());
        }
        
        $userId = $this->auth->id();
        $quizModel = new Quiz($this->db);
        $questionModel = new Question($this->db);
        $attemptModel = new QuizAttempt($this->db);
        
        // Get quiz details
        $quiz = $quizModel->findById($data['quiz_id']);
        if (!$quiz) {
            return Response::notFound('Quiz not found');
        }
        
        // Check max attempts
        if ($quiz['max_attempts'] > 0) {
            $attemptCount = $quizModel->getUserAttemptCount($quiz['id'], $userId);
            if ($attemptCount >= $quiz['max_attempts']) {
                return Response::error('Maximum attempts reached', [], 403);
            }
        }
        
        // Get all questions
        $questions = $questionModel->getByQuiz($quiz['id']);
        
        // Calculate score
        $correctCount = 0;
        $totalQuestions = count($questions);
        
        foreach ($questions as $question) {
            $userAnswer = $data['answers'][$question['id']] ?? '';
            if ($questionModel->checkAnswer($question['id'], $userAnswer)) {
                $correctCount++;
            }
        }
        
        $score = $totalQuestions > 0 ? ($correctCount / $totalQuestions) * 100 : 0;
        $isPassed = $score >= $quiz['pass_score'];
        
        // Start and submit attempt
        $attemptId = $attemptModel->start($quiz['id'], $userId);
        $attemptModel->submit($attemptId, $score, $data['answers'], $isPassed);
        
        return Response::success([
            'attempt_id' => $attemptId,
            'score' => round($score, 2),
            'correct_count' => $correctCount,
            'total_questions' => $totalQuestions,
            'is_passed' => $isPassed,
            'pass_score' => $quiz['pass_score']
        ], 'Quiz submitted successfully');
    }
}
