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

$courseId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$courseId) {
    header('Location: /app/views/admin/courses/list.php');
    exit;
}

$courseModel = new Course($db);
$course = $courseModel->findById($courseId);

if (!$course) {
    header('Location: /app/views/admin/courses/list.php');
    exit;
}

// Check and add is_mandatory column if not exists
try {
    $stmt = $db->query("SHOW COLUMNS FROM topics LIKE 'is_mandatory'");
    if ($stmt->rowCount() == 0) {
        $db->exec("ALTER TABLE topics ADD COLUMN is_mandatory TINYINT(1) DEFAULT 1 AFTER title");
    }
} catch (Exception $e) {
    // Column might already exist
}

// Get all chapters with topics
$db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
$stmt = $db->prepare("SELECT * FROM chapters WHERE course_id = ? ORDER BY sort_order ASC, id ASC");
$stmt->execute([$courseId]);
$chaptersTemp = $stmt->fetchAll(PDO::FETCH_ASSOC);

$chapters = [];
$chapterIndex = 0;

foreach ($chaptersTemp as $chapterRow) {
    $chapters[$chapterIndex] = $chapterRow;
    
    $stmt = $db->prepare("SELECT * FROM topics WHERE chapter_id = ? ORDER BY sort_order ASC");
    $stmt->execute([$chapterRow['id']]);
    $topicsTemp = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $chapters[$chapterIndex]['topics'] = [];
    $topicIndex = 0;
    
    foreach ($topicsTemp as $topicRow) {
        $chapters[$chapterIndex]['topics'][$topicIndex] = $topicRow;
        
        // Get content blocks
        $stmt = $db->prepare("SELECT * FROM content_blocks WHERE topic_id = ? ORDER BY sort_order ASC");
        $stmt->execute([$topicRow['id']]);
        $chapters[$chapterIndex]['topics'][$topicIndex]['content_blocks'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Check if topic has quiz
        $quizStmt = $db->prepare("
            SELECT q.id, q.title 
            FROM quizzes q
            INNER JOIN content_blocks cb ON q.content_block_id = cb.id
            WHERE cb.topic_id = ? AND cb.type = 'quiz'
            LIMIT 1
        ");
        $quizStmt->execute([$topicRow['id']]);
        $quiz = $quizStmt->fetch(PDO::FETCH_ASSOC);
        $chapters[$chapterIndex]['topics'][$topicIndex]['has_quiz'] = $quiz ? true : false;
        
        $topicIndex++;
    }
    
    $chapterIndex++;
}

unset($chaptersTemp, $topicsTemp, $chapterRow, $topicRow);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Course Builder - <?php echo htmlspecialchars($course['title']); ?></title>
    
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
        
        .chapter-card {
            background: white;
            border: 2px solid #e5e7eb;
            border-radius: 16px;
            transition: all 0.3s ease;
        }
        
        .chapter-card:hover {
            border-color: #22c55e;
            box-shadow: 0 4px 20px rgba(34, 197, 94, 0.1);
        }
        
        .topic-card {
            background: #f9fafb;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            transition: all 0.3s ease;
        }
        
        .topic-card:hover {
            background: white;
            border-color: #22c55e;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(4px);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .modal.active {
            display: flex;
        }
        
        .modal-content {
            background: white;
            border-radius: 24px;
            max-width: 900px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
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
        
        .btn-primary {
            background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
            color: white;
            padding: 14px 24px;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(34, 197, 94, 0.3);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(34, 197, 94, 0.4);
        }
        
        .content-preview {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 16px;
            margin-top: 12px;
        }
        
        .editor-btn {
            padding: 8px 12px;
            background: white;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 38px;
            height: 38px;
        }
        
        .editor-btn:hover {
            background: #f3f4f6;
            border-color: #22c55e;
        }
        
        .editor-btn:active, .editor-btn.active {
            background: #dcfce7;
            border-color: #22c55e;
            transform: scale(0.95);
        }
        
      #contentEditor {
    min-height: 400px;
    max-height: 500px;
    overflow-y: auto;
    padding: 20px;
    border: 2px solid #e5e7eb;
    border-radius: 0 0 12px 12px;
    background: white;
    line-height: 1.6;
}

#contentEditor:empty:before {
    content: attr(placeholder);
    color: #9ca3af;
}

#contentEditor:focus {
    outline: none;
    border-color: #22c55e;
}

/* Headings */
#contentEditor h1 {
    font-size: 2em;
    font-weight: bold;
    margin: 0.67em 0;
    line-height: 1.2;
}

#contentEditor h2 {
    font-size: 1.5em;
    font-weight: bold;
    margin: 0.75em 0;
    line-height: 1.3;
}

#contentEditor h3 {
    font-size: 1.17em;
    font-weight: bold;
    margin: 0.83em 0;
    line-height: 1.4;
}

/* Lists */
#contentEditor ul, #contentEditor ol {
    margin-left: 40px;
    margin-top: 1em;
    margin-bottom: 1em;
    padding-left: 0;
}

#contentEditor ul {
    list-style-type: disc;
}

#contentEditor ol {
    list-style-type: decimal;
}

#contentEditor li {
    margin-bottom: 0.5em;
}

/* Images - FIXED */
#contentEditor img {
    max-width: 100%;
    height: auto;
    border-radius: 8px;
    margin: 10px 0;
    display: block;
    cursor: pointer;
}

/* Videos - FIXED */
#contentEditor video {
    max-width: 100%;
    border-radius: 12px;
    margin: 20px 0;
    display: block;
}

/* YouTube/Vimeo iframes - FIXED */
#contentEditor iframe {
    max-width: 100%;
    border-radius: 12px;
    margin: 20px 0;
    display: block;
    min-height: 400px;
}

/* Responsive video containers - FIXED */
#contentEditor div[style*="position: relative"][style*="padding-bottom"] {
    position: relative;
    padding-bottom: 56.25%;
    height: 0;
    overflow: hidden;
    max-width: 100%;
    margin: 20px 0;
    border-radius: 12px;
}

#contentEditor div[style*="position: relative"] iframe {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    border-radius: 12px;
}

/* Links */
#contentEditor a {
    color: #2563eb;
    text-decoration: underline;
}

#contentEditor a:hover {
    color: #1e40af;
}

/* Code */
#contentEditor code {
    background: #f3f4f6;
    padding: 2px 6px;
    border-radius: 4px;
    font-family: 'Courier New', monospace;
    font-size: 0.9em;
}

#contentEditor pre {
    background: #1f2937;
    color: #f9fafb;
    padding: 16px;
    border-radius: 8px;
    overflow-x: auto;
    margin: 10px 0;
}

