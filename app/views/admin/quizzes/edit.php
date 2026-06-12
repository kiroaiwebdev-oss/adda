<?php
session_start();
require_once __DIR__ . '/../../../config/database.php';

$dbConfig = require __DIR__ . '/../../../config/database.php';
$dsn = sprintf("mysql:host=%s;port=%s;dbname=%s;charset=%s",
    $dbConfig['host'], $dbConfig['port'], $dbConfig['database'], $dbConfig['charset']
);
$db = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options']);

spl_autoload_register(function ($class) {
    $paths = [
        __DIR__ . '/../../../core/' . $class . '.php',
        __DIR__ . '/../../../models/' . $class . '.php',
        __DIR__ . '/../../../controllers/' . $class . '.php',
    ];
    foreach ($paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            return;
        }
    }
});

$auth = new Auth($db);
$auth->requireAdmin();

$quizId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$quizId) {
    header('Location: /app/views/admin/quizzes/list.php');
    exit;
}

// Get quiz directly from database
$stmt = $db->prepare("SELECT * FROM quizzes WHERE id = ?");
$stmt->execute([$quizId]);
$quiz = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$quiz) {
    header('Location: /app/views/admin/quizzes/list.php');
    exit;
}

// Get quiz context (course, chapter, topic)
$stmt = $db->prepare("
    SELECT cb.*, t.title as topic_title, ch.title as chapter_title, c.title as course_title, c.id as course_id
    FROM content_blocks cb
    JOIN topics t ON cb.topic_id = t.id
    JOIN chapters ch ON t.chapter_id = ch.id
    JOIN courses c ON ch.course_id = c.id
    WHERE cb.id = ?
");
$stmt->execute([$quiz['content_block_id']]);
$context = $stmt->fetch();

// Get questions directly from database
$stmt = $db->prepare("SELECT * FROM quiz_questions WHERE quiz_id = ? ORDER BY sort_order ASC");
$stmt->execute([$quizId]);
$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Parse options JSON for display
foreach ($questions as &$question) {
    if ($question['options']) {
        $question['options'] = json_decode($question['options'], true);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Quiz - <?php echo $quiz['title']; ?></title>
    <link rel="icon" type="image/png" href="https://internshipadda.com/icons.png">
    <link rel="shortcut icon" type="image/png" href="https://internshipadda.com/icons.png">
    <link rel="apple-touch-icon" href="https://internshipadda.com/icons.png">
    
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
        body {
            font-family: 'Inter', sans-serif;
            background: #fafafa;
        }
        h1, h2, h3, h4, h5, h6 {
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
        }
        .form-input, .form-textarea, .form-select {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        .form-input:focus, .form-textarea:focus, .form-select:focus {
            outline: none;
            border-color: #22c55e;
            box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.1);
        }
        .question-card {
            background: white;
            border: 2px solid #e5e7eb;
            border-radius: 16px;
            padding: 24px;
            transition: all 0.3s ease;
        }
        .question-card:hover {
            border-color: #22c55e;
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/../../../views/components/sidebar-admin.php'; ?>

<div class="ml-64 min-h-screen">
    <header class="bg-white border-b border-gray-200 sticky top-0 z-30">
        <div class="px-8 py-6">
            <div class="flex items-center justify-between">
                <div>
                    <div class="flex items-center gap-2 text-sm text-gray-500 mb-2">
                        <a href="/app/views/admin/quizzes/list.php" class="hover:text-primary-600">Quizzes</a>
                        <span>/</span>
                        <span>Edit Quiz</span>
                    </div>
                    <h1 class="text-3xl font-bold text-gray-900">Edit Quiz</h1>
                    <?php if ($context): ?>
                        <p class="text-gray-600 mt-1"><?php echo $context['course_title']; ?> → <?php echo $context['chapter_title']; ?> → <?php echo $context['topic_title']; ?></p>
                    <?php endif; ?>
                </div>
                <?php if ($context): ?>
                    <a href="/app/views/admin/courses/builder.php?id=<?php echo $context['course_id']; ?>" class="bg-primary-600 hover:bg-primary-700 text-white px-6 py-3 rounded-lg font-semibold transition-colors">
                        ← Back to Builder
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <main class="p-8">
        <div class="max-w-4xl mx-auto space-y-6">
            
            <div id="alertContainer"></div>

            <!-- ✅ Quiz Settings Section (KEPT) -->
            <div class="bg-white rounded-xl border border-gray-200 p-8">
                <h2 class="text-xl font-bold text-gray-900 mb-6">Quiz Settings</h2>
                
                <form id="quizSettingsForm" class="space-y-6">
                    <input type="hidden" name="quiz_id" value="<?php echo $quiz['id']; ?>">

                    <div>
                        <label for="title" class="block text-sm font-semibold text-gray-700 mb-2">Quiz Title *</label>
                        <input 
                            type="text" 
                            id="title" 
                            name="title" 
                            class="form-input" 
                            value="<?php echo htmlspecialchars($quiz['title']); ?>"
                            required
                        >
                    </div>

                    <div class="grid grid-cols-3 gap-6">
                        <div>
                            <label for="time_limit" class="block text-sm font-semibold text-gray-700 mb-2">Time Limit (minutes)</label>
                            <input 
                                type="number" 
                                id="time_limit" 
                                name="time_limit" 
                                class="form-input" 
                                value="<?php echo $quiz['time_limit']; ?>"
                                min="0"
                                placeholder="0 = No limit"
                            >
                            <p class="text-xs text-gray-500 mt-1">0 = No time limit</p>
                        </div>

                        <div>
                            <label for="pass_score" class="block text-sm font-semibold text-gray-700 mb-2">Pass Score (%)</label>
                            <input 
                                type="number" 
                                id="pass_score" 
                                name="pass_score" 
                                class="form-input" 
                                value="<?php echo $quiz['pass_score']; ?>"
                                min="0"
                                max="100"
                                required
                            >
                        </div>

                        <div>
                            <label for="max_attempts" class="block text-sm font-semibold text-gray-700 mb-2">Max Attempts</label>
                            <input 
                                type="number" 
                                id="max_attempts" 
                                name="max_attempts" 
                                class="form-input" 
                                value="<?php echo $quiz['max_attempts'] ?? 0; ?>"
                                min="0"
                                placeholder="0 = Unlimited"
                            >
                            <p class="text-xs text-gray-500 mt-1">0 = Unlimited</p>
                        </div>
                    </div>

                    <button type="submit" class="w-full bg-primary-600 hover:bg-primary-700 text-white px-6 py-3 rounded-lg font-semibold transition-colors">
                        Save Quiz Settings
                    </button>
                </form>
            </div>

            <!-- Questions Section -->
            <div class="bg-white rounded-xl border border-gray-200 p-8">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h2 class="text-xl font-bold text-gray-900">Questions</h2>
                        <p class="text-sm text-gray-600 mt-1"><?php echo count($questions); ?> question(s)</p>
                    </div>
                </div>

                <div id="questionsList" class="space-y-4">
                    <?php if (empty($questions)): ?>
                        <div class="text-center py-12">
                            <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            <p class="text-gray-500 font-semibold mb-2">No questions added yet</p>
                            <p class="text-gray-400 text-sm mb-4">Add questions from the course builder</p>
                            <?php if ($context): ?>
                                <a href="/app/views/admin/courses/builder.php?id=<?php echo $context['course_id']; ?>" class="inline-block bg-primary-600 hover:bg-primary-700 text-white px-6 py-3 rounded-lg font-semibold transition-colors">
                                    Go to Builder
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <?php foreach ($questions as $index => $question): ?>
                            <div class="question-card">
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <div class="flex items-center gap-3 mb-3">
                                            <span class="w-10 h-10 bg-primary-100 text-primary-600 rounded-lg flex items-center justify-center font-bold text-lg"><?php echo $index + 1; ?></span>
                                            <span class="text-xs font-semibold px-3 py-1 rounded-full <?php echo $question['type'] === 'multiple_choice' ? 'bg-blue-100 text-blue-700' : 'bg-green-100 text-green-700'; ?>">
                                                <?php echo $question['type'] === 'multiple_choice' ? 'Multiple Choice' : 'True/False'; ?>
                                            </span>
                                            <span class="text-xs text-gray-500 font-semibold"><?php echo $question['points']; ?> point(s)</span>
                                        </div>
                                        <p class="text-gray-900 font-semibold text-lg mb-4"><?php echo $question['question_text']; ?></p>
                                        
                                        <?php if ($question['options']): ?>
                                            <div class="space-y-2 text-sm mb-4">
                                                <?php 
                                                $optionLabels = ['A', 'B', 'C', 'D'];
                                                foreach ($question['options'] as $idx => $value): 
                                                    if ($value):
                                                ?>
                                                    <div class="flex items-center gap-3 p-3 rounded-lg <?php echo $value === $question['correct_answer'] ? 'bg-green-50 border border-green-200' : 'bg-gray-50'; ?>">
                                                        <span class="w-6 h-6 flex items-center justify-center rounded-full <?php echo $value === $question['correct_answer'] ? 'bg-green-600 text-white' : 'bg-gray-300 text-gray-700'; ?> font-semibold text-xs">
                                                            <?php echo $optionLabels[$idx]; ?>
                                                        </span>
                                                        <span class="<?php echo $value === $question['correct_answer'] ? 'text-green-900 font-semibold' : 'text-gray-700'; ?>">
                                                            <?php echo $value; ?>
                                                        </span>
                                                        <?php if ($value === $question['correct_answer']): ?>
                                                            <span class="ml-auto text-green-600 font-bold">✓ Correct</span>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php 
                                                    endif;
                                                endforeach; 
                                                ?>
                                            </div>
                                        <?php else: ?>
                                            <div class="p-4 bg-green-50 border border-green-200 rounded-lg">
                                                <p class="text-green-900 font-semibold">✓ Correct Answer: <?php echo $question['correct_answer']; ?></p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- ✅ FIXED: Clickable Edit & Delete Buttons -->
                                    <div class="flex items-center gap-2 ml-4">
                                        <?php if ($context): ?>
                                            <a href="/app/views/admin/courses/builder.php?id=<?php echo $context['course_id']; ?>" class="text-primary-600 hover:text-primary-700 hover:bg-primary-50 p-2 rounded-lg transition-colors" title="Edit in Builder">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                                </svg>
                                            </a>
                                        <?php endif; ?>
                                        <button onclick="deleteQuestion(<?php echo $question['id']; ?>, '<?php echo addslashes(substr($question['question_text'], 0, 40)); ?>...')" class="text-red-600 hover:text-red-700 hover:bg-red-50 p-2 rounded-lg transition-colors" title="Delete Question">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
    // Update Quiz Settings
    document.getElementById('quizSettingsForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const formData = new FormData(e.target);
        const data = {
            quiz_id: formData.get('quiz_id'),
            title: formData.get('title'),
            time_limit: parseInt(formData.get('time_limit')),
            pass_score: parseInt(formData.get('pass_score')),
            max_attempts: parseInt(formData.get('max_attempts'))
        };

        try {
            const response = await fetch('/api/quizzes.php?action=update', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });

            const result = await response.json();

            if (result.success) {
                showAlert('success', '✅ Quiz settings updated successfully!');
            } else {
                showAlert('error', '❌ ' + (result.error || 'Failed to update quiz'));
            }
        } catch (error) {
            showAlert('error', '❌ Network error');
        }
    });

    // Delete Question Function
    async function deleteQuestion(questionId, questionText) {
        if (!confirm(`Delete this question?\n\n"${questionText}"\n\nThis action cannot be undone.`)) return;

        try {
            const response = await fetch('/api/quizzes.php?action=delete-question', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ question_id: questionId })
            });

            const result = await response.json();
            
            if (result.success) {
                showAlert('success', '✅ Question deleted successfully!');
                setTimeout(() => {
                    location.reload();
                }, 1000);
            } else {
                showAlert('error', '❌ ' + (result.error || 'Failed to delete question'));
            }
        } catch (error) {
            showAlert('error', '❌ Network error');
        }
    }

    function showAlert(type, message) {
        const alertClass = type === 'success' ? 'bg-green-50 text-green-700 border-green-200' : 'bg-red-50 text-red-700 border-red-200';
        document.getElementById('alertContainer').innerHTML = `<div class="${alertClass} px-6 py-4 rounded-lg border-2 font-semibold mb-6">${message}</div>`;
        setTimeout(() => {
            document.getElementById('alertContainer').innerHTML = '';
        }, 3000);
    }
</script>

</body>
</html>
