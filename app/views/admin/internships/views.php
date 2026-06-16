<?php
// PREVENT CACHING
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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

$internshipId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$internshipId) {
    header('Location: /app/views/admin/internships/list.php');
    exit;
}

$internshipModel = new Internship($db);
$internship = $internshipModel->getById($internshipId);

if (!$internship) {
    header('Location: /app/views/admin/internships/list.php');
    exit;
}

// Get all modules with lessons, content, and quiz
$db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
$stmt = $db->prepare("SELECT * FROM internship_modules WHERE internship_id = ? ORDER BY sort_order ASC, id ASC");
$stmt->execute([$internshipId]);
$modulesTemp = $stmt->fetchAll(PDO::FETCH_ASSOC);

$modules = [];
$moduleIndex = 0;
$totalLessons = 0;
$totalQuizQuestions = 0;

foreach ($modulesTemp as $moduleRow) {
    $modules[$moduleIndex] = $moduleRow;
    
    // Get lessons
    $stmt = $db->prepare("SELECT * FROM internship_lessons WHERE module_id = ? ORDER BY sort_order ASC");
    $stmt->execute([$moduleRow['id']]);
    $lessonsTemp = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $modules[$moduleIndex]['lessons'] = [];
    $lessonIndex = 0;
    
    foreach ($lessonsTemp as $lessonRow) {
        $totalLessons++;
        $modules[$moduleIndex]['lessons'][$lessonIndex] = $lessonRow;
        
        // Get content blocks
        $stmt = $db->prepare("SELECT * FROM internship_content_blocks WHERE lesson_id = ? ORDER BY sort_order ASC");
        $stmt->execute([$lessonRow['id']]);
        $modules[$moduleIndex]['lessons'][$lessonIndex]['content_blocks'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get quiz questions
        $quizStmt = $db->prepare("SELECT * FROM internship_quiz_questions WHERE lesson_id = ? ORDER BY id ASC");
        $quizStmt->execute([$lessonRow['id']]);
        $quizQuestions = $quizStmt->fetchAll(PDO::FETCH_ASSOC);
        $modules[$moduleIndex]['lessons'][$lessonIndex]['quiz_questions'] = $quizQuestions;
        $totalQuizQuestions += count($quizQuestions);
        
        $lessonIndex++;
    }
    
    $moduleIndex++;
}

unset($modulesTemp, $lessonsTemp, $moduleRow, $lessonRow);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Internship - <?php echo htmlspecialchars($internship['title']); ?></title>
    <link rel="icon" type="image/png" href="https://internshipadda.com/icons.png">
    <link rel="shortcut icon" type="image/png" href="https://internshipadda.com/icons.png">
    <link rel="apple-touch-icon" href="https://internshipadda.com/icons.png">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Poppins:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
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
        
        .module-section {
            background: white;
            border: 2px solid #e5e7eb;
            border-radius: 16px;
            margin-bottom: 24px;
            overflow: hidden;
        }
        
        .module-header {
            background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
            color: white;
            padding: 20px 24px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: all 0.3s ease;
        }
        
        .module-header:hover {
            background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
        }
        
        .module-content {
            padding: 24px;
            display: none;
        }
        
        .module-content.active {
            display: block;
        }
        
        .lesson-card {
            background: #f9fafb;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 16px;
        }
        
        .content-block {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 12px;
            line-height: 1.6;
        }
        
        .content-block h1 {
            font-size: 2em;
            font-weight: bold;
            margin: 0.67em 0;
            line-height: 1.2;
        }

        .content-block h2 {
            font-size: 1.5em;
            font-weight: bold;
            margin: 0.75em 0;
            line-height: 1.3;
        }

        .content-block h3 {
            font-size: 1.17em;
            font-weight: bold;
            margin: 0.83em 0;
            line-height: 1.4;
        }

        .content-block ul, .content-block ol {
            margin-left: 40px;
            margin-top: 1em;
            margin-bottom: 1em;
            padding-left: 0;
        }

        .content-block ul {
            list-style-type: disc;
        }

        .content-block ol {
            list-style-type: decimal;
        }

        .content-block li {
            margin-bottom: 0.5em;
        }

        .content-block img {
            max-width: 100%;
            height: auto;
            border-radius: 8px;
            margin: 10px 0;
            display: block;
        }

        .content-block video {
            max-width: 100%;
            border-radius: 12px;
            margin: 20px 0;
            display: block;
        }

        .content-block iframe {
            max-width: 100%;
            border-radius: 12px;
            margin: 20px 0;
            display: block;
            min-height: 400px;
        }

        .content-block a {
            color: #2563eb;
            text-decoration: underline;
        }

        .content-block code {
            background: #f3f4f6;
            color: #111827;
            padding: 2px 6px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 0.9em;
        }

        .content-block pre {
            background: #1f2937;
            color: #f9fafb;
            padding: 16px;
            border-radius: 8px;
            overflow-x: auto;
            margin: 10px 0;
        }

        .content-block pre code {
            background: transparent;
            color: #f9fafb;
            padding: 0;
            border-radius: 0;
            font-size: inherit;
            white-space: pre;
        }

        .content-block blockquote {
            border-left: 4px solid #22c55e;
            padding-left: 16px;
            margin: 10px 0;
            color: #6b7280;
            font-style: italic;
        }

        .content-block hr {
            border: none;
            border-top: 2px solid #e5e7eb;
            margin: 20px 0;
        }
        
        .quiz-section {
            background: #fff7ed;
            border: 2px solid #fed7aa;
            border-radius: 12px;
            padding: 20px;
            margin-top: 16px;
        }
        
        .quiz-question {
            background: white;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 12px;
        }
        
        .quiz-option {
            padding: 10px 14px;
            background: #f3f4f6;
            border-radius: 6px;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .quiz-option.correct {
            background: #dcfce7;
            border: 2px solid #22c55e;
            font-weight: 600;
        }
        
        .stats-card {
            background: white;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            padding: 16px;
            text-align: center;
        }
        
        @media print {
            .no-print {
                display: none !important;
            }
            .module-content {
                display: block !important;
            }
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/../../components/sidebar-admin.php'; ?>

<div class="ml-64 min-h-screen">
    <header class="bg-white border-b border-gray-200 sticky top-0 z-30 no-print">
        <div class="px-8 py-6">
            <div class="flex items-center justify-between">
                <div>
                    <div class="flex items-center gap-2 text-sm text-gray-500 mb-2">
                        <a href="/app/views/admin/internships/list.php" class="hover:text-primary-600">Internships</a>
                        <span>/</span>
                        <span>View</span>
                    </div>
                    <h1 class="text-3xl font-bold text-gray-900">📖 <?php echo htmlspecialchars($internship['title']); ?></h1>
                    <p class="text-gray-600 mt-1">Complete internship content overview</p>
                </div>
                <div class="flex items-center gap-3">
                    <button onclick="window.print()" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-6 py-3 rounded-lg font-semibold transition-colors flex items-center gap-2">
                        <i class="fas fa-print"></i>
                        Print
                    </button>
                    <a href="/app/views/admin/internships/builder.php?id=<?php echo $internship['id']; ?>" class="bg-primary-600 hover:bg-primary-700 text-white px-6 py-3 rounded-lg font-semibold transition-colors flex items-center gap-2">
                        <i class="fas fa-edit"></i>
                        Edit
                    </a>
                    <button onclick="window.history.back()" class="bg-gray-600 hover:bg-gray-700 text-white px-6 py-3 rounded-lg font-semibold transition-colors">
                        ← Back
                    </button>
                </div>
            </div>
        </div>
    </header>

    <main class="p-8">
        <!-- Stats Overview -->
        <div class="max-w-5xl mx-auto mb-8 grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="stats-card">
                <div class="text-4xl mb-2">📚</div>
                <div class="text-2xl font-bold text-gray-900"><?php echo count($modules); ?></div>
                <div class="text-sm text-gray-600">Modules</div>
            </div>
            <div class="stats-card">
                <div class="text-4xl mb-2">📝</div>
                <div class="text-2xl font-bold text-gray-900"><?php echo $totalLessons; ?></div>
                <div class="text-sm text-gray-600">Lessons</div>
            </div>
            <div class="stats-card">
                <div class="text-4xl mb-2">❓</div>
                <div class="text-2xl font-bold text-gray-900"><?php echo $totalQuizQuestions; ?></div>
                <div class="text-sm text-gray-600">Quiz Questions</div>
            </div>
        </div>

        <?php if (empty($modules)): ?>
            <div class="max-w-5xl mx-auto">
                <div class="bg-white rounded-2xl border-2 border-dashed border-gray-300 p-12 text-center">
                    <div class="w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-6">
                        <i class="fas fa-inbox text-4xl text-gray-400"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 mb-3">No Content Yet</h3>
                    <p class="text-gray-600 mb-8 text-lg">Start building your internship by adding modules and lessons</p>
                    <a href="/app/views/admin/internships/builder.php?id=<?php echo $internship['id']; ?>" class="inline-flex items-center gap-2 bg-primary-600 hover:bg-primary-700 text-white px-6 py-3 rounded-lg font-semibold transition-colors">
                        <i class="fas fa-plus"></i>
                        Go to Builder
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="max-w-5xl mx-auto">
                <?php foreach ($modules as $moduleIndex => $module): ?>
                    <div class="module-section">
                        <div class="module-header" onclick="toggleModule(<?php echo $moduleIndex; ?>)">
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 bg-white bg-opacity-20 rounded-xl flex items-center justify-center text-white font-bold text-xl">
                                    <?php echo $moduleIndex + 1; ?>
                                </div>
                                <div>
                                    <h2 class="text-2xl font-bold"><?php echo htmlspecialchars($module['title']); ?></h2>
                                    <?php if (!empty($module['description'])): ?>
                                        <p class="text-green-100 mt-1"><?php echo htmlspecialchars($module['description']); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <i class="fas fa-chevron-down text-2xl transition-transform" id="arrow-<?php echo $moduleIndex; ?>"></i>
                        </div>
                        
                        <div class="module-content" id="module-<?php echo $moduleIndex; ?>">
                            <?php if (empty($module['lessons'])): ?>
                                <div class="text-center py-8 text-gray-500">
                                    <i class="fas fa-folder-open text-4xl mb-3 text-gray-400"></i>
                                    <p>No lessons in this module yet</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($module['lessons'] as $lessonIndex => $lesson): ?>
                                    <div class="lesson-card">
                                        <div class="flex items-start justify-between mb-4">
                                            <div class="flex items-center gap-3">
                                                <div class="w-10 h-10 bg-primary-100 rounded-lg flex items-center justify-center text-primary-600 font-bold">
                                                    <?php echo $lessonIndex + 1; ?>
                                                </div>
                                                <div>
                                                    <h3 class="text-xl font-bold text-gray-900"><?php echo htmlspecialchars($lesson['title']); ?></h3>
                                                    <?php if (!empty($lesson['description'])): ?>
                                                        <p class="text-sm text-gray-600 mt-1"><?php echo htmlspecialchars($lesson['description']); ?></p>
                                                    <?php endif; ?>
                                                    <?php if (isset($lesson['duration_minutes']) && $lesson['duration_minutes'] > 0): ?>
                                                        <div class="flex items-center gap-1 text-xs text-gray-500 mt-2">
                                                            <i class="far fa-clock"></i>
                                                            <span><?php echo $lesson['duration_minutes']; ?> minutes</span>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Content Blocks -->
                                        <?php if (!empty($lesson['content_blocks'])): ?>
                                            <div class="mb-4">
                                                <h4 class="text-sm font-bold text-gray-700 uppercase tracking-wide mb-3 flex items-center gap-2">
                                                    <i class="fas fa-file-alt text-primary-600"></i>
                                                    Lesson Content
                                                </h4>
                                                <?php foreach ($lesson['content_blocks'] as $contentBlock): ?>
                                                    <div class="content-block">
                                                        <?php echo $contentBlock['content']; ?>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php else: ?>
                                            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">
                                                <div class="flex items-center gap-2 text-yellow-700">
                                                    <i class="fas fa-exclamation-triangle"></i>
                                                    <span class="text-sm font-semibold">No content added yet</span>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <!-- Quiz Questions -->
                                        <?php if (!empty($lesson['quiz_questions'])): ?>
                                            <div class="quiz-section">
                                                <h4 class="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
                                                    <i class="fas fa-question-circle text-orange-600"></i>
                                                    Quiz Questions (<?php echo count($lesson['quiz_questions']); ?>)
                                                </h4>
                                                <?php foreach ($lesson['quiz_questions'] as $qIndex => $question): ?>
                                                    <div class="quiz-question">
                                                        <div class="flex items-start justify-between mb-3">
                                                            <div class="flex items-start gap-3 flex-1">
                                                                <div class="bg-primary-100 text-primary-700 px-3 py-1 rounded-full text-xs font-bold">
                                                                    Q<?php echo $qIndex + 1; ?>
                                                                </div>
                                                                <div class="flex-1">
                                                                    <p class="font-semibold text-gray-900 mb-2"><?php echo htmlspecialchars($question['question']); ?></p>
                                                                    <div class="flex items-center gap-3 text-xs text-gray-600">
                                                                        <span class="bg-gray-100 px-2 py-1 rounded">
                                                                            <?php echo ucwords(str_replace('_', ' ', $question['type'])); ?>
                                                                        </span>
                                                                        <?php if (isset($question['points'])): ?>
                                                                            <span class="bg-orange-100 text-orange-700 px-2 py-1 rounded font-semibold">
                                                                                <?php echo $question['points']; ?> points
                                                                            </span>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        
                                                        <?php if ($question['type'] === 'multiple_choice' && !empty($question['options'])): ?>
                                                            <div class="mt-3">
                                                                <?php 
                                                                $options = json_decode($question['options'], true);
                                                                if ($options):
                                                                    foreach ($options as $optIndex => $option):
                                                                        $isCorrect = isset($question['correct_option']) && ($optIndex + 1) == $question['correct_option'];
                                                                ?>
                                                                    <div class="quiz-option <?php echo $isCorrect ? 'correct' : ''; ?>">
                                                                        <span class="font-semibold"><?php echo chr(65 + $optIndex); ?>.</span>
                                                                        <span><?php echo htmlspecialchars($option); ?></span>
                                                                        <?php if ($isCorrect): ?>
                                                                            <i class="fas fa-check-circle text-green-600 ml-auto"></i>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                <?php 
                                                                    endforeach;
                                                                endif;
                                                                ?>
                                                            </div>
                                                        <?php elseif ($question['type'] === 'true_false'): ?>
                                                            <div class="mt-3">
                                                                <div class="quiz-option <?php echo (isset($question['correct_option']) && $question['correct_option'] == 1) ? 'correct' : ''; ?>">
                                                                    <span class="font-semibold">A.</span>
                                                                    <span>True</span>
                                                                    <?php if (isset($question['correct_option']) && $question['correct_option'] == 1): ?>
                                                                        <i class="fas fa-check-circle text-green-600 ml-auto"></i>
                                                                    <?php endif; ?>
                                                                </div>
                                                                <div class="quiz-option <?php echo (isset($question['correct_option']) && $question['correct_option'] == 2) ? 'correct' : ''; ?>">
                                                                    <span class="font-semibold">B.</span>
                                                                    <span>False</span>
                                                                    <?php if (isset($question['correct_option']) && $question['correct_option'] == 2): ?>
                                                                        <i class="fas fa-check-circle text-green-600 ml-auto"></i>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                        <?php elseif ($question['type'] === 'short_answer' && !empty($question['correct_answer'])): ?>
                                                            <div class="mt-3">
                                                                <div class="bg-green-50 border-2 border-green-200 rounded-lg p-3">
                                                                    <span class="text-xs font-semibold text-green-700 uppercase">Correct Answer:</span>
                                                                    <p class="text-gray-900 font-medium mt-1"><?php echo htmlspecialchars($question['correct_answer']); ?></p>
                                                                </div>
                                                            </div>
                                                        <?php endif; ?>
                                                        
                                                        <?php if (!empty($question['explanation'])): ?>
                                                            <div class="mt-3 bg-blue-50 border border-blue-200 rounded-lg p-3">
                                                                <div class="flex items-start gap-2">
                                                                    <i class="fas fa-info-circle text-blue-600 mt-0.5"></i>
                                                                    <div>
                                                                        <span class="text-xs font-semibold text-blue-700 uppercase">Explanation:</span>
                                                                        <p class="text-sm text-gray-700 mt-1"><?php echo htmlspecialchars($question['explanation']); ?></p>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
</div>

<script>
    function toggleModule(index) {
        const content = document.getElementById('module-' + index);
        const arrow = document.getElementById('arrow-' + index);
        
        content.classList.toggle('active');
        arrow.classList.toggle('rotate-180');
    }
    
    // Auto-open first module
    document.addEventListener('DOMContentLoaded', function() {
        const firstModule = document.getElementById('module-0');
        const firstArrow = document.getElementById('arrow-0');
        if (firstModule) {
            firstModule.classList.add('active');
            firstArrow.classList.add('rotate-180');
        }
    });
</script>

</body>
</html>
