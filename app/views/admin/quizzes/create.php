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

$contentBlockId = isset($_GET['block_id']) ? (int)$_GET['block_id'] : 0;

if (!$contentBlockId) {
    header('Location: /admin/courses/list.php');
    exit;
}

// Get content block and course info
$stmt = $db->prepare("
    SELECT cb.*, t.title as topic_title, ch.title as chapter_title, c.title as course_title, c.id as course_id
    FROM content_blocks cb
    JOIN topics t ON cb.topic_id = t.id
    JOIN chapters ch ON t.chapter_id = ch.id
    JOIN courses c ON ch.course_id = c.id
    WHERE cb.id = ? AND cb.type = 'quiz'
");
$stmt->execute([$contentBlockId]);
$contentBlock = $stmt->fetch();

if (!$contentBlock) {
    header('Location: /admin/courses/list.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Quiz - Admin</title>
    
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
        .form-input, .form-textarea {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        .form-input:focus, .form-textarea:focus {
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
            <div class="flex items-center gap-2 text-sm text-gray-500 mb-2">
                <a href="/admin/courses/list.php" class="hover:text-primary-600">Courses</a>
                <span>/</span>
                <a href="/admin/courses/builder.php?id=<?php echo $contentBlock['course_id']; ?>" class="hover:text-primary-600"><?php echo htmlspecialchars($contentBlock['course_title']); ?></a>
                <span>/</span>
                <span>Create Quiz</span>
            </div>
            <h1 class="text-3xl font-bold text-gray-900">Create Quiz</h1>
            <p class="text-gray-600 mt-1"><?php echo htmlspecialchars($contentBlock['chapter_title']); ?> → <?php echo htmlspecialchars($contentBlock['topic_title']); ?></p>
        </div>
    </header>

    <main class="p-8">
        <div class="max-w-4xl mx-auto space-y-6">
            <!-- Quiz Settings -->
            <div class="bg-white rounded-xl border border-gray-200 p-8">
                <h2 class="text-xl font-bold text-gray-900 mb-6">Quiz Settings</h2>
                
                <div id="alertContainer" class="mb-6"></div>

                <form id="quizSettingsForm" class="space-y-6">
                    <input type="hidden" name="content_block_id" value="<?php echo $contentBlockId; ?>">

                    <div>
                        <label for="title" class="block text-sm font-semibold text-gray-700 mb-2">Quiz Title *</label>
                        <input 
                            type="text" 
                            id="title" 
                            name="title" 
                            class="form-input" 
                            placeholder="e.g., JavaScript Variables Test"
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
                                value="0"
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
                                value="70"
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
                                value="0"
                                min="0"
                                placeholder="0 = Unlimited"
                            >
                            <p class="text-xs text-gray-500 mt-1">0 = Unlimited</p>
                        </div>
                    </div>

                    <button type="submit" class="w-full bg-primary-600 hover:bg-primary-700 text-white px-6 py-3 rounded-lg font-semibold transition-colors">
                        Create Quiz & Add Questions
                    </button>
                </form>
            </div>

            <!-- Questions Section (Hidden until quiz created) -->
            <div id="questionsSection" class="hidden">
                <div class="bg-white rounded-xl border border-gray-200 p-8">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-xl font-bold text-gray-900">Questions</h2>
                        <button onclick="openAddQuestionModal()" class="bg-primary-600 hover:bg-primary-700 text-white px-6 py-2 rounded-lg font-semibold transition-colors">
                            + Add Question
                        </button>
                    </div>

                    <div id="questionsList" class="space-y-4">
                        <!-- Questions will be loaded here -->
                    </div>
                </div>

                <div class="flex justify-end">
                    <a href="/admin/courses/builder.php?id=<?php echo $contentBlock['course_id']; ?>" class="bg-primary-600 hover:bg-primary-700 text-white px-8 py-3 rounded-lg font-semibold transition-colors">
                        Done - Back to Builder
                    </a>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Add Question Modal -->
<div id="addQuestionModal" class="modal hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
    <div class="bg-white rounded-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto p-8 m-4">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-2xl font-bold text-gray-900">Add Question</h2>
            <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        <form id="addQuestionForm" class="space-y-6">
            <input type="hidden" name="quiz_id" id="quizIdInput">

            <div>
                <label for="question_type" class="block text-sm font-semibold text-gray-700 mb-2">Question Type *</label>
                <select id="question_type" name="type" class="form-input" onchange="toggleQuestionType()">
                    <option value="mcq">Multiple Choice (MCQ)</option>
                    <option value="true_false">True/False</option>
                </select>
            </div>

            <div>
                <label for="question_text" class="block text-sm font-semibold text-gray-700 mb-2">Question *</label>
                <textarea 
                    id="question_text" 
                    name="question" 
                    rows="3"
                    class="form-textarea" 
                    placeholder="Enter your question..."
                    required
                ></textarea>
            </div>

            <!-- MCQ Options -->
            <div id="mcqOptions">
                <label class="block text-sm font-semibold text-gray-700 mb-2">Answer Options *</label>
                <div class="space-y-3">
                    <input type="text" name="option_a" class="form-input" placeholder="Option A" required>
                    <input type="text" name="option_b" class="form-input" placeholder="Option B" required>
                    <input type="text" name="option_c" class="form-input" placeholder="Option C">
                    <input type="text" name="option_d" class="form-input" placeholder="Option D">
                </div>
            </div>

            <div>
                <label for="correct_answer" class="block text-sm font-semibold text-gray-700 mb-2">Correct Answer *</label>
                <input 
                    type="text" 
                    id="correct_answer" 
                    name="correct_answer" 
                    class="form-input" 
                    placeholder="e.g., A or True"
                    required
                >
                <p class="text-xs text-gray-500 mt-1">For MCQ: Enter option letter (A, B, C, D). For True/False: Enter "True" or "False"</p>
            </div>

            <div class="flex gap-3 pt-4">
                <button type="submit" class="flex-1 bg-primary-600 hover:bg-primary-700 text-white px-6 py-3 rounded-lg font-semibold transition-colors">
                    Add Question
                </button>
                <button type="button" onclick="closeModal()" class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 px-6 py-3 rounded-lg font-semibold transition-colors">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    let currentQuizId = null;

    // Create Quiz
    document.getElementById('quizSettingsForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const formData = new FormData(e.target);
        const data = {
            content_block_id: formData.get('content_block_id'),
            title: formData.get('title'),
            time_limit: parseInt(formData.get('time_limit')),
            pass_score: parseInt(formData.get('pass_score')),
            max_attempts: parseInt(formData.get('max_attempts'))
        };

        try {
            const response = await fetch('/api/quizzes.php?action=create', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });

            const result = await response.json();

            if (result.success) {
                currentQuizId = result.data.quiz_id;
                document.getElementById('quizIdInput').value = currentQuizId;
                document.getElementById('quizSettingsForm').classList.add('hidden');
                document.getElementById('questionsSection').classList.remove('hidden');
                showAlert('success', 'Quiz created! Now add questions.');
            } else {
                showAlert('error', result.error || 'Failed to create quiz');
            }
        } catch (error) {
            showAlert('error', 'Network error');
        }
    });

    // Add Question
    document.getElementById('addQuestionForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const formData = new FormData(e.target);
        const type = formData.get('type');
        
        let options = null;
        if (type === 'mcq') {
            options = {
                A: formData.get('option_a'),
                B: formData.get('option_b'),
                C: formData.get('option_c') || '',
                D: formData.get('option_d') || ''
            };
        }

        const data = {
            quiz_id: currentQuizId,
            question: formData.get('question'),
            type: type,
            options: options,
            correct_answer: formData.get('correct_answer')
        };

        try {
            const response = await fetch('/api/quizzes.php?action=add-question', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });

            const result = await response.json();

            if (result.success) {
                closeModal();
                loadQuestions();
                e.target.reset();
            } else {
                alert(result.error || 'Failed to add question');
            }
        } catch (error) {
            alert('Network error');
        }
    });

    function toggleQuestionType() {
        const type = document.getElementById('question_type').value;
        document.getElementById('mcqOptions').style.display = type === 'mcq' ? 'block' : 'none';
    }

    function openAddQuestionModal() {
        document.getElementById('addQuestionModal').classList.remove('hidden');
        toggleQuestionType();
    }

    function closeModal() {
        document.getElementById('addQuestionModal').classList.add('hidden');
    }

    async function loadQuestions() {
        if (!currentQuizId) return;

        const response = await fetch(`/api/quizzes.php?action=get-questions&quiz_id=${currentQuizId}`);
        const result = await response.json();

        if (result.success && result.data.questions) {
            const list = document.getElementById('questionsList');
            
            if (result.data.questions.length === 0) {
                list.innerHTML = '<p class="text-gray-500 text-center py-8">No questions added yet. Click "Add Question" to start.</p>';
            } else {
                list.innerHTML = result.data.questions.map((q, index) => `
                    <div class="question-card">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <div class="flex items-center gap-3 mb-2">
                                    <span class="w-8 h-8 bg-primary-100 text-primary-600 rounded-lg flex items-center justify-center font-bold">${index + 1}</span>
                                    <span class="text-xs font-semibold px-3 py-1 rounded-full ${q.type === 'mcq' ? 'bg-blue-100 text-blue-700' : 'bg-green-100 text-green-700'}">
                                        ${q.type === 'mcq' ? 'Multiple Choice' : 'True/False'}
                                    </span>
                                </div>
                                <p class="text-gray-900 font-semibold mb-3">${q.question}</p>
                                ${q.options ? `
                                    <div class="space-y-1 text-sm">
                                        ${Object.entries(JSON.parse(q.options)).map(([key, val]) => 
                                            val ? `<p class="text-gray-600"><span class="font-semibold">${key}.</span> ${val}</p>` : ''
                                        ).join('')}
                                    </div>
                                ` : ''}
                                <p class="text-sm text-primary-600 font-semibold mt-3">✓ Correct Answer: ${q.correct_answer}</p>
                            </div>
                            <button onclick="deleteQuestion(${q.id})" class="text-red-600 hover:text-red-700 p-2 rounded-lg hover:bg-red-50 transition-colors">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                </svg>
                            </button>
                        </div>
                    </div>
                `).join('');
            }
        }
    }

    async function deleteQuestion(questionId) {
        if (!confirm('Delete this question?')) return;

        const response = await fetch('/api/quizzes.php?action=delete-question', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ question_id: questionId })
        });

        const result = await response.json();
        if (result.success) {
            loadQuestions();
        }
    }

    function showAlert(type, message) {
        const alertClass = type === 'success' ? 'bg-green-50 text-green-700 border-green-200' : 'bg-red-50 text-red-700 border-red-200';
        document.getElementById('alertContainer').innerHTML = `<div class="${alertClass} px-4 py-3 rounded-lg border">${message}</div>`;
    }
</script>

</body>
</html>
