<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

$dbConfig = require __DIR__ . '/../../config/database.php';
$dsn = sprintf("mysql:host=%s;port=%s;dbname=%s;charset=%s",
    $dbConfig['host'], $dbConfig['port'], $dbConfig['database'], $dbConfig['charset']
);

try {
    $db = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options']);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
} catch (PDOException $e) {
    die("Database connection failed");
}

spl_autoload_register(function ($class) {
    $paths = [
        __DIR__ . '/../../core/' . $class . '.php',
        __DIR__ . '/../../models/' . $class . '.php',
    ];
    foreach ($paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            return;
        }
    }
});

$auth = new Auth($db);
$auth->requireAuth();

if ($auth->isAdmin()) {
    header('Location: /app/views/admin/dashboard.php');
    exit;
}

$userId = $auth->id();
$quizId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$quizId) {
    header('Location: /app/views/learner/my-courses.php');
    exit;
}

// Get quiz details
$stmt = $db->prepare("SELECT * FROM quizzes WHERE id = ?");
$stmt->execute([$quizId]);
$quiz = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$quiz) {
    header('Location: /app/views/learner/my-courses.php?error=quiz_not_found');
    exit;
}

// Get topic and course info
$stmt = $db->prepare("
    SELECT t.id as topic_id, t.title as topic_title, c.id as course_id, c.title as course_title
    FROM content_blocks cb
    INNER JOIN topics t ON cb.topic_id = t.id
    INNER JOIN chapters ch ON t.chapter_id = ch.id
    INNER JOIN courses c ON ch.course_id = c.id
    WHERE cb.id = ?
");
$stmt->execute([$quiz['content_block_id']]);
$topicInfo = $stmt->fetch(PDO::FETCH_ASSOC);

$topicId = $topicInfo['topic_id'] ?? null;
$courseId = $topicInfo['course_id'] ?? null;

// Check previous attempts
$stmt = $db->prepare("SELECT COUNT(*) as attempt_count FROM quiz_attempts WHERE user_id = ? AND quiz_id = ?");
$stmt->execute([$userId, $quizId]);
$attemptCount = $stmt->fetchColumn();

// ✅ FIXED: Unlimited attempts if max_attempts is 0 or NULL
$maxAttempts = $quiz['max_attempts'] ?? 0;
$maxReached = ($maxAttempts > 0) && ($attemptCount >= $maxAttempts);

// Check if already passed
$stmt = $db->prepare("SELECT * FROM quiz_attempts WHERE user_id = ? AND quiz_id = ? AND status = 'passed' LIMIT 1");
$stmt->execute([$userId, $quizId]);
$passedAttempt = $stmt->fetch(PDO::FETCH_ASSOC);

// ✅ FIXED: Get questions without reference issue
$stmt = $db->prepare("SELECT * FROM quiz_questions WHERE quiz_id = ? ORDER BY sort_order ASC");
$stmt->execute([$quizId]);
$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ✅ FIXED: Parse options without using reference (&)
foreach ($questions as $key => $question) {
    $questions[$key]['options_array'] = json_decode($question['options'], true) ?: [];
}

$quizTimeLimit = $quiz['time_limit'] ?? 30;
$quizPassScore = $quiz['pass_score'] ?? 70;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($quiz['title']); ?> - Quiz</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Poppins:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <script src="https://cdn.tailwindcss.com"></script>
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#f0fdf4', 100: '#dcfce7', 200: '#bbf7d0', 300: '#86efac',
                            400: '#4ade80', 500: '#22c55e', 600: '#16a34a', 700: '#15803d',
                            800: '#166534', 900: '#14532d',
                        }
                    }
                }
            }
        }
    </script>
    
    <style>
        body { font-family: 'Inter', sans-serif; }
        h1, h2, h3 { font-family: 'Poppins', sans-serif; font-weight: 700; }
        .option-item { cursor: pointer; transition: all 0.2s; }
        .option-item:hover { background: #f3f4f6; }
        .option-item.selected { background: #dcfce7; border-color: #22c55e; }
    </style>
</head>
<body class="bg-gray-50">

<div class="min-h-screen py-8 px-4">
    <div class="max-w-4xl mx-auto">
        
        <?php if ($passedAttempt): ?>
            <!-- Already Passed -->
            <div class="bg-white rounded-2xl shadow-lg p-12 text-center">
                <div class="w-24 h-24 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-6">
                    <svg class="w-12 h-12 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                </div>
                <h1 class="text-3xl font-bold text-gray-900 mb-4">✅ Quiz Already Passed!</h1>
                <p class="text-gray-600 mb-2">Score: <?php echo number_format($passedAttempt['score'], 1); ?>%</p>
                <p class="text-gray-600 mb-8">Passed on: <?php echo date('M d, Y', strtotime($passedAttempt['completed_at'])); ?></p>
                <a href="/app/views/learner/course-player.php?id=<?php echo $courseId; ?>&topic=<?php echo $topicId; ?>" 
                   class="inline-block bg-primary-600 hover:bg-primary-700 text-white px-8 py-3 rounded-lg font-semibold">
                    Back to Course
                </a>
            </div>
            
        <?php elseif ($maxReached): ?>
            <!-- Max Attempts Reached -->
            <div class="bg-white rounded-2xl shadow-lg p-12 text-center">
                <div class="w-24 h-24 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-6">
                    <svg class="w-12 h-12 text-red-600" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                    </svg>
                </div>
                <h1 class="text-3xl font-bold text-gray-900 mb-4">❌ Maximum Attempts Reached</h1>
                <p class="text-gray-600 mb-8">You've used all <?php echo $maxAttempts; ?> attempts for this quiz. Please contact your instructor.</p>
                <a href="/app/views/learner/my-courses.php" class="inline-block bg-gray-600 hover:bg-gray-700 text-white px-8 py-3 rounded-lg font-semibold">
                    Back to Courses
                </a>
            </div>
            
        <?php elseif (empty($questions)): ?>
            <!-- No Questions -->
            <div class="bg-white rounded-2xl shadow-lg p-12 text-center">
                <div class="w-24 h-24 bg-yellow-100 rounded-full flex items-center justify-center mx-auto mb-6">
                    <svg class="w-12 h-12 text-yellow-600" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                    </svg>
                </div>
                <h1 class="text-3xl font-bold text-gray-900 mb-4">⚠️ No Questions Available</h1>
                <p class="text-gray-600 mb-8">This quiz doesn't have any questions yet.</p>
                <a href="/app/views/learner/my-courses.php" class="inline-block bg-primary-600 hover:bg-primary-700 text-white px-8 py-3 rounded-lg font-semibold">
                    Back to Courses
                </a>
            </div>
            
        <?php else: ?>
            <!-- Quiz Form -->
            <div class="bg-white rounded-2xl shadow-lg p-8">
                <div class="flex items-center justify-between mb-8 pb-6 border-b">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900 mb-2"><?php echo htmlspecialchars($quiz['title']); ?></h1>
                        <p class="text-gray-600">
                            <?php echo count($questions); ?> Questions
                            <?php if ($maxAttempts > 0): ?>
                                | Attempt <?php echo $attemptCount + 1; ?> of <?php echo $maxAttempts; ?>
                            <?php else: ?>
                                | Attempt <?php echo $attemptCount + 1; ?> (Unlimited)
                            <?php endif; ?>
                        </p>
                    </div>
                    <?php if ($quizTimeLimit > 0): ?>
                        <div class="text-center">
                            <div class="text-sm text-gray-600 mb-1">Time Remaining</div>
                            <div id="timer" class="text-3xl font-bold text-primary-600"></div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="mb-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <p class="text-sm text-blue-800">
                        <strong>Pass Score:</strong> <?php echo $quizPassScore; ?>%
                    </p>
                </div>
                
                <form id="quizForm" class="space-y-8">
                    <?php foreach ($questions as $index => $question): ?>
                        <div class="border border-gray-200 rounded-lg p-6 bg-gray-50">
                            <div class="flex items-start gap-3 mb-4">
                                <div class="w-8 h-8 bg-primary-600 rounded-lg flex items-center justify-center text-white font-bold flex-shrink-0">
                                    <?php echo $index + 1; ?>
                                </div>
                                <div class="flex-1">
                                    <h3 class="text-lg font-bold text-gray-900">
                                        <?php echo htmlspecialchars($question['question_text']); ?>
                                    </h3>
                                    <p class="text-sm text-gray-500 mt-1"><?php echo $question['points']; ?> points</p>
                                </div>
                            </div>
                            
                            <div class="space-y-2 ml-11">
                                <?php foreach ($question['options_array'] as $optIndex => $option): ?>
                                    <label class="option-item flex items-center gap-3 p-4 border-2 border-gray-200 rounded-lg" data-question="<?php echo $question['id']; ?>">
                                        <input type="radio" 
                                               name="answer_<?php echo $question['id']; ?>" 
                                               value="<?php echo htmlspecialchars($option); ?>" 
                                               class="w-5 h-5 text-primary-600" 
                                               required>
                                        <span class="text-gray-900"><?php echo htmlspecialchars($option); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <div class="flex items-center justify-between pt-6 border-t">
                        <a href="/app/views/learner/course-player.php?id=<?php echo $courseId; ?>&topic=<?php echo $topicId; ?>" 
                           class="text-gray-600 hover:text-gray-800">
                            ← Back to Course
                        </a>
                        <button type="submit" class="bg-primary-600 hover:bg-primary-700 text-white px-8 py-4 rounded-lg font-bold text-lg shadow-lg transition-all">
                            Submit Quiz
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
        
    </div>
</div>

<script>
    const quizId = <?php echo $quizId; ?>;
    const timeLimit = <?php echo $quizTimeLimit; ?>;
    const topicId = <?php echo $topicId ?? 'null'; ?>;
    const courseId = <?php echo $courseId ?? 'null'; ?>;
    let timeRemaining = timeLimit * 60;
    let timerInterval;
    let quizSubmitted = false;

    if (timeLimit > 0) {
        timerInterval = setInterval(updateTimer, 1000);
    }

    function updateTimer() {
        timeRemaining--;
        
        const minutes = Math.floor(timeRemaining / 60);
        const seconds = timeRemaining % 60;
        const timerElement = document.getElementById('timer');
        if (timerElement) {
            timerElement.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
        }
        
        if (timeRemaining <= 0) {
            clearInterval(timerInterval);
            alert('Time is up! Submitting your quiz...');
            submitQuiz();
        }
    }

    const quizForm = document.getElementById('quizForm');
    if (quizForm) {
        quizForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            if (!confirm('Are you sure you want to submit your quiz?')) {
                return;
            }

            submitQuiz();
        });
    }

    async function submitQuiz() {
        quizSubmitted = true;
        
        const answers = {};
        const questions = <?php echo json_encode(array_column($questions, 'id')); ?>;
        
        questions.forEach(qId => {
            const selected = document.querySelector(`input[name="answer_${qId}"]:checked`);
            answers[qId] = selected ? selected.value : '';
            console.log(`Question ${qId}: "${answers[qId]}"`);
        });

        console.log('Submitting answers:', answers);

        try {
            const response = await fetch('/api/quizzes.php?action=submit-attempt', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    quiz_id: quizId,
                    answers: answers
                })
            });

            const result = await response.json();
            console.log('Server response:', result);

            if (result.success) {
                if (timerInterval) clearInterval(timerInterval);
                
                const isPassed = result.data?.is_passed || false;
                const score = result.data?.score || 0;
                const correctCount = result.data?.correct_count || 0;
                const totalQuestions = result.data?.total_questions || 0;

                if (isPassed) {
                    alert(`🎉 Quiz Passed!\n\nScore: ${score.toFixed(1)}%\nCorrect: ${correctCount}/${totalQuestions}\n\nMarking topic as complete...`);
                    
                    if (topicId) {
                        await markTopicCompleteAndRedirect(topicId, courseId);
                    } else {
                        location.reload();
                    }
                } else {
                    alert(`❌ Quiz Failed\n\nScore: ${score.toFixed(1)}%\nCorrect: ${correctCount}/${totalQuestions}\n\nPassing score: <?php echo $quizPassScore; ?>%\n\nTry again!`);
                    location.reload();
                }
            } else {
                alert(result.error || 'Failed to submit quiz');
                quizSubmitted = false;
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Network error. Please try again.');
            quizSubmitted = false;
        }
    }

    async function markTopicCompleteAndRedirect(topicId, courseId) {
        try {
            const response = await fetch('/api/mark-topic-complete.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({topic_id: topicId})
            });

            const result = await response.json();

            if (courseId && topicId) {
                window.location.href = `/app/views/learner/course-player.php?id=${courseId}&topic=${topicId}`;
            } else {
                window.location.href = '/app/views/learner/my-courses.php';
            }
        } catch (error) {
            console.error('Error:', error);
            if (courseId && topicId) {
                window.location.href = `/app/views/learner/course-player.php?id=${courseId}&topic=${topicId}`;
            }
        }
    }

    document.querySelectorAll('.option-item').forEach(item => {
        item.addEventListener('click', function() {
            const questionId = this.getAttribute('data-question');
            const input = this.querySelector('input');
            
            document.querySelectorAll(`.option-item[data-question="${questionId}"]`).forEach(opt => {
                opt.classList.remove('selected');
            });
            
            this.classList.add('selected');
            input.checked = true;
        });
    });

    window.addEventListener('beforeunload', function (e) {
        if (!quizSubmitted && <?php echo !$maxReached && !empty($questions) ? 'true' : 'false'; ?>) {
            e.preventDefault();
            e.returnValue = '';
        }
    });
</script>

</body>
</html>