#contentEditor pre code {
    background: transparent;
    padding: 0;
    color: #f9fafb;
}

/* Blockquote */
#contentEditor blockquote {
    border-left: 4px solid #22c55e;
    padding-left: 16px;
    margin: 10px 0;
    color: #6b7280;
    font-style: italic;
}

/* Horizontal Rule */
#contentEditor hr {
    border: none;
    border-top: 2px solid #e5e7eb;
    margin: 20px 0;
}

/* Paragraphs */
#contentEditor p {
    margin: 0.5em 0;
}

/* Strong/Bold */
#contentEditor strong, #contentEditor b {
    font-weight: bold;
}

/* Emphasis/Italic */
#contentEditor em, #contentEditor i {
    font-style: italic;
}

/* Underline */
#contentEditor u {
    text-decoration: underline;
}

/* Strikethrough */
#contentEditor s, #contentEditor strike {
    text-decoration: line-through;
}
/* Make videos and iframes non-selectable in edit mode */
#contentEditor video,
#contentEditor iframe {
    pointer-events: auto;
}

#contentEditor div[contenteditable="false"] {
    outline: 2px dashed #e5e7eb;
    outline-offset: 4px;
    cursor: default;
    user-select: none;
}

#contentEditor div[contenteditable="false"]:hover {
    outline-color: #22c55e;
}

/* Video wrapper */
#contentEditor div[contenteditable="false"] video {
    display: block;
    max-width: 100%;
    border-radius: 12px;
}

/* YouTube/Vimeo wrapper */
#contentEditor div[contenteditable="false"][style*="position: relative"] {
    position: relative;
    padding-bottom: 56.25%;
    height: 0;
    overflow: hidden;
    max-width: 100%;
    margin: 20px 0;
    border-radius: 12px;
}

#contentEditor div[contenteditable="false"][style*="position: relative"] iframe {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    border-radius: 12px;
    border: none;
}


        
        .icon-grid {
            display: grid;
            grid-template-columns: repeat(8, 1fr);
            gap: 8px;
            max-height: 200px;
            overflow-y: auto;
            padding: 10px;
            background: #f9fafb;
            border-radius: 8px;
        }
        
        .icon-btn {
            padding: 10px;
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            cursor: pointer;
            font-size: 18px;
            transition: all 0.2s;
        }
        
        .icon-btn:hover {
            background: #dcfce7;
            border-color: #22c55e;
        }
        
        /* Video wrapper in editor */
#contentEditor div[contenteditable="false"] {
    outline: 2px dashed #e5e7eb;
    outline-offset: 4px;
    cursor: default;
    user-select: none;
}

#contentEditor div[contenteditable="false"]:hover {
    outline-color: #22c55e;
}

#contentEditor div[contenteditable="false"] video {
    display: block;
    max-width: 100%;
    border-radius: 8px;
    background: #000;
}

/* Progress bar animations */
@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

#videoUploadProgress,
#imageUploadProgress {
    animation: pulse 2s ease-in-out infinite;
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
                    <div class="flex items-center gap-2 text-sm text-gray-500 mb-2">
                        <a href="/app/views/admin/courses/list.php" class="hover:text-primary-600">Courses</a>
                        <span>/</span>
                        <span>Builder</span>
                    </div>
                    <h1 class="text-3xl font-bold text-gray-900">📚 <?php echo htmlspecialchars($course['title']); ?></h1>
                    <p class="text-gray-600 mt-1">Build your course content</p>
                </div>
                <div class="flex items-center gap-3">
                    <button onclick="window.history.back()" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-6 py-3 rounded-lg font-semibold transition-colors">
                        ← Back
                    </button>
                    <a href="/app/views/admin/courses/settings.php?id=<?php echo $course['id']; ?>" class="bg-gray-600 hover:bg-gray-700 text-white px-6 py-3 rounded-lg font-semibold transition-colors">
                        ⚙️ Settings
                    </a>
                    <button onclick="openAddChapterModal()" class="btn-primary flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                        Add Chapter
                    </button>
                </div>
            </div>
        </div>
    </header>

    <main class="p-8">
        <div id="alertContainer" class="mb-6 max-w-5xl mx-auto"></div>

        <?php if (empty($chapters)): ?>
            <div class="max-w-3xl mx-auto">
                <div class="bg-white rounded-2xl border-2 border-dashed border-gray-300 p-12 text-center">
                    <div class="w-24 h-24 bg-primary-100 rounded-full flex items-center justify-center mx-auto mb-6">
                        <svg class="w-12 h-12 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                        </svg>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 mb-3">Start Building Your Course</h3>
                    <p class="text-gray-600 mb-8 text-lg">Create your first chapter to organize your content</p>
                    <button onclick="openAddChapterModal()" class="btn-primary inline-flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                        Add First Chapter
                    </button>
                </div>
            </div>
        <?php else: ?>
            <div class="max-w-5xl mx-auto space-y-6">
                <?php foreach ($chapters as $index => $chapter): ?>
                    <div class="chapter-card">
                        <div class="p-6">
                            <div class="flex items-center justify-between mb-6">
                                <div class="flex items-center gap-4 flex-1">
                                    <div class="w-12 h-12 bg-gradient-to-br from-primary-500 to-primary-700 rounded-xl flex items-center justify-center text-white font-bold text-xl">
                                        <?php echo $index + 1; ?>
                                    </div>
                                    <div class="flex-1">
                                        <h3 class="text-xl font-bold text-gray-900"><?php echo htmlspecialchars($chapter['title']); ?></h3>
                                        <p class="text-sm text-gray-500 mt-1"><?php echo count($chapter['topics']); ?> topics</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <button onclick="openAddTopicModal(<?php echo $chapter['id']; ?>)" class="bg-primary-50 text-primary-700 hover:bg-primary-100 px-4 py-2 rounded-lg font-semibold text-sm transition-colors">
                                        + Add Topic
                                    </button>
                                    <button onclick="editChapter(<?php echo $chapter['id']; ?>, '<?php echo addslashes($chapter['title']); ?>')" class="text-gray-600 hover:text-gray-800 p-2 rounded-lg hover:bg-gray-100 transition-colors">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                        </svg>
                                    </button>
                                    <button onclick="deleteChapter(<?php echo $chapter['id']; ?>)" class="text-red-600 hover:text-red-700 p-2 rounded-lg hover:bg-red-50 transition-colors">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            <?php if (!empty($chapter['topics'])): ?>
                                <div class="space-y-4 pl-16">
                                    <?php foreach ($chapter['topics'] as $topicIndex => $topic): ?>
                                        <div class="topic-card p-5">
                                            <div class="flex items-start justify-between mb-4">
                                                <div class="flex items-start gap-3 flex-1">
                                                    <div class="w-8 h-8 bg-primary-100 rounded-lg flex items-center justify-center text-primary-700 font-bold text-sm flex-shrink-0">
                                                        <?php echo $topicIndex + 1; ?>
                                                    </div>
                                                    <div class="flex-1">
                                                        <div class="flex items-center gap-2 mb-1">
                                                            <h4 class="font-bold text-gray-900 text-lg"><?php echo htmlspecialchars($topic['title']); ?></h4>
                                                            <?php if (isset($topic['is_mandatory']) && $topic['is_mandatory']): ?>
                                                                <span class="text-xs font-semibold bg-orange-100 text-orange-700 px-2 py-1 rounded-full">Required</span>
                                                            <?php endif; ?>
                                                            <?php if ($topic['has_quiz']): ?>
                                                                <span class="text-xs font-semibold bg-purple-100 text-purple-700 px-2 py-1 rounded-full">📝 Quiz</span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <p class="text-sm text-gray-600">
                                                            <?php echo count($topic['content_blocks']); ?> content blocks
                                                        </p>
                                                    </div>
                                                </div>
                                                <div class="flex items-center gap-2">
                                                    <button onclick="editTopic(<?php echo $topic['id']; ?>, '<?php echo addslashes($topic['title']); ?>', <?php echo (isset($topic['is_mandatory']) && $topic['is_mandatory']) ? 'true' : 'false'; ?>)" class="text-gray-600 hover:text-gray-800 p-2 rounded-lg hover:bg-gray-100 transition-colors">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                                        </svg>
                                                    </button>
                                                    <button onclick="deleteTopic(<?php echo $topic['id']; ?>)" class="text-red-600 hover:text-red-700 p-2 rounded-lg hover:bg-red-50 transition-colors">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                        </svg>
                                                    </button>
                                                </div>
                                            </div>

                                            <div class="flex gap-3 mt-4 pt-4 border-t border-gray-200">
                                                <button onclick="openContentEditor(<?php echo $topic['id']; ?>)" class="flex-1 bg-blue-50 text-blue-700 hover:bg-blue-100 px-4 py-3 rounded-lg font-semibold text-sm transition-colors flex items-center justify-center gap-2">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                                    </svg>
                                                    Edit Content
                                                </button>
                                                
                                                <button onclick="openQuizEditor(<?php echo $topic['id']; ?>)" class="flex-1 bg-purple-50 text-purple-700 hover:bg-purple-100 px-4 py-3 rounded-lg font-semibold text-sm transition-colors flex items-center justify-center gap-2">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                                                    </svg>
                                                    <?php echo $topic['has_quiz'] ? 'Edit Quiz' : 'Add Quiz'; ?>
                                                </button>
                                            </div>

                                            <?php if (!empty($topic['content_blocks'])): ?>
                                                <div class="content-preview mt-4">
                                                    <p class="text-xs font-semibold text-gray-500 uppercase mb-2">Content Preview:</p>
                                                    <?php foreach ($topic['content_blocks'] as $block): ?>
                                                        <?php if ($block['type'] === 'text'): ?>
                                                            <div class="text-sm text-gray-700 line-clamp-3">
                                                                <?php echo substr(strip_tags($block['content']), 0, 150); ?>...
                                                            </div>
                                                        <?php elseif ($block['type'] === 'quiz'): ?>
                                                            <div class="text-sm text-purple-700 font-semibold">
                                                                📝 Quiz attached
                                                            </div>
                                                        <?php endif; ?>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="pl-16">
                                    <button onclick="openAddTopicModal(<?php echo $chapter['id']; ?>)" class="text-primary-600 hover:text-primary-700 font-semibold">
                                        + Add First Topic
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
</div>

