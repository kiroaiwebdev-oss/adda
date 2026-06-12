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

// ✅ FIX: Direct query with question count
$stmt = $db->prepare("
    SELECT 
        q.*,
        cb.topic_id,
        t.title as topic_title,
        ch.title as chapter_title,
        c.title as course_title,
        c.id as course_id,
        (SELECT COUNT(*) FROM quiz_questions WHERE quiz_id = q.id) as question_count
    FROM quizzes q
    LEFT JOIN content_blocks cb ON q.content_block_id = cb.id
    LEFT JOIN topics t ON cb.topic_id = t.id
    LEFT JOIN chapters ch ON t.chapter_id = ch.id
    LEFT JOIN courses c ON ch.course_id = c.id
    ORDER BY q.created_at DESC
");
$stmt->execute();
$quizzes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Quizzes - Admin</title>
    
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
    </style>
</head>
<body>

<?php include __DIR__ . '/../../../views/components/sidebar-admin.php'; ?>

<div class="ml-64 min-h-screen">
    <header class="bg-white border-b border-gray-200 sticky top-0 z-30">
        <div class="px-8 py-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">All Quizzes</h1>
                    <p class="text-gray-600 mt-1">Manage all quizzes across courses</p>
                </div>
                <a href="/app/views/admin/courses/list.php" class="bg-primary-600 hover:bg-primary-700 text-white px-6 py-3 rounded-lg font-semibold transition-colors">
                    Back to Courses
                </a>
            </div>
        </div>
    </header>

    <main class="p-8">
        <?php if (empty($quizzes)): ?>
            <div class="bg-white rounded-xl border-2 border-dashed border-gray-300 p-12 text-center">
                <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                </svg>
                <h3 class="text-xl font-bold text-gray-900 mb-2">No Quizzes Yet</h3>
                <p class="text-gray-600 mb-6">Create quizzes in the course builder</p>
                <a href="/app/views/admin/courses/list.php" class="inline-block bg-primary-600 hover:bg-primary-700 text-white px-6 py-3 rounded-lg font-semibold transition-colors">
                    Go to Courses
                </a>
            </div>
        <?php else: ?>
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Quiz</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Course / Location</th>
                            <th class="px-6 py-4 text-center text-xs font-semibold text-gray-700 uppercase tracking-wider">Questions</th>
                            <th class="px-6 py-4 text-center text-xs font-semibold text-gray-700 uppercase tracking-wider">Pass Score</th>
                            <th class="px-6 py-4 text-center text-xs font-semibold text-gray-700 uppercase tracking-wider">Time Limit</th>
                            <th class="px-6 py-4 text-center text-xs font-semibold text-gray-700 uppercase tracking-wider">Max Attempts</th>
                            <th class="px-6 py-4 text-right text-xs font-semibold text-gray-700 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($quizzes as $quiz): ?>
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="font-semibold text-gray-900"><?php echo htmlspecialchars($quiz['title']); ?></div>
                                    <div class="text-xs text-gray-500 mt-1">Created: <?php echo date('M j, Y', strtotime($quiz['created_at'])); ?></div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm">
                                      <!-- NEW (CORRECT) ✅ -->
<div class="font-semibold text-gray-900"><?php echo $quiz['title']; ?></div>
<div class="font-semibold text-gray-900"><?php echo $quiz['course_title'] ?? 'N/A'; ?></div>
<div class="text-gray-600 text-xs mt-1">
    <?php echo $quiz['chapter_title'] ?? 'introduction'; ?> → <?php echo $quiz['topic_title'] ?? 'Vlof'; ?>
</div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <!-- ✅ FIX: Display actual question count -->
                                    <span class="inline-flex items-center justify-center w-10 h-10 bg-primary-100 text-primary-700 rounded-full font-bold">
                                        <?php echo $quiz['question_count'] ?? 0; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <span class="text-sm font-semibold text-gray-900"><?php echo $quiz['pass_score'] ?? 70; ?>%</span>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <span class="text-sm text-gray-600">
                                        <?php echo ($quiz['time_limit'] ?? 0) > 0 ? $quiz['time_limit'] . ' min' : 'No limit'; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <span class="text-sm text-gray-600">
                                        <?php echo ($quiz['max_attempts'] ?? 0) > 0 ? $quiz['max_attempts'] : 'Unlimited'; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <!-- ✅ FIX: Correct path to edit page -->
                                        <a href="/app/views/admin/quizzes/edit.php?id=<?php echo $quiz['id']; ?>" class="text-primary-600 hover:text-primary-700 font-semibold text-sm px-3 py-1 rounded-lg hover:bg-primary-50 transition-colors">
                                            Edit
                                        </a>
                                        <button onclick="deleteQuiz(<?php echo $quiz['id']; ?>)" class="text-red-600 hover:text-red-700 font-semibold text-sm px-3 py-1 rounded-lg hover:bg-red-50 transition-colors">
                                            Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </main>
</div>

<script>
    async function deleteQuiz(quizId) {
        if (!confirm('Delete this quiz and all its questions? This action cannot be undone.')) return;

        const response = await fetch('/api/quizzes.php?action=delete', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ quiz_id: quizId })
        });

        const result = await response.json();
        if (result.success) {
            location.reload();
        } else {
            alert(result.error || 'Failed to delete quiz');
        }
    }
</script>

</body>
</html>
