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
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

spl_autoload_register(function ($class) {
    $paths = [
        __DIR__ . '/../../core/' . $class . '.php',
        __DIR__ . '/../../models/' . $class . '.php',
        __DIR__ . '/../../controllers/' . $class . '.php',
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

$currentUser = $auth->user();
$userId = $auth->id();

$courseId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$requestedTopicId = isset($_GET['topic']) ? intval($_GET['topic']) : 0;

if (!$courseId) {
    header('Location: /app/views/learner/my-courses.php');
    exit;
}

// Check enrollment
try {
    $enrollStmt = $db->prepare("SELECT * FROM enrollments WHERE user_id = ? AND course_id = ?");
    $enrollStmt->execute([$userId, $courseId]);
    $enrollment = $enrollStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$enrollment) {
        header('Location: /app/views/learner/my-courses.php?error=not_enrolled');
        exit;
    }
} catch (Exception $e) {
    die("Enrollment check failed: " . $e->getMessage());
}

// Get course details
try {
    $courseStmt = $db->prepare("SELECT * FROM courses WHERE id = ?");
    $courseStmt->execute([$courseId]);
    $course = $courseStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$course) {
        header('Location: /app/views/learner/my-courses.php?error=course_not_found');
        exit;
    }
} catch (Exception $e) {
    die("Course fetch failed: " . $e->getMessage());
}

// Get chapters with topics
$chapters = [];
try {
    $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    
    $stmt = $db->prepare("SELECT * FROM chapters WHERE course_id = ? ORDER BY sort_order ASC, id ASC");
    $stmt->execute([$courseId]);
    $chaptersTemp = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $chapterIndex = 0;
    foreach ($chaptersTemp as $chapterRow) {
        $chapters[$chapterIndex] = $chapterRow;
        
        $stmt = $db->prepare("SELECT * FROM topics WHERE chapter_id = ? ORDER BY sort_order ASC, id ASC");
        $stmt->execute([$chapterRow['id']]);
        $topicsTemp = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $chapters[$chapterIndex]['topics'] = [];
        $topicIndex = 0;
        
        foreach ($topicsTemp as $topicRow) {
            $chapters[$chapterIndex]['topics'][$topicIndex] = $topicRow;
            
            // Check topic progress
            $progressStmt = $db->prepare("SELECT * FROM topic_progress WHERE topic_id = ? AND user_id = ?");
            $progressStmt->execute([$topicRow['id'], $userId]);
            $progress = $progressStmt->fetch(PDO::FETCH_ASSOC);
            
            $chapters[$chapterIndex]['topics'][$topicIndex]['is_completed'] = $progress ? ($progress['is_complete'] ?? $progress['is_completed'] ?? 0) : 0;
            $chapters[$chapterIndex]['topics'][$topicIndex]['completed_at'] = $progress ? $progress['completed_at'] : null;
            
            // Check if topic has quiz
            $quizCheckStmt = $db->prepare("
                SELECT cb.id as content_block_id, q.id as quiz_id, q.title as quiz_title
                FROM content_blocks cb
                INNER JOIN quizzes q ON q.content_block_id = cb.id
                WHERE cb.topic_id = ? AND cb.type = 'quiz'
                LIMIT 1
            ");
            $quizCheckStmt->execute([$topicRow['id']]);
            $quiz = $quizCheckStmt->fetch(PDO::FETCH_ASSOC);
            
            $chapters[$chapterIndex]['topics'][$topicIndex]['has_quiz'] = $quiz ? true : false;
            $chapters[$chapterIndex]['topics'][$topicIndex]['quiz_id'] = $quiz ? $quiz['quiz_id'] : null;
            $chapters[$chapterIndex]['topics'][$topicIndex]['quiz_title'] = $quiz ? $quiz['quiz_title'] : null;
            
            // Check quiz completion
            if ($quiz) {
                $quizAttemptStmt = $db->prepare("
                    SELECT * FROM quiz_attempts 
                    WHERE user_id = ? AND quiz_id = ? AND status = 'passed'
                    ORDER BY completed_at DESC LIMIT 1
                ");
                $quizAttemptStmt->execute([$userId, $quiz['quiz_id']]);
                $quizPassed = $quizAttemptStmt->fetch(PDO::FETCH_ASSOC);
                $chapters[$chapterIndex]['topics'][$topicIndex]['quiz_passed'] = $quizPassed ? true : false;
            } else {
                $chapters[$chapterIndex]['topics'][$topicIndex]['quiz_passed'] = true;
            }
            
            // Get content blocks
            $stmt = $db->prepare("SELECT * FROM content_blocks WHERE topic_id = ? ORDER BY sort_order ASC, id ASC");
            $stmt->execute([$topicRow['id']]);
            $chapters[$chapterIndex]['topics'][$topicIndex]['content_blocks'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $topicIndex++;
        }
        
        $chapterIndex++;
    }
    
    unset($chaptersTemp, $topicsTemp, $chapterRow, $topicRow);
    
} catch (Exception $e) {
    error_log("Chapters fetch error: " . $e->getMessage());
    $chapters = [];
}

// Determine current topic
$currentTopic = null;
$currentChapter = null;

if ($requestedTopicId) {
    foreach ($chapters as $chapter) {
        foreach ($chapter['topics'] as $topic) {
            if ($topic['id'] == $requestedTopicId) {
                $currentTopic = $topic;
                $currentChapter = $chapter;
                break 2;
            }
        }
    }
}

if (!$currentTopic) {
    foreach ($chapters as $chapter) {
        foreach ($chapter['topics'] as $topic) {
            if (empty($topic['is_completed'])) {
                $currentTopic = $topic;
                $currentChapter = $chapter;
                break 2;
            }
        }
    }
}

if (!$currentTopic) {
    foreach ($chapters as $chapter) {
        if (!empty($chapter['topics'])) {
            $currentTopic = $chapter['topics'][0];
            $currentChapter = $chapter;
            break;
        }
    }
}

$contentBlocks = [];
if ($currentTopic && !empty($currentTopic['content_blocks'])) {
    $contentBlocks = $currentTopic['content_blocks'];
}

// Build a flat ordered list of topics for prev/next navigation and overall progress
$allTopicsFlat = [];
$totalTopics = 0;
$completedTopics = 0;
foreach ($chapters as $chapter) {
    foreach ($chapter['topics'] as $topic) {
        $allTopicsFlat[] = [
            'id'           => (int)$topic['id'],
            'title'        => $topic['title'],
            'chapter_id'   => $chapter['id'],
            'is_completed' => !empty($topic['is_completed']),
        ];
        $totalTopics++;
        if (!empty($topic['is_completed'])) {
            $completedTopics++;
        }
    }
}
$overallPct = $totalTopics > 0 ? (int) round(($completedTopics / $totalTopics) * 100) : 0;

// Find prev / next topics relative to current
$prevTopic = null;
$nextTopic = null;
if ($currentTopic) {
    foreach ($allTopicsFlat as $idx => $t) {
        if ($t['id'] == (int)$currentTopic['id']) {
            if ($idx > 0) $prevTopic = $allTopicsFlat[$idx - 1];
            if ($idx < count($allTopicsFlat) - 1) $nextTopic = $allTopicsFlat[$idx + 1];
            break;
        }
    }
}

function decodeText($text) {
    return html_entity_decode($text ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Render content-block HTML safely so code samples (HTML/CSS/JS/PHP etc.)
 * render as visible code instead of being eaten by the browser.
 *
 * Handles 4 cases:
 *  a) Properly encoded <pre><code>&lt;!DOCTYPE&gt;...</code></pre>     → unchanged
 *  b) Raw HTML inside <pre><code><!DOCTYPE>...</code></pre>            → re-encoded
 *  c) Raw <!DOCTYPE>...</html> directly in content (no wrapper)        → wrap in <pre><code> + encode
 *  d) Loose multi-line <code>...</code> (TinyMCE emits sometimes)      → wrap in <pre>
 */
function renderContentHtml($html) {
    if ($html === null || $html === '') return '';

    // STEP 1: Protect existing <pre>...</pre> blocks (handles any attrs).
    // Re-encode raw doc-level tags inside their <code> child (or inside <pre> directly).
    $protected = [];
    $html = preg_replace_callback(
        '#(<pre\b[^>]*>)([\s\S]*?)(</pre>)#i',
        function ($m) use (&$protected) {
            $openTag  = $m[1];
            $inner    = $m[2];
            $closeTag = $m[3];

            if (stripos($inner, '<code') !== false) {
                $inner = preg_replace_callback(
                    '#(<code\b[^>]*>)([\s\S]*?)(</code>)#i',
                    function ($m2) {
                        $codeContent = $m2[2];
                        if (preg_match('#<(!doctype|/?html|/?head|/?body|/?title|/?script|/?style|/?link|/?meta)\b#i', $codeContent)) {
                            $codeContent = htmlspecialchars($codeContent, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                        }
                        return $m2[1] . $codeContent . $m2[3];
                    },
                    $inner
                );
            } else {
                if (preg_match('#<(!doctype|/?html|/?head|/?body|/?title|/?script|/?style|/?link|/?meta)\b#i', $inner)) {
                    $inner = htmlspecialchars($inner, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                }
            }

            $key = '___PRE_BLOCK_' . count($protected) . '___';
            $protected[$key] = $openTag . $inner . $closeTag;
            return $key;
        },
        $html
    );

    // STEP 2: Loose <code> (not inside <pre>) — wrap multi-line in <pre>, encode raw tags
    $html = preg_replace_callback(
        '#(<code\b[^>]*>)([\s\S]*?)(</code>)#i',
        function ($m) {
            $codeContent = $m[2];

            if (preg_match('#<(!doctype|/?html|/?head|/?body|/?title|/?script|/?style|/?link|/?meta)\b#i', $codeContent)) {
                $codeContent = htmlspecialchars($codeContent, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }

            if (strpos($codeContent, "\n") !== false || strpos($codeContent, '&lt;') !== false || strpos($codeContent, '<') !== false) {
                return '<pre><code>' . $codeContent . '</code></pre>';
            }
            return $m[1] . $codeContent . $m[3];
        },
        $html
    );

    // STEP 3a: Auto-detect full <!DOCTYPE ...>...</html> blocks pasted raw into content
    // (admin paste-bug). Wrap them in a styled <pre><code> with ALL contents encoded.
    $html = preg_replace_callback(
        '#(<!DOCTYPE\b[^>]*>[\s\S]*?</html\s*>)#i',
        function ($m) {
            $encoded = htmlspecialchars($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            return '<pre><code>' . $encoded . '</code></pre>';
        },
        $html
    );

    // STEP 3b: Encode any remaining bare doc-level tags (orphan <html>, <body>, etc.)
    $rawTagPatterns = [
        '#<!DOCTYPE\b[^>]*>#i',
        '#</?html\b[^>]*>#i',
        '#</?head\b[^>]*>#i',
        '#</?body\b[^>]*>#i',
        '#<title\b[^>]*>[\s\S]*?</title>#i',
        '#<script\b[^>]*>[\s\S]*?</script>#i',
        '#<style\b[^>]*>[\s\S]*?</style>#i',
        '#<meta\b[^>]*/?>#i',
        '#<link\b[^>]*/?>#i',
    ];
    foreach ($rawTagPatterns as $pattern) {
        $html = preg_replace_callback($pattern, function ($m) {
            return htmlspecialchars($m[0], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }, $html);
    }

    // STEP 4: Restore protected <pre> blocks
    foreach ($protected as $key => $value) {
        $html = str_replace($key, $value, $html);
    }

    return $html;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <title><?php echo decodeText($course['title']); ?> - Course Player</title>
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
        }
        h1, h2, h3, h4, h5, h6 {
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
        }
        .prose {
            max-width: none;
            color: #374151;
            font-size: 16px;
            line-height: 1.75;
        }
        .prose h1, .prose h2, .prose h3, .prose h4, .prose h5, .prose h6 {
            margin-top: 1.5em;
            margin-bottom: 0.75em;
            color: #111827;
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
            line-height: 1.3;
        }
        .prose h1 { font-size: 2em; }
        .prose h2 { font-size: 1.6em; border-bottom: 2px solid #e5e7eb; padding-bottom: 0.3em; }
        .prose h3 { font-size: 1.3em; }
        .prose h4 { font-size: 1.1em; }
        .prose p {
            margin-bottom: 1em;
            line-height: 1.75;
        }
        .prose a {
            color: #16a34a;
            text-decoration: underline;
            font-weight: 500;
        }
        .prose a:hover { color: #15803d; }
        .prose strong { color: #111827; font-weight: 700; }
        .prose em { font-style: italic; }
        .prose ul, .prose ol {
            margin-left: 1.5em;
            margin-bottom: 1em;
            padding-left: 0.5em;
        }
        .prose ul { list-style-type: disc; }
        .prose ol { list-style-type: decimal; }
        .prose li {
            margin-bottom: 0.5em;
            line-height: 1.7;
        }
        /* Inline code */
        .prose code {
            background: #f3f4f6;
            color: #db2777;
            padding: 2px 6px;
            border-radius: 4px;
            font-family: 'Menlo', 'Monaco', 'Courier New', monospace;
            font-size: 0.9em;
            font-weight: 500;
            border: 1px solid #e5e7eb;
            white-space: pre-wrap;
            word-break: break-word;
        }
        /* Block code (inside <pre>) */
        .prose pre {
            background: #1e293b;
            color: #f8fafc;
            padding: 18px 22px;
            border-radius: 12px;
            overflow-x: auto;
            margin: 1.25em 0;
            font-family: 'Menlo', 'Monaco', 'Courier New', monospace;
            font-size: 14px;
            line-height: 1.6;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            border: 1px solid #334155;
        }
        .prose pre code {
            background: transparent;
            color: inherit;
            padding: 0;
            border: 0;
            border-radius: 0;
            font-size: inherit;
            font-weight: 400;
            white-space: pre;
            word-break: normal;
        }
        /* Standalone <code> outside pre, multi-line */
        .prose > code, .content-block > code {
            display: block;
            background: #1e293b;
            color: #f8fafc;
            padding: 18px 22px;
            border-radius: 12px;
            overflow-x: auto;
            margin: 1.25em 0;
            white-space: pre;
            font-family: 'Menlo', 'Monaco', 'Courier New', monospace;
            font-size: 14px;
            line-height: 1.6;
            border: 1px solid #334155;
        }
        .prose blockquote {
            border-left: 4px solid #16a34a;
            padding: 8px 16px;
            margin: 1em 0;
            color: #4b5563;
            background: #f9fafb;
            font-style: italic;
            border-radius: 0 8px 8px 0;
        }
        .prose img {
            max-width: 100%;
            height: auto;
            border-radius: 8px;
            margin: 1em 0;
            display: block;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .prose video, .prose iframe {
            max-width: 100%;
            border-radius: 12px;
            margin: 1em 0;
        }
        .prose iframe { min-height: 400px; }
        .prose table {
            border-collapse: collapse;
            width: 100%;
            margin: 1em 0;
            font-size: 0.95em;
        }
        .prose table th, .prose table td {
            border: 1px solid #e5e7eb;
            padding: 8px 12px;
            text-align: left;
        }
        .prose table th {
            background: #f3f4f6;
            font-weight: 700;
        }
        .prose hr {
            border: 0;
            border-top: 1px solid #e5e7eb;
            margin: 2em 0;
        }
        @media (max-width: 640px) {
            .prose { font-size: 15px; }
            .prose h1 { font-size: 1.6em; }
            .prose h2 { font-size: 1.35em; }
            .prose h3 { font-size: 1.15em; }
            .prose pre, .prose > code { font-size: 13px; padding: 14px; }
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        .animate-spin {
            animation: spin 1s linear infinite;
        }
        
        /* Mobile Sidebar */
        .mobile-sidebar {
            position: fixed;
            top: 0;
            left: -100%;
            width: 320px;
            max-width: 85vw;
            height: 100vh;
            background: white;
            box-shadow: 4px 0 20px rgba(0,0,0,0.15);
            z-index: 200;
            transition: left 0.35s cubic-bezier(0.4, 0, 0.2, 1);
            overflow-y: auto;
        }

        .mobile-sidebar.active {
            left: 0;
        }

        /* Overlay */
        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.6);
            z-index: 150;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.35s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .sidebar-overlay.active {
            opacity: 1;
            pointer-events: all;
        }
        
        /* Mobile Top Bar */
        .mobile-topbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: white;
            border-bottom: 1px solid #e5e7eb;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            z-index: 100;
            padding: 12px 16px;
        }
        
        /* Content spacing for mobile */
        .mobile-content {
            padding-top: 60px;
        }
        
        /* Desktop sidebar */
        .desktop-sidebar {
            display: none;
        }
        
        @media (min-width: 769px) {
            .mobile-topbar {
                display: none;
            }
            
            .mobile-content {
                padding-top: 0;
            }
            
            .desktop-sidebar {
                display: block;
            }
        }
        
        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-30px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }
    </style>
</head>
<body class="bg-gray-50">

<!-- Mobile Top Bar -->
<div class="mobile-topbar md:hidden">
    <div class="flex items-center justify-between">
        <button onclick="toggleSidebar()" class="p-2 hover:bg-gray-100 rounded-lg active:bg-gray-200 transition-colors">
            <svg class="w-6 h-6 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
            </svg>
        </button>
        <div class="flex-1 mx-3 min-w-0">
            <h1 class="text-sm font-bold text-gray-900 truncate"><?php echo decodeText($course['title']); ?></h1>
        </div>
        <a href="/app/views/learner/my-courses.php" class="p-2 hover:bg-gray-100 rounded-lg active:bg-gray-200 transition-colors">
            <svg class="w-6 h-6 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
        </a>
    </div>
</div>

<!-- Sidebar Overlay -->
<div class="sidebar-overlay md:hidden" id="sidebarOverlay" onclick="toggleSidebar()"></div>

<div class="flex h-screen overflow-hidden">
    <!-- Mobile Sidebar -->
    <div class="mobile-sidebar md:hidden" id="mobileSidebar">
        <div class="p-4 border-b border-gray-200 sticky top-0 bg-white z-10">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-lg font-bold text-gray-900">Course Content</h2>
                <button onclick="toggleSidebar()" class="p-2 hover:bg-gray-100 rounded-lg">
                    <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            <a href="/app/views/learner/my-courses.php" class="text-sm text-gray-600 hover:text-primary-600 inline-flex items-center gap-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Back to My Courses
            </a>
        </div>
        
        <!-- Certificate Section -->
        <div id="certificateSidebarSectionMobile" class="p-3 border-b border-gray-200"></div>
        
        <!-- Chapters List -->
        <div class="p-3">
            <?php if (empty($chapters)): ?>
                <div class="text-center py-8 text-gray-500">
                    <svg class="w-12 h-12 text-gray-400 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <p class="text-sm">No content available</p>
                </div>
            <?php else: ?>
                <div class="space-y-2">
                    <?php foreach ($chapters as $chapterIndex => $chapter): ?>
                        <div class="border border-gray-200 rounded-lg overflow-hidden">
                            <div class="bg-gray-50 px-3 py-2 border-b border-gray-200">
                                <div class="flex items-center gap-2">
                                    <span class="flex items-center justify-center w-5 h-5 rounded-full bg-primary-600 text-white text-xs font-bold flex-shrink-0">
                                        <?php echo $chapterIndex + 1; ?>
                                    </span>
                                    <span class="font-semibold text-gray-900 text-sm"><?php echo decodeText($chapter['title']); ?></span>
                                </div>
                            </div>
                            
                            <?php if (!empty($chapter['topics'])): ?>
                                <div class="divide-y divide-gray-100">
                                    <?php foreach ($chapter['topics'] as $topicIndex => $topic): ?>
                                        <a href="?id=<?php echo $courseId; ?>&topic=<?php echo $topic['id']; ?>" 
                                           onclick="toggleSidebar()"
                                           class="block px-3 py-2 hover:bg-gray-50 transition-colors <?php echo ($currentTopic && $currentTopic['id'] == $topic['id']) ? 'bg-primary-50 border-l-4 border-primary-600' : ''; ?>">
                                            <div class="flex items-start gap-2">
                                                <div class="flex-shrink-0 mt-0.5">
                                                    <?php if (!empty($topic['is_completed']) && (!$topic['has_quiz'] || $topic['quiz_passed'])): ?>
                                                        <svg class="w-4 h-4 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                                        </svg>
                                                    <?php else: ?>
                                                        <div class="w-4 h-4 rounded-full border-2 border-gray-300"></div>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <div class="flex-1 min-w-0">
                                                    <div class="text-xs text-gray-500 mb-0.5">Topic <?php echo $topicIndex + 1; ?></div>
                                                    <div class="font-medium text-gray-900 text-sm <?php echo ($currentTopic && $currentTopic['id'] == $topic['id']) ? 'text-primary-700' : ''; ?>">
                                                        <?php echo decodeText($topic['title']); ?>
                                                    </div>
                                                    <div class="flex flex-wrap gap-1 mt-1">
                                                        <?php if (!empty($topic['is_mandatory'])): ?>
                                                            <span class="inline-block text-xs bg-orange-100 text-orange-700 px-1.5 py-0.5 rounded-full">Required</span>
                                                        <?php endif; ?>
                                                        <?php if ($topic['has_quiz']): ?>
                                                            <span class="inline-block text-xs bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded-full">📝 Quiz</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Desktop Sidebar -->
    <div class="desktop-sidebar w-80 bg-white border-r border-gray-200 overflow-y-auto flex-shrink-0">
        <div class="p-6 border-b border-gray-200 sticky top-0 bg-white z-10">
            <a href="/app/views/learner/my-courses.php" class="text-sm text-gray-600 hover:text-primary-600 mb-3 inline-flex items-center gap-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Back to My Courses
            </a>
            <h2 class="text-xl font-bold text-gray-900 mt-2"><?php echo decodeText($course['title']); ?></h2>
        </div>
        
        <!-- Certificate Section -->
        <div id="certificateSidebarSection" class="p-4 border-b border-gray-200"></div>
        
        <div class="p-4">
            <?php if (empty($chapters)): ?>
                <div class="text-center py-8 text-gray-500">
                    <svg class="w-12 h-12 text-gray-400 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <p class="text-sm">No content available</p>
                </div>
            <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($chapters as $chapterIndex => $chapter): ?>
                        <div class="border border-gray-200 rounded-lg overflow-hidden">
                            <div class="bg-gray-50 px-4 py-3 border-b border-gray-200">
                                <div class="flex items-center gap-2">
                                    <span class="flex items-center justify-center w-6 h-6 rounded-full bg-primary-600 text-white text-xs font-bold">
                                        <?php echo $chapterIndex + 1; ?>
                                    </span>
                                    <span class="font-semibold text-gray-900"><?php echo decodeText($chapter['title']); ?></span>
                                </div>
                            </div>
                            
                            <?php if (!empty($chapter['topics'])): ?>
                                <div class="divide-y divide-gray-100">
                                    <?php foreach ($chapter['topics'] as $topicIndex => $topic): ?>
                                        <a href="?id=<?php echo $courseId; ?>&topic=<?php echo $topic['id']; ?>" 
                                           class="block px-4 py-3 hover:bg-gray-50 transition-colors <?php echo ($currentTopic && $currentTopic['id'] == $topic['id']) ? 'bg-primary-50 border-l-4 border-primary-600' : ''; ?>">
                                            <div class="flex items-start gap-3">
                                                <div class="flex-shrink-0 mt-0.5">
                                                    <?php if (!empty($topic['is_completed']) && (!$topic['has_quiz'] || $topic['quiz_passed'])): ?>
                                                        <svg class="w-5 h-5 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                                        </svg>
                                                    <?php else: ?>
                                                        <div class="w-5 h-5 rounded-full border-2 border-gray-300"></div>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <div class="flex-1 min-w-0">
                                                    <div class="text-xs text-gray-500 mb-1">Topic <?php echo $topicIndex + 1; ?></div>
                                                    <div class="font-medium text-gray-900 <?php echo ($currentTopic && $currentTopic['id'] == $topic['id']) ? 'text-primary-700' : ''; ?>">
                                                        <?php echo decodeText($topic['title']); ?>
                                                    </div>
                                                    <?php if (!empty($topic['is_mandatory'])): ?>
                                                        <span class="inline-block mt-1 text-xs bg-orange-100 text-orange-700 px-2 py-0.5 rounded-full">Required</span>
                                                    <?php endif; ?>
                                                    <?php if ($topic['has_quiz']): ?>
                                                        <span class="inline-block mt-1 text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full">📝 Quiz</span>
                                                    <?php endif; ?>
                                                    <?php if (!empty($topic['content_blocks'])): ?>
                                                        <div class="text-xs text-gray-500 mt-1"><?php echo count($topic['content_blocks']); ?> content blocks</div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="flex-1 overflow-y-auto bg-white mobile-content">
        <?php if ($currentTopic): ?>
            <!-- Sticky Progress Header -->
            <div class="sticky top-0 z-20 bg-white/95 backdrop-blur border-b border-gray-200 px-4 sm:px-6 md:px-8 py-3">
                <div class="max-w-4xl mx-auto flex items-center gap-3 sm:gap-4">
                    <div class="hidden sm:flex w-10 h-10 bg-primary-100 rounded-full items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 text-primary-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                        </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between mb-1">
                            <p class="text-xs sm:text-sm font-semibold text-gray-700"><?php echo $completedTopics; ?> of <?php echo $totalTopics; ?> topics</p>
                            <p class="text-xs sm:text-sm font-bold text-primary-700"><?php echo $overallPct; ?>%</p>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2 overflow-hidden">
                            <div class="bg-gradient-to-r from-primary-500 to-primary-700 h-2 rounded-full transition-all duration-500" style="width: <?php echo $overallPct; ?>%"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="max-w-4xl mx-auto p-4 sm:p-6 md:p-8">
                <div class="mb-6 sm:mb-8 pb-4 sm:pb-6 border-b border-gray-200">
                    <div class="flex items-center gap-2 text-xs sm:text-sm text-primary-600 font-medium mb-2 sm:mb-3">
                        <svg class="w-3 h-3 sm:w-4 sm:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
                        </svg>
                        <?php echo decodeText($currentChapter['title']); ?>
                    </div>
                    <h1 class="text-2xl sm:text-3xl md:text-4xl font-bold text-gray-900 mb-2 sm:mb-4">
                        <?php echo decodeText($currentTopic['title']); ?>
                    </h1>
                </div>
                
                <?php if (empty($contentBlocks)): ?>
                    <div class="bg-yellow-50 border-2 border-yellow-200 rounded-xl p-8 sm:p-12 text-center">
                        <svg class="w-12 h-12 sm:w-16 sm:h-16 text-yellow-500 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        <h3 class="text-lg sm:text-xl font-bold text-yellow-900 mb-2">No Content Available</h3>
                        <p class="text-sm sm:text-base text-yellow-800">This topic doesn't have any content yet.</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-6 sm:space-y-8">
                        <?php foreach ($contentBlocks as $block): ?>
                            <?php if ($block['type'] === 'text'): ?>
                                <div class="content-block">
                                    <div class="prose prose-sm sm:prose-base lg:prose-lg max-w-none">
                                        <?php echo renderContentHtml($block['content']); ?>
                                    </div>
                                </div>
                                
                            <?php elseif ($block['type'] === 'video'): ?>
                                <div class="content-block">
                                    <div class="rounded-xl overflow-hidden shadow-lg border border-gray-200">
                                        <div class="aspect-video bg-gray-900">
                                            <?php
                                            $videoUrl = $block['content'];
                                            if (strpos($videoUrl, 'youtube.com/watch') !== false) {
                                                $videoUrl = str_replace('watch?v=', 'embed/', $videoUrl);
                                            } elseif (strpos($videoUrl, 'youtu.be/') !== false) {
                                                $videoUrl = str_replace('youtu.be/', 'youtube.com/embed/', $videoUrl);
                                            }
                                            ?>
                                            <iframe src="<?php echo htmlspecialchars($videoUrl); ?>" 
                                                    class="w-full h-full" 
                                                    frameborder="0" 
                                                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                                    allowfullscreen>
                                            </iframe>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Mark as Complete -->
                <div class="mt-8 sm:mt-12 pt-6 sm:pt-8 border-t-2 border-gray-200">
                    <?php if (empty($currentTopic['is_completed'])): ?>
                        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between bg-gray-50 rounded-xl p-4 sm:p-6 gap-4">
                            <div class="flex-1">
                                <h3 class="font-bold text-gray-900 text-base sm:text-lg mb-1">Completed this topic?</h3>
                                <p class="text-gray-600 text-sm">Mark it as complete to track your progress</p>
                            </div>
                            <button onclick="handleMarkComplete(<?php echo $currentTopic['id']; ?>, <?php echo $courseId; ?>, <?php echo $currentTopic['has_quiz'] ? 'true' : 'false'; ?>, <?php echo $currentTopic['quiz_id'] ?? 'null'; ?>)" 
                                    id="completeBtn"
                                    class="w-full sm:w-auto bg-primary-600 hover:bg-primary-700 active:bg-primary-800 text-white px-6 sm:px-8 py-3 sm:py-4 rounded-xl font-bold text-base sm:text-lg transition-all shadow-lg flex items-center justify-center gap-2">
                                <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                Mark as Complete
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="bg-green-50 border-2 border-green-200 rounded-xl p-4 sm:p-6 flex items-center gap-3">
                            <svg class="w-6 h-6 sm:w-8 sm:h-8 text-green-600 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                            <div>
                                <h3 class="font-bold text-green-900 text-base sm:text-lg">Completed!</h3>
                                <p class="text-green-700 text-sm">You completed this topic</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Previous / Next Navigation -->
                <div class="mt-6 sm:mt-8 grid grid-cols-2 gap-3 sm:gap-4">
                    <?php if ($prevTopic): ?>
                        <a href="?id=<?php echo $courseId; ?>&topic=<?php echo $prevTopic['id']; ?>"
                           class="group flex items-center gap-2 sm:gap-3 px-3 sm:px-4 py-3 sm:py-4 bg-gray-50 hover:bg-gray-100 active:bg-gray-200 border border-gray-200 rounded-xl transition-all">
                            <svg class="w-5 h-5 text-gray-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                            </svg>
                            <div class="min-w-0 text-left">
                                <p class="text-xs text-gray-500 uppercase tracking-wider font-semibold">Previous</p>
                                <p class="text-sm sm:text-base font-semibold text-gray-900 truncate"><?php echo htmlspecialchars(decodeText($prevTopic['title'])); ?></p>
                            </div>
                        </a>
                    <?php else: ?>
                        <div></div>
                    <?php endif; ?>

                    <?php if ($nextTopic): ?>
                        <a href="?id=<?php echo $courseId; ?>&topic=<?php echo $nextTopic['id']; ?>"
                           class="group flex items-center gap-2 sm:gap-3 px-3 sm:px-4 py-3 sm:py-4 bg-primary-50 hover:bg-primary-100 active:bg-primary-200 border border-primary-200 rounded-xl transition-all justify-end text-right">
                            <div class="min-w-0">
                                <p class="text-xs text-primary-700 uppercase tracking-wider font-semibold">Next</p>
                                <p class="text-sm sm:text-base font-semibold text-gray-900 truncate"><?php echo htmlspecialchars(decodeText($nextTopic['title'])); ?></p>
                            </div>
                            <svg class="w-5 h-5 text-primary-700 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="flex items-center justify-center h-full p-8">
                <div class="text-center max-w-md">
                    <svg class="w-20 h-20 sm:w-24 sm:h-24 text-gray-400 mx-auto mb-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                    </svg>
                    <h3 class="text-xl sm:text-2xl font-bold text-gray-900 mb-3">No Topics Available</h3>
                    <p class="text-sm sm:text-base text-gray-600 mb-6">This course doesn't have any topics yet.</p>
                    <a href="/app/views/learner/my-courses.php" class="inline-block bg-primary-600 hover:bg-primary-700 text-white px-6 py-3 rounded-lg font-semibold text-sm sm:text-base">
                        Back to My Courses
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Quiz Prompt Modal -->
<div id="quizPromptModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.7); backdrop-filter: blur(8px); z-index: 9999; align-items: center; justify-content: center; padding: 20px;">
    <div style="background: white; border-radius: 24px; max-width: 550px; width: 100%; padding: 32px; box-shadow: 0 25px 70px rgba(0, 0, 0, 0.4); animation: modalSlideIn 0.3s ease-out;">
        <div style="text-center; margin-bottom: 28px;">
            <div style="width: 80px; height: 80px; background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%); border-radius: 50%; display: flex; align-items: center; justify-center; margin: 0 auto 20px; box-shadow: 0 8px 24px rgba(59, 130, 246, 0.3);">
                <svg style="width: 40px; height: 40px; color: white;" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/>
                    <path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z" clip-rule="evenodd"/>
                </svg>
            </div>
            <h2 style="font-family: 'Poppins', sans-serif; font-size: 24px; font-weight: 800; color: #111827; margin-bottom: 12px; line-height: 1.3;">📝 Quiz Available!</h2>
            <p style="font-size: 15px; color: #6b7280; line-height: 1.6; font-weight: 500;">
                This topic has a quiz. Take it now to test your knowledge, or skip to mark complete.
            </p>
        </div>
        
        <div style="display: flex; flex-direction: column; gap: 12px;">
            <button onclick="takeQuizNow()" 
                    style="width: 100%; background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%); color: white; padding: 16px 24px; border-radius: 14px; font-weight: 700; font-size: 16px; border: none; cursor: pointer; box-shadow: 0 6px 20px rgba(59, 130, 246, 0.35); transition: all 0.3s ease; font-family: 'Poppins', sans-serif;"
                    onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 8px 24px rgba(59, 130, 246, 0.45)';"
                    onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 6px 20px rgba(59, 130, 246, 0.35)';">
                ✅ Take Quiz Now
            </button>
            
            <button onclick="skipQuiz()" 
                    style="width: 100%; background: #f3f4f6; color: #374151; padding: 16px 24px; border-radius: 14px; font-weight: 700; font-size: 16px; border: none; cursor: pointer; transition: all 0.3s ease; font-family: 'Poppins', sans-serif;"
                    onmouseover="this.style.background='#e5e7eb';"
                    onmouseout="this.style.background='#f3f4f6';">
                ⏭️ Skip Quiz (Mark Complete)
            </button>
            
            <button onclick="closeQuizModal()" 
                    style="width: 100%; background: transparent; color: #9ca3af; padding: 12px; border: none; cursor: pointer; font-size: 14px; text-decoration: underline; font-weight: 600; transition: color 0.2s;"
                    onmouseover="this.style.color='#6b7280';"
                    onmouseout="this.style.color='#9ca3af';">
                Cancel
            </button>
        </div>
    </div>
</div>

<script>
const courseId = <?php echo $courseId; ?>;
let currentQuizId = null;
let currentTopicIdForCompletion = null;

function toggleSidebar() {
    const sidebar = document.getElementById('mobileSidebar');
    const overlay = document.getElementById('sidebarOverlay');
    
    sidebar.classList.toggle('active');
    overlay.classList.toggle('active');
    
    if (sidebar.classList.contains('active')) {
        document.body.style.overflow = 'hidden';
    } else {
        document.body.style.overflow = '';
    }
}

function handleMarkComplete(topicId, courseId, hasQuiz, quizId) {
    console.log('handleMarkComplete called:', {topicId, courseId, hasQuiz, quizId});
    
    if (hasQuiz && quizId) {
        currentQuizId = quizId;
        currentTopicIdForCompletion = topicId;
        const modal = document.getElementById('quizPromptModal');
        if (modal) {
            modal.style.display = 'flex';
        }
    } else {
        markAsComplete(topicId, courseId);
    }
}

function takeQuizNow() {
    console.log('Taking quiz:', currentQuizId);
    if (currentQuizId) {
        window.location.href = `/app/views/learner/quiz-take.php?id=${currentQuizId}`;
    }
}

// ✅ FIXED: Skip Quiz
function skipQuiz() {
    console.log('Skip quiz clicked, topic ID:', currentTopicIdForCompletion);
    closeQuizModal();
    
    if (currentTopicIdForCompletion) {
        markAsComplete(currentTopicIdForCompletion, courseId);
    } else {
        console.error('No topic ID to complete!');
    }
}

function closeQuizModal() {
    const modal = document.getElementById('quizPromptModal');
    if (modal) {
        modal.style.display = 'none';
    }
    // Don't reset these yet - we need them for skipQuiz
    // currentQuizId = null;
    // currentTopicIdForCompletion = null;
}

function markAsComplete(topicId, courseId) {
    console.log('Marking topic complete:', topicId);
    
    const btn = document.getElementById('completeBtn');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = `
            <svg class="animate-spin w-5 h-5 inline-block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span class="ml-2">Marking...</span>
        `;
    }
    
    fetch('/api/mark-topic-complete.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        },
        body: JSON.stringify({ topic_id: topicId })
    })
    .then(response => {
        console.log('Response status:', response.status);
        return response.json();
    })
    .then(data => {
        console.log('Mark complete response:', data);
        
        if (data.success) {
            if (btn) {
                btn.innerHTML = `
                    <svg class="w-6 h-6 inline-block" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    <span class="ml-2">Completed!</span>
                `;
                btn.classList.remove('bg-primary-600', 'hover:bg-primary-700');
                btn.classList.add('bg-green-600');
            }
            
            // Check certificate first
            checkCertificateEligibility();
            
            // Navigate to next topic after 1 second
            setTimeout(() => {
                console.log('Navigating to next topic...');
                findAndNavigateToNextTopic(topicId);
            }, 1000);
        } else {
            throw new Error(data.message || 'Failed to mark as complete');
        }
    })
    .catch(error => {
        console.error('Error marking complete:', error);
        alert('Error: ' + error.message);
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = `
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                <span class="ml-2">Mark as Complete</span>
            `;
        }
    });
}

// ✅ COMPLETELY REWRITTEN NEXT TOPIC FINDER
function findAndNavigateToNextTopic(currentTopicId) {
    console.log('=== FINDING NEXT TOPIC ===');
    console.log('Current Topic ID:', currentTopicId);
    
    // Get ALL topic links from both desktop and mobile sidebar
    const allLinks = document.querySelectorAll('a[href*="topic="]');
    console.log('Total links found:', allLinks.length);
    
    // Build ordered list of topics
    const topicsList = [];
    allLinks.forEach(link => {
        const href = link.getAttribute('href');
        const topicMatch = href.match(/topic=(\d+)/);
        
        if (topicMatch) {
            const topicId = parseInt(topicMatch[1]);
            
            // Avoid duplicates (mobile + desktop sidebar)
            if (!topicsList.find(t => t.id === topicId)) {
                topicsList.push({
                    id: topicId,
                    url: href,
                    link: link
                });
            }
        }
    });
    
    console.log('Unique topics found:', topicsList.length);
    console.log('Topic IDs:', topicsList.map(t => t.id));
    
    // Find current topic's position
    let currentPosition = -1;
    for (let i = 0; i < topicsList.length; i++) {
        if (topicsList[i].id === currentTopicId) {
            currentPosition = i;
            break;
        }
    }
    
    console.log('Current position in list:', currentPosition);
    
    if (currentPosition === -1) {
        console.error('❌ Current topic not found in list!');
        console.log('Looking for ID:', currentTopicId);
        console.log('Available IDs:', topicsList.map(t => t.id));
        return;
    }
    
    // Get next topic
    const nextPosition = currentPosition + 1;
    
    if (nextPosition < topicsList.length) {
        const nextTopic = topicsList[nextPosition];
        console.log('✅ Next topic found!');
        console.log('Next Topic ID:', nextTopic.id);
        console.log('Next URL:', nextTopic.url);
        console.log('Redirecting in 0.5 seconds...');
        
        setTimeout(() => {
            window.location.href = nextTopic.url;
        }, 500);
    } else {
        console.log('🎉 Last topic completed!');
        alert('🎉 Congratulations! You completed all topics in this course!');
        
        // Reload to show certificate option
        setTimeout(() => {
            window.location.reload();
        }, 1500);
    }
}

async function checkCertificateEligibility() {
    try {
        const response = await fetch('/api/certificates.php?action=auto-check', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({course_id: courseId})
        });
        
        const result = await response.json();
        
        if (result.success && result.is_complete) {
            const certHTML = `
                <div class="bg-gradient-to-br from-green-50 to-emerald-50 border-2 border-green-300 rounded-lg p-4">
                    <div class="flex items-center gap-2 mb-2">
                        <svg class="w-5 h-5 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.783.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                        </svg>
                        <h4 class="font-bold text-green-800 text-sm">🎉 Course Complete!</h4>
                    </div>
                    <p class="text-xs text-green-700 mb-3">${result.progress.mandatory_completed}/${result.progress.mandatory_total} mandatory topics</p>
                    ${result.has_certificate ? `
                        <a href="/app/views/learner/certificates.php" 
                           class="block text-center bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm font-bold transition">
                            🎓 View Certificates
                        </a>
                    ` : `
                        <button onclick="generateCertificate()" 
                                id="generateCertBtn"
                                class="w-full bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm font-bold transition">
                            🎓 Get Certificate
                        </button>
                    `}
                </div>
            `;
            
            const sectionDesktop = document.getElementById('certificateSidebarSection');
            const sectionMobile = document.getElementById('certificateSidebarSectionMobile');
            if (sectionDesktop) sectionDesktop.innerHTML = certHTML;
            if (sectionMobile) sectionMobile.innerHTML = certHTML;
        }
    } catch (error) {
        console.error('Certificate check error:', error);
    }
}

async function generateCertificate() {
    const btn = document.getElementById('generateCertBtn');
    if (btn) {
        btn.disabled = true;
        btn.textContent = '⏳ Generating...';
    }
    
    try {
        const response = await fetch('/api/certificates.php?action=generate', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({course_id: courseId})
        });
        
        const result = await response.json();
        
        if (result.success && result.certificate_id) {
            window.location.href = '/app/views/learner/certificates.php';
        } else {
            alert('❌ ' + (result.error || 'Failed to generate certificate'));
            if (btn) {
                btn.disabled = false;
                btn.textContent = '🎓 Get Certificate';
            }
        }
    } catch (error) {
        alert('❌ Error: ' + error.message);
        if (btn) {
            btn.disabled = false;
            btn.textContent = '🎓 Get Certificate';
        }
    }
}

// Close modal on outside click
document.addEventListener('click', function(e) {
    const modal = document.getElementById('quizPromptModal');
    if (e.target === modal) {
        closeQuizModal();
    }
});

// Check certificate on page load
document.addEventListener('DOMContentLoaded', function() {
    console.log('Page loaded, checking certificate eligibility');
    checkCertificateEligibility();
});
</script>


</body>
</html>