<!-- Add Chapter Modal -->
<div id="addChapterModal" class="modal">
    <div class="modal-content">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-2xl font-bold text-gray-900">📖 Add New Chapter</h2>
            <button onclick="closeModal('addChapterModal')" class="text-gray-400 hover:text-gray-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        <form id="addChapterForm" class="space-y-4">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Chapter Title *</label>
                <input type="text" name="title" class="form-input" placeholder="e.g., Introduction to JavaScript" required>
            </div>
            <div class="flex gap-3 pt-4">
                <button type="submit" class="flex-1 btn-primary">Create Chapter</button>
                <button type="button" onclick="closeModal('addChapterModal')" class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 px-6 py-3 rounded-lg font-semibold transition-colors">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Chapter Modal -->
<div id="editChapterModal" class="modal">
    <div class="modal-content">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-2xl font-bold text-gray-900">✏️ Edit Chapter</h2>
            <button onclick="closeModal('editChapterModal')" class="text-gray-400 hover:text-gray-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        <form id="editChapterForm" class="space-y-4">
            <input type="hidden" name="chapter_id" id="editChapterId">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Chapter Title *</label>
                <input type="text" name="title" id="editChapterTitle" class="form-input" required>
            </div>
            <div class="flex gap-3 pt-4">
                <button type="submit" class="flex-1 btn-primary">Save Changes</button>
                <button type="button" onclick="closeModal('editChapterModal')" class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 px-6 py-3 rounded-lg font-semibold transition-colors">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Add Topic Modal -->
<div id="addTopicModal" class="modal">
    <div class="modal-content">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-2xl font-bold text-gray-900">📝 Add New Topic</h2>
            <button onclick="closeModal('addTopicModal')" class="text-gray-400 hover:text-gray-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        <form id="addTopicForm" class="space-y-4">
            <input type="hidden" name="chapter_id" id="topicChapterId">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Topic Title *</label>
                <input type="text" name="title" class="form-input" placeholder="e.g., Variables and Data Types" required>
            </div>
            <div>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="is_mandatory" value="1" checked class="w-4 h-4 text-primary-600 border-gray-300 rounded focus:ring-primary-500">
                    <span class="text-sm font-semibold text-gray-700">Required (students must complete this)</span>
                </label>
            </div>
            <div class="flex gap-3 pt-4">
                <button type="submit" class="flex-1 btn-primary">Create Topic</button>
                <button type="button" onclick="closeModal('addTopicModal')" class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 px-6 py-3 rounded-lg font-semibold transition-colors">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Topic Modal -->
