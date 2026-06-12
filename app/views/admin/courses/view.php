<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../../config/database.php';
$dbConfig = require __DIR__ . '/../../../config/database.php';

$dsn = sprintf("mysql:host=%s;port=%s;dbname=%s;charset=%s",
    $dbConfig['host'], $dbConfig['port'], $dbConfig['database'], $dbConfig['charset']
);

try {
    $db = new PDO($dsn, $dbConfig['username'], $dbConfig['password']);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

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

$courseId = $_GET['id'] ?? null;
if (!$courseId) {
    header('Location: /app/views/admin/courses/list.php');
    exit;
}

$courseModel = new Course($db);
$course = $courseModel->getById($courseId);

if (!$course) {
    header('Location: /app/views/admin/courses/list.php');
    exit;
}

// Fetch chapters with topics, content blocks, and quizzes
try {
    $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    $stmt = $db->prepare("SELECT * FROM chapters WHERE course_id = ? ORDER BY sort_order ASC, id ASC");
    $stmt->execute([$courseId]);
    $chaptersTemp = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $chapters = [];
    $chapterIndex = 0;
    $totalQuizzes = 0;

    foreach ($chaptersTemp as $chapterRow) {
        $chapters[$chapterIndex] = $chapterRow;
        
        // Fetch topics
        $stmt = $db->prepare("SELECT * FROM topics WHERE chapter_id = ? ORDER BY sort_order ASC");
        $stmt->execute([$chapterRow['id']]);
        $topicsTemp = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $chapters[$chapterIndex]['topics'] = [];
        $topicIndex = 0;
        
        foreach ($topicsTemp as $topicRow) {
            $chapters[$chapterIndex]['topics'][$topicIndex] = $topicRow;
            
            // Fetch content blocks
            $stmt = $db->prepare("SELECT * FROM content_blocks WHERE topic_id = ? ORDER BY sort_order ASC");
            $stmt->execute([$topicRow['id']]);
            $chapters[$chapterIndex]['topics'][$topicIndex]['content_blocks'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // ✅ FETCH QUIZ - Check by topic_id first, then content_block_id
            $stmt = $db->prepare("
                SELECT * FROM quizzes 
                WHERE topic_id = ? OR content_block_id IN (
                    SELECT id FROM content_blocks WHERE topic_id = ?
                )
                LIMIT 1
            ");
            $stmt->execute([$topicRow['id'], $topicRow['id']]);
            $quiz = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($quiz) {
                // Fetch questions for this quiz
                $stmt = $db->prepare("SELECT * FROM quiz_questions WHERE quiz_id = ? ORDER BY id ASC");
                $stmt->execute([$quiz['id']]);
                $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Parse options JSON
                foreach ($questions as $qIndex => $question) {
                    if (!empty($question['options'])) {
                        $questions[$qIndex]['options_array'] = json_decode($question['options'], true);
                    } else {
                        $questions[$qIndex]['options_array'] = [];
                    }
                }
                
                $quiz['questions'] = $questions;
                $chapters[$chapterIndex]['topics'][$topicIndex]['quiz'] = $quiz;
                $totalQuizzes++;
            } else {
                $chapters[$chapterIndex]['topics'][$topicIndex]['quiz'] = null;
            }
            
            $topicIndex++;
        }
        
        $chapterIndex++;
    }
    
    unset($chaptersTemp, $topicsTemp, $chapterRow, $topicRow);
    
} catch (PDOException $e) {
    error_log("Error fetching chapters: " . $e->getMessage());
    $chapters = [];
    $totalQuizzes = 0;
}

$enrollmentCount = $courseModel->getEnrollmentCount($courseId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Course - <?php echo htmlspecialchars($course['title'], ENT_QUOTES, 'UTF-8'); ?></title>
    
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
        .content-preview {
            max-height: 500px;
            overflow-y: auto;
        }
        .content-preview img {
            max-width: 100%;
            height: auto;
            border-radius: 8px;
            margin: 15px 0;
        }
        .content-preview pre {
            background: #1f2937;
            padding: 20px;
            border-radius: 8px;
            overflow-x: auto;
            color: #10b981;
        }
        .quiz-preview {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/../../components/sidebar-admin.php'; ?>

<div class="ml-64 min-h-screen">
    <header class="bg-white border-b border-gray-200 sticky top-0 z-30">
        <div class="px-8 py-6">
            <div class="flex items-center justify-between">
                <div>
                    <a href="/app/views/admin/courses/list.php" class="text-primary-600 hover:text-primary-700 text-sm font-semibold mb-2 inline-block">
                        ← Back to Courses
                    </a>
                    <h1 class="text-3xl font-bold text-gray-900"><?php echo html_entity_decode($course['title'], ENT_QUOTES, 'UTF-8'); ?></h1>
                    <p class="text-gray-600 mt-1">Complete course preview</p>
                </div>
                <div class="flex gap-3">
                    <a href="/app/views/admin/courses/edit.php?id=<?php echo $course['id']; ?>" class="bg-primary-600 hover:bg-primary-700 text-white px-6 py-3 rounded-lg font-semibold transition-colors">
                        Edit Course
                    </a>
                    <a href="/app/views/admin/courses/builder.php?id=<?php echo $course['id']; ?>" class="bg-gray-700 hover:bg-gray-800 text-white px-6 py-3 rounded-lg font-semibold transition-colors">
                        Course Builder
                    </a>
                </div>
            </div>
        </div>
    </header>

    <main class="p-8">
        <!-- Course Stats -->
        <div class="bg-white rounded-xl border border-gray-200 p-8 mb-6">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="bg-primary-50 rounded-lg p-6">
                    <div class="flex items-center gap-3">
                        <div class="bg-primary-600 rounded-lg p-3">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600 font-semibold">Enrollments</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo $enrollmentCount; ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-blue-50 rounded-lg p-6">
                    <div class="flex items-center gap-3">
                        <div class="bg-blue-600 rounded-lg p-3">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600 font-semibold">Price</p>
                            <p class="text-2xl font-bold text-gray-900">₹<?php echo number_format($course['price']); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Quiz Count -->
                <div class="bg-purple-50 rounded-lg p-6">
                    <div class="flex items-center gap-3">
                        <div class="bg-purple-600 rounded-lg p-3">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600 font-semibold">Quizzes</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo $totalQuizzes; ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-yellow-50 rounded-lg p-6">
                    <div class="flex items-center gap-3">
                        <div class="bg-yellow-600 rounded-lg p-3">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600 font-semibold">Status</p>
                            <span class="inline-block mt-1 px-3 py-1 text-sm font-bold rounded-full <?php echo $course['status'] === 'published' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700'; ?>">
                                <?php echo ucfirst($course['status']); ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="border-t border-gray-200 pt-6">
                <h2 class="text-xl font-bold text-gray-900 mb-4">Course Description</h2>
                <p class="text-gray-700 leading-relaxed"><?php echo nl2br(htmlspecialchars($course['description'])); ?></p>
            </div>
        </div>

        <!-- Course Content -->
        <div class="bg-white rounded-xl border border-gray-200 p-8">
            <h2 class="text-2xl font-bold text-gray-900 mb-6">Course Content</h2>
            
            <?php if (empty($chapters)): ?>
                <div class="text-center py-12">
                    <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    <p class="text-gray-600 font-semibold mb-4">No chapters added yet</p>
                    <a href="/app/views/admin/courses/builder.php?id=<?php echo $course['id']; ?>" class="inline-block bg-primary-600 hover:bg-primary-700 text-white px-6 py-3 rounded-lg font-semibold transition-colors">
                        Add Content
                    </a>
                </div>
            <?php else: ?>
                <div class="space-y-6">
                    <?php foreach ($chapters as $chapterIndex => $chapter): ?>
                        <div class="border-2 border-primary-200 rounded-xl overflow-hidden shadow-sm">
                            <!-- Chapter Header -->
                            <div class="bg-gradient-to-r from-primary-500 to-primary-600 px-6 py-5 flex items-center justify-between">
                                <div class="flex items-center gap-4">
                                    <span class="bg-white text-primary-600 w-12 h-12 rounded-full flex items-center justify-center font-bold text-xl shadow-lg">
                                        <?php echo $chapterIndex + 1; ?>
                                    </span>
                                    <h3 class="text-2xl font-bold text-white"><?php echo htmlspecialchars($chapter['title']); ?></h3>
                                </div>
                                <span class="text-sm text-white font-bold bg-white/30 px-5 py-2 rounded-full">
                                    <?php echo count($chapter['topics'] ?? []); ?> Topics
                                </span>
                            </div>
                            
                            <!-- Topics -->
                            <?php if (!empty($chapter['topics'])): ?>
                                <div class="divide-y divide-gray-200">
                                    <?php foreach ($chapter['topics'] as $topicIndex => $topic): ?>
                                        <div class="p-6 bg-gray-50">
                                            <div class="flex items-center justify-between mb-5">
                                                <div class="flex items-center gap-3">
                                                    <span class="bg-primary-100 text-primary-700 w-10 h-10 rounded-lg flex items-center justify-center font-bold text-lg">
                                                        <?php echo $topicIndex + 1; ?>
                                                    </span>
                                                    <h4 class="text-xl font-bold text-gray-900"><?php echo htmlspecialchars($topic['title']); ?></h4>
                                                </div>
                                                <div class="flex items-center gap-2">
                                                    <span class="text-xs px-4 py-2 bg-blue-100 text-blue-700 rounded-full font-bold">
                                                        <?php echo count($topic['content_blocks'] ?? []); ?> Blocks
                                                    </span>
                                                    <?php if (!empty($topic['quiz'])): ?>
                                                        <span class="text-xs px-4 py-2 bg-purple-100 text-purple-700 rounded-full font-bold flex items-center gap-1">
                                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                                                            </svg>
                                                            Quiz
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            
                                            <!-- Content Blocks -->
                                            <?php if (!empty($topic['content_blocks'])): ?>
                                                <div class="space-y-5 bg-white rounded-xl p-6 border-2 border-gray-200 content-preview mb-5">
                                                    <?php foreach ($topic['content_blocks'] as $blockIndex => $block): ?>
                                                        <div class="border-l-4 border-primary-500 pl-5 py-3">
                                                            <div class="text-xs text-primary-600 mb-3 font-bold uppercase">
                                                                Block <?php echo $blockIndex + 1; ?>: <?php echo htmlspecialchars($block['block_type'] ?? 'text'); ?>
                                                            </div>
                                                            
                                                            <?php 
                                                            $blockType = $block['block_type'] ?? 'text';
                                                            $blockContent = $block['content'] ?? '';
                                                            ?>
                                                            
                                                            <?php if ($blockType === 'text'): ?>
                                                                <div class="prose max-w-none">
                                                                    <?php echo $blockContent; ?>
                                                                </div>
                                                            <?php elseif ($blockType === 'image'): ?>
                                                                <img src="<?php echo htmlspecialchars($blockContent); ?>" alt="Content" class="rounded-lg shadow-md">
                                                            <?php elseif ($blockType === 'video'): ?>
                                                                <div class="aspect-video rounded-lg overflow-hidden bg-black">
                                                                    <?php if (strpos($blockContent, 'youtube') !== false): ?>
                                                                        <?php
                                                                        preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/ ]{11})/', $blockContent, $match);
                                                                        $videoId = $match[1] ?? '';
                                                                        ?>
                                                                        <?php if ($videoId): ?>
                                                                            <iframe class="w-full h-full" src="https://www.youtube.com/embed/<?php echo $videoId; ?>" frameborder="0" allowfullscreen></iframe>
                                                                        <?php endif; ?>
                                                                    <?php else: ?>
                                                                        <video controls class="w-full h-full">
                                                                            <source src="<?php echo htmlspecialchars($blockContent); ?>">
                                                                        </video>
                                                                    <?php endif; ?>
                                                                </div>
                                                            <?php elseif ($blockType === 'code'): ?>
                                                                <pre><code><?php echo htmlspecialchars($blockContent); ?></code></pre>
                                                            <?php else: ?>
                                                                <p class="text-gray-600">Unknown type</p>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <!-- ✅ QUIZ PREVIEW -->
                                            <?php if (!empty($topic['quiz'])): ?>
                                                <?php $quiz = $topic['quiz']; ?>
                                                <div class="quiz-preview rounded-xl p-6 text-white shadow-lg">
                                                    <div class="flex items-center justify-between mb-5">
                                                        <div class="flex items-center gap-3">
                                                            <div class="bg-white/20 backdrop-blur rounded-lg p-3">
                                                                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                                                                </svg>
                                                            </div>
                                                            <div>
                                                                <h5 class="text-2xl font-bold">📝 <?php echo htmlspecialchars($quiz['title']); ?></h5>
                                                                <?php if (!empty($quiz['description'])): ?>
                                                                    <p class="text-sm text-white/80 mt-1"><?php echo htmlspecialchars($quiz['description']); ?></p>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                        <div class="text-right">
                                                            <div class="text-sm font-semibold text-white/80">Questions</div>
                                                            <div class="text-3xl font-bold"><?php echo count($quiz['questions'] ?? []); ?></div>
                                                        </div>
                                                    </div>
                                                    
                                                    <!-- Quiz Meta Info -->
                                                    <div class="grid grid-cols-3 gap-4 mb-6">
                                                        <div class="bg-white/10 backdrop-blur rounded-lg p-4 text-center">
                                                            <div class="text-sm font-semibold text-white/80">Passing Score</div>
                                                            <div class="text-2xl font-bold"><?php echo $quiz['pass_score'] ?? 70; ?>%</div>
                                                        </div>
                                                        
                                                        <div class="bg-white/10 backdrop-blur rounded-lg p-4 text-center">
                                                            <div class="text-sm font-semibold text-white/80">Time Limit</div>
                                                            <div class="text-2xl font-bold"><?php echo $quiz['time_limit'] ?? 30; ?> min</div>
                                                        </div>
                                                        
                                                        <div class="bg-white/10 backdrop-blur rounded-lg p-4 text-center">
                                                            <div class="text-sm font-semibold text-white/80">Max Attempts</div>
                                                            <div class="text-2xl font-bold"><?php echo ($quiz['max_attempts'] == 0 || empty($quiz['max_attempts'])) ? '∞' : $quiz['max_attempts']; ?></div>
                                                        </div>
                                                    </div>
                                                    
                                                    <!-- Questions Preview -->
                                                    <?php if (!empty($quiz['questions'])): ?>
                                                        <div class="bg-white/10 backdrop-blur rounded-xl p-5">
                                                            <h6 class="font-bold text-lg mb-4">Questions Preview:</h6>
                                                            <div class="space-y-4 max-h-96 overflow-y-auto">
                                                                <?php foreach ($quiz['questions'] as $qIndex => $question): ?>
                                                                    <div class="bg-white/10 backdrop-blur rounded-lg p-4">
                                                                        <div class="flex items-start gap-3">
                                                                            <span class="bg-white/20 text-white w-8 h-8 rounded-full flex items-center justify-center font-bold text-sm flex-shrink-0">
                                                                                <?php echo $qIndex + 1; ?>
                                                                            </span>
                                                                            <div class="flex-1">
                                                                                <p class="font-semibold mb-3"><?php echo htmlspecialchars($question['question_text']); ?></p>
                                                                                
                                                                                <?php if (!empty($question['options_array'])): ?>
                                                                                    <div class="space-y-2">
                                                                                        <?php foreach ($question['options_array'] as $optIndex => $option): ?>
                                                                                            <div class="flex items-center gap-2 text-sm">
                                                                                                <span class="bg-white/20 w-6 h-6 rounded flex items-center justify-center text-xs font-bold">
                                                                                                    <?php echo is_numeric($optIndex) ? chr(65 + $optIndex) : $optIndex; ?>
                                                                                                </span>
                                                                                                <span><?php echo htmlspecialchars($option); ?></span>
                                                                                            </div>
                                                                                        <?php endforeach; ?>
                                                                                    </div>
                                                                                <?php endif; ?>
                                                                                
                                                                                <div class="mt-3 text-xs text-white/90 bg-white/10 px-3 py-2 rounded">
                                                                                    ✓ Correct Answer: <strong><?php echo htmlspecialchars($question['correct_answer'] ?? 'N/A'); ?></strong>
                                                                                    <?php if (!empty($question['points'])): ?>
                                                                                        | Points: <strong><?php echo $question['points']; ?></strong>
                                                                                    <?php endif; ?>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        </div>
                                                    <?php else: ?>
                                                        <p class="text-white/60 text-center italic py-4 bg-white/10 rounded-lg">No questions added yet</p>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

</body>
</html>