<div id="editTopicModal" class="modal">
    <div class="modal-content">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-2xl font-bold text-gray-900">✏️ Edit Topic</h2>
            <button onclick="closeModal('editTopicModal')" class="text-gray-400 hover:text-gray-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        <form id="editTopicForm" class="space-y-4">
            <input type="hidden" name="topic_id" id="editTopicId">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Topic Title *</label>
                <input type="text" name="title" id="editTopicTitle" class="form-input" required>
            </div>
            <div>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="is_mandatory" id="editTopicMandatory" value="1" class="w-4 h-4 text-primary-600 border-gray-300 rounded focus:ring-primary-500">
                    <span class="text-sm font-semibold text-gray-700">Required (students must complete this)</span>
                </label>
            </div>
            <div class="flex gap-3 pt-4">
                <button type="submit" class="flex-1 btn-primary">Save Changes</button>
                <button type="button" onclick="closeModal('editTopicModal')" class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 px-6 py-3 rounded-lg font-semibold transition-colors">Cancel</button>
            </div>
        </form>
    </div>
</div>
<!-- Content Editor Modal - FIXED WITH SEPARATE ICONS -->
<div id="contentEditorModal" class="modal">
    <div class="modal-content" style="max-width: 1200px;">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-2xl font-bold text-gray-900">🎨 Content Editor</h2>
            <button onclick="closeModal('contentEditorModal')" class="text-gray-400 hover:text-gray-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
            <p class="text-sm text-blue-700 font-semibold">
                💡 Use <span class="bg-green-100 px-2 py-0.5 rounded">📤 Upload</span> buttons to upload from device or <span class="bg-purple-100 px-2 py-0.5 rounded">🔗 URL</span> buttons for online links!
            </p>
        </div>
        
        <form id="contentEditorForm" class="space-y-0">
            <input type="hidden" name="topic_id" id="contentEditorTopicId">
            
            <!-- Toolbar -->
            <div class="border border-gray-300 rounded-t-lg bg-gray-50 p-3 flex items-center gap-2 flex-wrap">
                <!-- Text Formatting -->
                <div class="flex items-center gap-1 border-r border-gray-300 pr-2">
                    <button type="button" onclick="formatText('bold')" class="editor-btn" title="Bold">
                        <span class="font-bold">B</span>
                    </button>
                    <button type="button" onclick="formatText('italic')" class="editor-btn" title="Italic">
                        <span class="italic font-semibold">I</span>
                    </button>
                    <button type="button" onclick="formatText('underline')" class="editor-btn" title="Underline">
                        <span class="underline font-semibold">U</span>
                    </button>
                    <button type="button" onclick="formatText('strikeThrough')" class="editor-btn" title="Strikethrough">
                        <span class="line-through">S</span>
                    </button>
                </div>
                
                <!-- Headings -->
                <div class="flex items-center gap-1 border-r border-gray-300 pr-2">
                    <button type="button" onclick="insertHeading('h1')" class="editor-btn" title="Heading 1">
                        <span class="text-lg font-bold">H1</span>
                    </button>
                    <button type="button" onclick="insertHeading('h2')" class="editor-btn" title="Heading 2">
                        <span class="text-base font-bold">H2</span>
                    </button>
                    <button type="button" onclick="insertHeading('h3')" class="editor-btn" title="Heading 3">
                        <span class="text-sm font-bold">H3</span>
                    </button>
                </div>
                
                <!-- Lists -->
                <div class="flex items-center gap-1 border-r border-gray-300 pr-2">
                    <button type="button" onclick="formatText('insertUnorderedList')" class="editor-btn" title="Bullet List">
                        <i class="fas fa-list-ul"></i>
                    </button>
                    <button type="button" onclick="formatText('insertOrderedList')" class="editor-btn" title="Numbered List">
                        <i class="fas fa-list-ol"></i>
                    </button>
                </div>
                
                <!-- Alignment -->
                <div class="flex items-center gap-1 border-r border-gray-300 pr-2">
                    <button type="button" onclick="formatText('justifyLeft')" class="editor-btn" title="Align Left">
                        <i class="fas fa-align-left"></i>
                    </button>
                    <button type="button" onclick="formatText('justifyCenter')" class="editor-btn" title="Align Center">
                        <i class="fas fa-align-center"></i>
                    </button>
                    <button type="button" onclick="formatText('justifyRight')" class="editor-btn" title="Align Right">
                        <i class="fas fa-align-right"></i>
                    </button>
                </div>
                
                <!-- Image Options - SEPARATE ICONS -->
                <div class="flex items-center gap-1 border-r border-gray-300 pr-2">
                    <!-- Upload Image from Device -->
                    <button type="button" onclick="document.getElementById('imageUploadInput').click()" class="editor-btn bg-green-50 border-green-300" title="Upload Image from Device">
                        <i class="fas fa-upload text-green-600"></i>
                        <i class="fas fa-image text-green-600 text-xs ml-0.5"></i>
                    </button>
                    
                    <!-- Insert Image from URL -->
                    <button type="button" onclick="insertImageURL()" class="editor-btn bg-blue-50 border-blue-300" title="Insert Image from URL">
                        <i class="fas fa-link text-blue-600"></i>
                        <i class="fas fa-image text-blue-600 text-xs ml-0.5"></i>
                    </button>
                </div>
                
                <!-- Video Options - SEPARATE ICONS -->
                <div class="flex items-center gap-1 border-r border-gray-300 pr-2">
                    <!-- Upload Video from Device -->
                    <button type="button" onclick="document.getElementById('videoUploadInput').click()" class="editor-btn bg-purple-50 border-purple-300" title="Upload Video from Device">
                        <i class="fas fa-upload text-purple-600"></i>
                        <i class="fas fa-video text-purple-600 text-xs ml-0.5"></i>
                    </button>
                    
                    <!-- Insert Video from URL (YouTube/Vimeo) -->
                    <button type="button" onclick="insertVideo()" class="editor-btn bg-red-50 border-red-300" title="Embed YouTube/Vimeo Video">
                        <i class="fas fa-link text-red-600"></i>
                        <i class="fas fa-video text-red-600 text-xs ml-0.5"></i>
                    </button>
                </div>
                
                <!-- Link -->
                <div class="flex items-center gap-1 border-r border-gray-300 pr-2">
                    <button type="button" onclick="insertLink()" class="editor-btn" title="Insert Link">
                        <i class="fas fa-link"></i>
                    </button>
                </div>
                
                <!-- More Options -->
                <div class="flex items-center gap-1 border-r border-gray-300 pr-2">
                    <button type="button" onclick="insertBlockquote()" class="editor-btn" title="Quote">
                        <i class="fas fa-quote-right"></i>
                    </button>
                    <button type="button" onclick="insertCode()" class="editor-btn" title="Code">
                        <i class="fas fa-code"></i>
                    </button>
                    <button type="button" onclick="insertHorizontalRule()" class="editor-btn" title="Horizontal Line">
                        <i class="fas fa-minus"></i>
                    </button>
                </div>
                
                <!-- Clear -->
                <div class="flex items-center gap-1">
                    <button type="button" onclick="clearFormatting()" class="editor-btn text-red-600" title="Clear Formatting">
                        <i class="fas fa-eraser"></i>
                    </button>
                </div>
            </div>
            
            <!-- Editor Area -->
            <div 
                id="contentEditor" 
                contenteditable="true"
                placeholder="Start typing your content here... Use the toolbar to format text, add images, videos, and more!"
            ></div>
            
            <!-- Hidden File Inputs -->
            <input type="file" id="imageUploadInput" accept="image/*" style="display:none" onchange="handleImageUpload(event)">
            <input type="file" id="videoUploadInput" accept="video/*" style="display:none" onchange="handleVideoUpload(event)">
            
            <!-- Submit -->
            <div class="flex gap-3 pt-4">
                <button type="submit" class="flex-1 btn-primary">
                    <i class="fas fa-save mr-2"></i>Save Content
                </button>
                <button type="button" onclick="closeModal('contentEditorModal')" class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 px-6 py-3 rounded-lg font-semibold transition-colors">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Quiz Editor Modal -->
<div id="quizEditorModal" class="modal">
    <div class="modal-content" style="max-width: 900px;">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-2xl font-bold text-gray-900">📝 Quiz Editor</h2>
            <button onclick="closeModal('quizEditorModal')" class="text-gray-400 hover:text-gray-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        
        <div class="bg-purple-50 border border-purple-200 rounded-lg p-4 mb-6">
            <p class="text-sm text-purple-700 font-semibold">
                💡 Create quizzes to test student knowledge! Add multiple-choice questions with explanations.
            </p>
        </div>
        
        <form id="quizEditorForm" class="space-y-6">
            <input type="hidden" name="topic_id" id="quizTopicId">
            
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Quiz Title *</label>
                <input type="text" name="title" id="quizTitle" class="form-input" placeholder="e.g., Test Your Knowledge" required>
            </div>
            
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Description</label>
                <textarea name="description" id="quizDescription" class="form-textarea" rows="3" placeholder="Brief description of what this quiz covers..."></textarea>
            </div>
            
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Passing Score (%)</label>
                    <input type="number" name="passing_score" id="quizPassingScore" class="form-input" value="70" min="0" max="100" required>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Time Limit (minutes)</label>
                    <input type="number" name="time_limit" id="quizTimeLimit" class="form-input" value="30" min="1">
                </div>
            </div>
            
            <div class="border-t border-gray-300 pt-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-bold text-gray-900">Questions</h3>
                    <button type="button" onclick="addQuestion()" class="bg-primary-50 text-primary-700 hover:bg-primary-100 px-4 py-2 rounded-lg font-semibold text-sm transition-colors flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                        Add Question
                    </button>
                </div>
                <div id="questionsContainer" class="space-y-4"></div>
            </div>
            
            <div class="flex gap-3 pt-4">
                <button type="submit" class="flex-1 btn-primary">
                    <i class="fas fa-save mr-2"></i>Save Quiz
                </button>
                <button type="button" onclick="closeModal('quizEditorModal')" class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 px-6 py-3 rounded-lg font-semibold transition-colors">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<script>
const courseId = <?php echo $courseId; ?>;

// 🔥 ENHANCED: HANDLE IMAGE UPLOAD WITH PROGRESS BAR
async function handleImageUpload(event) {
    const file = event.target.files[0];
    if (!file) return;
    
    if (!file.type.startsWith('image/')) {
        alert('❌ Please select an image file');
        return;
    }
    
    const maxSize = 10 * 1024 * 1024; // 10MB
    if (file.size > maxSize) {
        alert('❌ Image file is too large. Maximum size is 10MB');
        return;
    }
    
    const editor = document.getElementById('contentEditor');
    
    // Create progress container
    const progressContainer = document.createElement('div');
    progressContainer.id = 'imageUploadProgress';
    progressContainer.style.cssText = 'background: white; border: 2px solid #e5e7eb; border-radius: 12px; padding: 16px; margin: 10px 0;';
    progressContainer.innerHTML = `
        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 10px;">
            <div style="flex: 1;">
                <div style="font-weight: 600; color: #1f2937; margin-bottom: 4px;">
                    📸 Uploading: ${file.name}
                </div>
                <div style="font-size: 13px; color: #6b7280;">
                    Size: ${(file.size / 1024).toFixed(2)} KB
                </div>
            </div>
        </div>
        <div style="background: #f3f4f6; border-radius: 8px; height: 20px; overflow: hidden;">
            <div id="imageProgressBar" style="background: linear-gradient(90deg, #3b82f6 0%, #2563eb 100%); height: 100%; width: 0%; transition: width 0.3s; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; font-size: 11px;">
                0%
            </div>
        </div>
    `;
    
    editor.appendChild(progressContainer);
    
    const formData = new FormData();
    formData.append('file', file);
    
    try {
        const xhr = new XMLHttpRequest();
        
        xhr.upload.addEventListener('progress', (e) => {
            if (e.lengthComputable) {
                const percentComplete = Math.round((e.loaded / e.total) * 100);
                const progressBar = document.getElementById('imageProgressBar');
                
                if (progressBar) {
                    progressBar.style.width = percentComplete + '%';
                    progressBar.textContent = percentComplete + '%';
                }
            }
        });
        
        xhr.addEventListener('load', () => {
            if (xhr.status === 200) {
                const result = JSON.parse(xhr.responseText);
                
                if (result.success) {
                    progressContainer.remove();
                    
                    const img = `<img src="${result.data.url}" alt="${result.data.file_name}" style="max-width: 100%; height: auto; border-radius: 8px; margin: 10px 0; display: block;" />`;
                    document.execCommand('insertHTML', false, img);
                    showAlert('✅ Image uploaded successfully!', 'success');
                } else {
                    progressContainer.remove();
                    showAlert('❌ Upload failed: ' + result.error, 'error');
                }
            } else {
                progressContainer.remove();
                showAlert('❌ Upload failed with status: ' + xhr.status, 'error');
            }
        });
        
        xhr.addEventListener('error', () => {
            progressContainer.remove();
            showAlert('❌ Network error during upload', 'error');
        });
        
        xhr.open('POST', '/api/upload-media.php');
        xhr.send(formData);
        
    } catch (error) {
        progressContainer.remove();
        console.error('Upload error:', error);
        showAlert('❌ Network error during upload', 'error');
    }
    
    event.target.value = '';
}

// 🔥 COMPLETELY FIXED: HANDLE VIDEO UPLOAD WITH PROGRESS BAR
// 🔥 100% WORKING: HANDLE VIDEO UPLOAD WITH PROGRESS BAR
async function handleVideoUpload(event) {
    const file = event.target.files[0];
    if (!file) return;
    
    if (!file.type.startsWith('video/')) {
        alert('❌ Please select a video file');
        return;
    }
    
    const maxSize = 50 * 1024 * 1024; // 50MB
    if (file.size > maxSize) {
        alert('❌ Video file is too large. Maximum size is 50MB');
        return;
    }
    
    const editor = document.getElementById('contentEditor');
    
    // Create progress container
    const progressContainer = document.createElement('div');
    progressContainer.id = 'videoUploadProgress';
    progressContainer.style.cssText = 'background: white; border: 2px solid #e5e7eb; border-radius: 12px; padding: 20px; margin: 10px 0;';
    progressContainer.innerHTML = `
        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
            <div style="flex: 1;">
                <div style="font-weight: 600; color: #1f2937; margin-bottom: 4px;">
                    📤 Uploading: ${file.name}
                </div>
                <div style="font-size: 14px; color: #6b7280;">
                    Size: ${(file.size / 1024 / 1024).toFixed(2)} MB
                </div>
            </div>
        </div>
        <div style="background: #f3f4f6; border-radius: 8px; height: 24px; overflow: hidden; position: relative;">
            <div id="videoProgressBar" style="background: linear-gradient(90deg, #22c55e 0%, #16a34a 100%); height: 100%; width: 0%; transition: width 0.3s; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; font-size: 12px;">
                0%
            </div>
        </div>
        <div id="videoProgressText" style="margin-top: 8px; font-size: 13px; color: #6b7280; text-align: center;">
            Preparing upload...
        </div>
    `;
    
    editor.appendChild(progressContainer);
    
    const formData = new FormData();
    formData.append('file', file);
    
    const xhr = new XMLHttpRequest();
    
    // Progress tracking
    xhr.upload.addEventListener('progress', (e) => {
        if (e.lengthComputable) {
            const percentComplete = Math.round((e.loaded / e.total) * 100);
            const progressBar = document.getElementById('videoProgressBar');
            const progressText = document.getElementById('videoProgressText');
            
            if (progressBar && progressText) {
                progressBar.style.width = percentComplete + '%';
                progressBar.textContent = percentComplete + '%';
                
                const uploadedMB = (e.loaded / 1024 / 1024).toFixed(2);
                const totalMB = (e.total / 1024 / 1024).toFixed(2);
                progressText.textContent = `Uploading... ${uploadedMB} MB / ${totalMB} MB`;
            }
        }
    });
    
    // Upload complete
    xhr.addEventListener('load', () => {
        if (xhr.status === 200) {
            try {
                const result = JSON.parse(xhr.responseText);
                
                console.log('Upload response:', result); // Debug
                
                if (result.success) {
                    // Remove progress container
                    progressContainer.remove();
                    
                    // Get video URL - handle both absolute and relative paths
                    let videoURL = result.data.url;
                    
                    // If URL doesn't start with http, make it absolute
                    if (!videoURL.startsWith('http')) {
                        const origin = window.location.origin;
                        videoURL = origin + (videoURL.startsWith('/') ? '' : '/') + videoURL;
                    }
                    
                    console.log('Video URL:', videoURL); // Debug
                    
                    // Create video wrapper and element
                    const videoContainer = document.createElement('div');
                    videoContainer.contentEditable = 'false';
                    videoContainer.style.cssText = 'margin: 20px auto; max-width: 100%; background: #f9fafb; border: 2px solid #e5e7eb; border-radius: 12px; padding: 8px;';
                    
                    const videoElement = document.createElement('video');
                    videoElement.controls = true;
                    videoElement.preload = 'metadata';
                    videoElement.style.cssText = 'width: 100%; max-width: 100%; border-radius: 8px; display: block; background: #000;';
                    
                    // Add source
                    const sourceElement = document.createElement('source');
                    sourceElement.src = videoURL;
                    sourceElement.type = file.type;
                    
                    videoElement.appendChild(sourceElement);
                    
                    // Add error handler
                    videoElement.addEventListener('error', (e) => {
                        console.error('Video load error:', e);
                        showAlert('❌ Video failed to load. Check the file path.', 'error');
                    });
                    
                    // Add loaded handler
                    videoElement.addEventListener('loadedmetadata', () => {
                        console.log('Video loaded successfully');
                    });
                    
                    videoContainer.appendChild(videoElement);
                    
                    // Append to editor
                    editor.appendChild(videoContainer);
                    
                    // Add line break after video
                    const br = document.createElement('br');
                    editor.appendChild(br);
                    
                    // Scroll to show video
                    editor.scrollTop = editor.scrollHeight;
                    
                    // Try to load video
                    videoElement.load();
                    
                    showAlert('✅ Video uploaded successfully!', 'success');
                } else {
                    progressContainer.remove();
                    console.error('Upload failed:', result.error);
                    showAlert('❌ Upload failed: ' + result.error, 'error');
                }
            } catch (error) {
                progressContainer.remove();
                console.error('Parse error:', error);
                showAlert('❌ Server response error', 'error');
            }
        } else {
            progressContainer.remove();
            console.error('Upload status:', xhr.status);
            showAlert('❌ Upload failed with status: ' + xhr.status, 'error');
        }
    });
    
    // Error handling
    xhr.addEventListener('error', () => {
        progressContainer.remove();
        console.error('Network error');
        showAlert('❌ Network error during upload', 'error');
    });
    
    // Start upload
    xhr.open('POST', '/api/upload-media.php');
    xhr.send(formData);
    
    event.target.value = '';
}



// 🔥 INSERT IMAGE FROM URL
function insertImageURL() {
    const url = prompt('Enter image URL:', 'https://');
    if (!url || url === 'https://') {
        return;
    }
    
    if (!url.match(/\.(jpeg|jpg|gif|png|webp|svg)$/i) && !url.startsWith('http')) {
        if (!confirm('This might not be a valid image URL. Insert anyway?')) {
            return;
        }
    }
    
    const img = `<img src="${url}" alt="Image" style="max-width: 100%; height: auto; border-radius: 8px; margin: 10px 0; display: block;" />`;
    document.execCommand('insertHTML', false, img);
    showAlert('✅ Image inserted successfully!', 'success');
    document.getElementById('contentEditor').focus();
}

// 🔥 COMPLETELY FIXED: Insert YouTube/Vimeo video
function insertVideo() {
    const url = prompt('Enter YouTube or Vimeo video URL:', 'https://');
    if (!url || url === 'https://') {
        return;
    }
    
    let embedUrl = '';
    
    if (url.includes('youtube.com') || url.includes('youtu.be')) {
        let videoId = '';
        
        if (url.includes('youtu.be/')) {
            videoId = url.split('youtu.be/')[1]?.split('?')[0];
        } else if (url.includes('youtube.com/watch')) {
            videoId = url.split('v=')[1]?.split('&')[0];
        } else if (url.includes('youtube.com/embed/')) {
            videoId = url.split('embed/')[1]?.split('?')[0];
        }
        
        if (videoId) {
            embedUrl = `https://www.youtube.com/embed/${videoId}`;
        }
    } 
    else if (url.includes('vimeo.com')) {
        const videoId = url.split('vimeo.com/')[1]?.split('?')[0].split('/').pop();
        if (videoId) {
            embedUrl = `https://player.vimeo.com/video/${videoId}`;
        }
    }
    
    if (embedUrl) {
        const iframeWrapper = document.createElement('div');
        iframeWrapper.contentEditable = false;
        iframeWrapper.style.cssText = 'position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden; max-width: 100%; margin: 20px 0; border-radius: 12px;';
        
        const iframe = document.createElement('iframe');
        iframe.src = embedUrl;
        iframe.style.cssText = 'position: absolute; top: 0; left: 0; width: 100%; height: 100%; border-radius: 12px; border: none;';
        iframe.setAttribute('frameborder', '0');
        iframe.setAttribute('allow', 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture');
        iframe.setAttribute('allowfullscreen', '');
        
        iframeWrapper.appendChild(iframe);
        
        const selection = window.getSelection();
        const range = selection.getRangeAt(0);
        range.insertNode(iframeWrapper);
        
        range.setStartAfter(iframeWrapper);
        range.setEndAfter(iframeWrapper);
        selection.removeAllRanges();
        selection.addRange(range);
        
        const br = document.createElement('br');
        range.insertNode(br);
        
        showAlert('✅ Video embedded successfully!', 'success');
    } else {
        alert('❌ Invalid video URL. Please use YouTube or Vimeo links.');
    }
    
    document.getElementById('contentEditor').focus();
}

// Modal Functions
function openModal(modalId) {
    document.getElementById(modalId).classList.add('active');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('active');
}

// Alert Function
function showAlert(message, type = 'success') {
    const alertContainer = document.getElementById('alertContainer');
    const alertDiv = document.createElement('div');
    
    const colors = {
        success: 'bg-green-50 border-green-200 text-green-800',
        error: 'bg-red-50 border-red-200 text-red-800',
        info: 'bg-blue-50 border-blue-200 text-blue-800'
    };
    
    alertDiv.className = `${colors[type]} border-2 rounded-lg p-4 mb-4 flex items-center justify-between`;
    alertDiv.innerHTML = `
        <span class="font-semibold">${message}</span>
        <button onclick="this.parentElement.remove()" class="text-gray-600 hover:text-gray-800">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
        </button>
    `;
    
    alertContainer.appendChild(alertDiv);
    
    setTimeout(() => {
        alertDiv.remove();
    }, 5000);
}

// Chapter Functions
function openAddChapterModal() {
    openModal('addChapterModal');
}

document.getElementById('addChapterForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(e.target);
    
    try {
        const response = await fetch(`/api/builder.php?action=add_chapter&course_id=${courseId}`, {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            showAlert('✅ Chapter created successfully!', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showAlert('❌ ' + result.error, 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showAlert('❌ Network error', 'error');
    }
});

function editChapter(chapterId, title) {
    document.getElementById('editChapterId').value = chapterId;
    document.getElementById('editChapterTitle').value = title;
    openModal('editChapterModal');
}

document.getElementById('editChapterForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(e.target);
    
    try {
        const response = await fetch('/api/builder.php?action=edit_chapter', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            showAlert('✅ Chapter updated successfully!', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showAlert('❌ ' + result.error, 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showAlert('❌ Network error', 'error');
    }
});

function deleteChapter(chapterId) {
    if (!confirm('Are you sure you want to delete this chapter? All topics and content will be deleted.')) {
        return;
    }
    
    fetch(`/api/builder.php?action=delete_chapter&chapter_id=${chapterId}`, {
        method: 'POST'
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            showAlert('✅ Chapter deleted successfully!', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showAlert('❌ ' + result.error, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('❌ Network error', 'error');
    });
}

// Topic Functions
function openAddTopicModal(chapterId) {
    document.getElementById('topicChapterId').value = chapterId;
    openModal('addTopicModal');
}

document.getElementById('addTopicForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(e.target);
    
    try {
        const response = await fetch('/api/builder.php?action=add_topic', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            showAlert('✅ Topic created successfully!', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showAlert('❌ ' + result.error, 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showAlert('❌ Network error', 'error');
    }
});

function editTopic(topicId, title, isMandatory) {
    document.getElementById('editTopicId').value = topicId;
    document.getElementById('editTopicTitle').value = title;
    document.getElementById('editTopicMandatory').checked = isMandatory;
    openModal('editTopicModal');
}

document.getElementById('editTopicForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(e.target);
    
    try {
        const response = await fetch('/api/builder.php?action=edit_topic', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            showAlert('✅ Topic updated successfully!', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showAlert('❌ ' + result.error, 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showAlert('❌ Network error', 'error');
    }
});

function deleteTopic(topicId) {
    if (!confirm('Are you sure you want to delete this topic? All content will be deleted.')) {
        return;
    }
    
    fetch(`/api/builder.php?action=delete_topic&topic_id=${topicId}`, {
        method: 'POST'
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            showAlert('✅ Topic deleted successfully!', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showAlert('❌ ' + result.error, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('❌ Network error', 'error');
    });
}

// 🔥 FIXED: Content Editor - Load existing content with proper video/iframe rendering
function openContentEditor(topicId) {
    document.getElementById('contentEditorTopicId').value = topicId;
    const editor = document.getElementById('contentEditor');
    
    editor.innerHTML = '<p style="color: #9ca3af; text-align: center; padding: 40px;">⏳ Loading content...</p>';
    
    openModal('contentEditorModal');
    
    fetch(`/api/builder.php?action=get_content&topic_id=${topicId}`)
        .then(response => {
            console.log('Response status:', response.status);
            return response.json();
        })
        .then(result => {
            console.log('Content loaded:', result);
            
            if (result.success) {
                if (result.data && result.data.content && result.data.content.trim() !== '') {
                    editor.innerHTML = result.data.content;
                    
                    setTimeout(() => {
                        const videos = editor.querySelectorAll('video');
                        videos.forEach(video => {
                            if (!video.parentElement.hasAttribute('contenteditable')) {
                                const wrapper = document.createElement('div');
                                wrapper.contentEditable = false;
                                wrapper.style.cssText = 'margin: 20px 0; max-width: 100%;';
                                video.parentNode.insertBefore(wrapper, video);
                                wrapper.appendChild(video);
                            }
                            video.load();
                        });
                        
                        const iframes = editor.querySelectorAll('iframe');
                        iframes.forEach(iframe => {
                            const parent = iframe.parentElement;
                            if (parent && !parent.hasAttribute('contenteditable')) {
                                parent.contentEditable = false;
                            }
                            
                            const src = iframe.getAttribute('src');
                            if (src) {
                                iframe.setAttribute('src', src);
                            }
                        });
                        
                        const wrappers = editor.querySelectorAll('div[style*="position: relative"]');
                        wrappers.forEach(wrapper => {
                            wrapper.contentEditable = false;
                        });
                    }, 100);
                    
                } else {
                    editor.innerHTML = '';
                }
            } else {
                console.error('Failed to load content:', result.error);
                editor.innerHTML = '';
                showAlert('⚠️ Could not load existing content: ' + result.error, 'info');
            }
        })
        .catch(error => {
            console.error('Error loading content:', error);
            editor.innerHTML = '';
            showAlert('❌ Network error while loading content', 'error');
        });
}

document.getElementById('contentEditorForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const topicId = document.getElementById('contentEditorTopicId').value;
    const content = document.getElementById('contentEditor').innerHTML;
    
    const formData = new FormData();
    formData.append('topic_id', topicId);
    formData.append('content', content);
    
    try {
        const response = await fetch('/api/builder.php?action=save_content', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            showAlert('✅ Content saved successfully!', 'success');
            closeModal('contentEditorModal');
            setTimeout(() => location.reload(), 1000);
        } else {
            showAlert('❌ ' + result.error, 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showAlert('❌ Network error', 'error');
    }
});

// Rich Text Editor Functions
function formatText(command) {
    document.execCommand(command, false, null);
    document.getElementById('contentEditor').focus();
}

function insertHeading(level) {
    document.execCommand('formatBlock', false, level);
    document.getElementById('contentEditor').focus();
}

function insertLink() {
    const url = prompt('Enter URL:', 'https://');
    if (url && url !== 'https://') {
        document.execCommand('createLink', false, url);
    }
    document.getElementById('contentEditor').focus();
}

function insertBlockquote() {
    document.execCommand('formatBlock', false, 'blockquote');
    document.getElementById('contentEditor').focus();
}

function insertCode() {
    const code = prompt('Enter code:');
    if (code) {
        const codeHtml = `<code>${code.replace(/</g, '&lt;').replace(/>/g, '&gt;')}</code>`;
        document.execCommand('insertHTML', false, codeHtml);
    }
    document.getElementById('contentEditor').focus();
}

function insertHorizontalRule() {
    document.execCommand('insertHorizontalRule', false, null);
    document.getElementById('contentEditor').focus();
}

function clearFormatting() {
    document.execCommand('removeFormat', false, null);
    document.getElementById('contentEditor').focus();
}

// Quiz Editor Functions
function openQuizEditor(topicId) {
    document.getElementById('quizTopicId').value = topicId;
    document.getElementById('questionsContainer').innerHTML = '';
    
    fetch(`/api/builder.php?action=get_quiz&topic_id=${topicId}`)
        .then(response => response.json())
        .then(result => {
            if (result.success && result.data) {
                const quiz = result.data;
                document.getElementById('quizTitle').value = quiz.title || '';
                document.getElementById('quizDescription').value = quiz.description || '';
                document.getElementById('quizPassingScore').value = quiz.passing_score || 70;
                document.getElementById('quizTimeLimit').value = quiz.time_limit || 30;
                
                if (quiz.questions && quiz.questions.length > 0) {
                    quiz.questions.forEach((question) => {
                        addQuestion(question);
                    });
                } else {
                    addQuestion();
                }
            } else {
                addQuestion();
            }
        })
        .catch(error => {
            console.error('Error loading quiz:', error);
            addQuestion();
        });
    
    openModal('quizEditorModal');
}

let questionCounter = 0;

function addQuestion(questionData = null) {
    questionCounter++;
    const container = document.getElementById('questionsContainer');
    
    const questionDiv = document.createElement('div');
    questionDiv.className = 'bg-gray-50 border-2 border-gray-200 rounded-lg p-4';
    questionDiv.innerHTML = `
        <div class="flex items-center justify-between mb-4">
            <h4 class="font-bold text-gray-900">Question ${questionCounter}</h4>
            <button type="button" onclick="this.closest('.bg-gray-50').remove(); renumberQuestions();" class="text-red-600 hover:text-red-700">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                </svg>
            </button>
        </div>
        
        <div class="space-y-3">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Question Text *</label>
                <input type="text" name="questions[${questionCounter}][text]" value="${questionData?.question_text || ''}" class="form-input" placeholder="Enter your question here..." required>
            </div>
            
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Options (check correct answer) *</label>
                <div class="space-y-2">
                    ${[1, 2, 3, 4].map(i => `
                        <div class="flex items-center gap-2">
                            <input type="radio" name="questions[${questionCounter}][correct]" value="${i}" ${questionData?.correct_option == i ? 'checked' : ''} required class="w-4 h-4">
                            <input type="text" name="questions[${questionCounter}][option${i}]" value="${questionData ? (questionData[`option${i}`] || '') : ''}" class="form-input flex-1" placeholder="Option ${i}" required>
                        </div>
                    `).join('')}
                </div>
            </div>
            
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Explanation (optional)</label>
                <textarea name="questions[${questionCounter}][explanation]" class="form-textarea" rows="2" placeholder="Explain why this is the correct answer...">${questionData?.explanation || ''}</textarea>
            </div>
        </div>
    `;
    
    container.appendChild(questionDiv);
}

function renumberQuestions() {
    const questions = document.querySelectorAll('#questionsContainer > div');
    questions.forEach((q, index) => {
        const heading = q.querySelector('h4');
        if (heading) {
            heading.textContent = `Question ${index + 1}`;
        }
    });
}

document.getElementById('quizEditorForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(e.target);
    
    const questionInputs = document.querySelectorAll('input[name*="questions"][name*="[text]"]');
    if (questionInputs.length === 0) {
        showAlert('❌ Please add at least one question', 'error');
        return;
    }
    
    try {
        const response = await fetch('/api/builder.php?action=save_quiz', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            showAlert('✅ Quiz saved successfully!', 'success');
            closeModal('quizEditorModal');
            setTimeout(() => location.reload(), 1000);
        } else {
            showAlert('❌ ' + result.error, 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showAlert('❌ Network error', 'error');
    }
});

// Close modal on outside click
document.querySelectorAll('.modal').forEach(modal => {
    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            modal.classList.remove('active');
        }
    });
});
</script>




</body>
</html>