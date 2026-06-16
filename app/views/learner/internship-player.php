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

$internshipId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$requestedLessonId = isset($_GET['lesson']) ? intval($_GET['lesson']) : 0;

if (!$internshipId) {
    header('Location: /app/views/learner/my-internships.php');
    exit;
}

// Check enrollment
try {
    $enrollStmt = $db->prepare("SELECT * FROM internship_enrollments WHERE user_id = ? AND internship_id = ?");
    $enrollStmt->execute([$userId, $internshipId]);
    $enrollment = $enrollStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$enrollment) {
        header('Location: /app/views/learner/my-internships.php?error=not_enrolled');
        exit;
    }
} catch (Exception $e) {
    die("Enrollment check failed: " . $e->getMessage());
}

// Get internship details
try {
    $internshipStmt = $db->prepare("SELECT * FROM internships WHERE id = ?");
    $internshipStmt->execute([$internshipId]);
    $internship = $internshipStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$internship) {
        header('Location: /app/views/learner/my-internships.php?error=not_found');
        exit;
    }
} catch (Exception $e) {
    die("Internship fetch failed: " . $e->getMessage());
}

// Get modules with lessons
$modules = [];
try {
    $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    
    $stmt = $db->prepare("SELECT * FROM internship_modules WHERE internship_id = ? ORDER BY sort_order ASC, id ASC");
    $stmt->execute([$internshipId]);
    $modulesTemp = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $moduleIndex = 0;
    foreach ($modulesTemp as $moduleRow) {
        $modules[$moduleIndex] = $moduleRow;
        
        $stmt = $db->prepare("SELECT * FROM internship_lessons WHERE module_id = ? ORDER BY sort_order ASC, id ASC");
        $stmt->execute([$moduleRow['id']]);
        $lessonsTemp = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $modules[$moduleIndex]['lessons'] = [];
        $lessonIndex = 0;
        
        foreach ($lessonsTemp as $lessonRow) {
            $modules[$moduleIndex]['lessons'][$lessonIndex] = $lessonRow;
            
            $progressStmt = $db->prepare("SELECT * FROM internship_lesson_progress WHERE lesson_id = ? AND student_id = ?");
            $progressStmt->execute([$lessonRow['id'], $userId]);
            $progress = $progressStmt->fetch(PDO::FETCH_ASSOC);
            
            $modules[$moduleIndex]['lessons'][$lessonIndex]['is_completed'] = $progress ? $progress['is_completed'] : 0;
            $modules[$moduleIndex]['lessons'][$lessonIndex]['completed_at'] = $progress ? $progress['completed_at'] : null;
            
            $stmt = $db->prepare("SELECT * FROM internship_content_blocks WHERE lesson_id = ? ORDER BY sort_order ASC, id ASC");
            $stmt->execute([$lessonRow['id']]);
            $modules[$moduleIndex]['lessons'][$lessonIndex]['content_blocks'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $lessonIndex++;
        }
        
        $moduleIndex++;
    }
    
    unset($modulesTemp, $lessonsTemp, $moduleRow, $lessonRow);
    
} catch (Exception $e) {
    error_log("Modules fetch error: " . $e->getMessage());
    $modules = [];
}

// ---------------------------------------------------------------------------
// DAY-BY-DAY DRIP UNLOCK
// One lesson (one "day") unlocks per calendar day starting from enrollment.
// Day 1 is available on the enrollment day, Day 2 the next day, and so on.
// The cut-off is computed on the IST (Asia/Kolkata) calendar date, so new
// content unlocks exactly at 00:00 (12 AM) India time.
// ---------------------------------------------------------------------------
date_default_timezone_set('Asia/Kolkata');
$istTz = new DateTimeZone('Asia/Kolkata');

try {
    $enrolledAt = new DateTime($enrollment['created_at'] ?? 'now', $istTz);
} catch (Exception $e) {
    $enrolledAt = new DateTime('now', $istTz);
}
$nowIst = new DateTime('now', $istTz);

// Compare calendar dates only, so the unlock flips precisely at midnight IST.
$startDay = (clone $enrolledAt)->setTime(0, 0, 0);
$todayDay = (clone $nowIst)->setTime(0, 0, 0);
$daysPassed = (int) $startDay->diff($todayDay)->days;
$unlockedCount = $daysPassed + 1; // Day 1 unlocked on the enrollment day itself
if ($unlockedCount < 1) {
    $unlockedCount = 1;
}

// Tag every lesson with a sequential day number, lock status and unlock date.
$globalLessonNumber = 0;
foreach ($modules as $mi => $module) {
    foreach ($module['lessons'] as $li => $lessonRow2) {
        $globalLessonNumber++;
        $isLocked = ($globalLessonNumber > $unlockedCount);
        $unlockOn = (clone $startDay)->modify('+' . ($globalLessonNumber - 1) . ' days');

        $modules[$mi]['lessons'][$li]['day_number']  = $globalLessonNumber;
        $modules[$mi]['lessons'][$li]['is_locked']    = $isLocked;
        $modules[$mi]['lessons'][$li]['unlock_date']  = $unlockOn->format('D, M j, Y');
        $modules[$mi]['lessons'][$li]['unlock_in']    = $isLocked ? (int) $todayDay->diff($unlockOn)->days : 0;
    }
}
unset($module, $lessonRow2);

// Determine current lesson (never auto-open a locked one)
$currentLesson = null;
$currentModule = null;

// 1. Explicitly requested lesson (locked ones still resolve so we can show the lock screen)
if ($requestedLessonId) {
    foreach ($modules as $module) {
        foreach ($module['lessons'] as $lesson) {
            if ($lesson['id'] == $requestedLessonId) {
                $currentLesson = $lesson;
                $currentModule = $module;
                break 2;
            }
        }
    }
}

// 2. First unlocked & not-yet-completed lesson
if (!$currentLesson) {
    foreach ($modules as $module) {
        foreach ($module['lessons'] as $lesson) {
            if (empty($lesson['is_locked']) && empty($lesson['is_completed'])) {
                $currentLesson = $lesson;
                $currentModule = $module;
                break 2;
            }
        }
    }
}

// 3. Otherwise the latest unlocked lesson (e.g. today's day already completed)
if (!$currentLesson) {
    foreach ($modules as $module) {
        foreach ($module['lessons'] as $lesson) {
            if (empty($lesson['is_locked'])) {
                $currentLesson = $lesson;
                $currentModule = $module;
            }
        }
    }
}

// 4. Fallback to the very first lesson
if (!$currentLesson) {
    foreach ($modules as $module) {
        if (!empty($module['lessons'])) {
            $currentLesson = $module['lessons'][0];
            $currentModule = $module;
            break;
        }
    }
}

$currentLessonLocked = $currentLesson ? !empty($currentLesson['is_locked']) : false;

$contentBlocks = [];
if ($currentLesson && !$currentLessonLocked && !empty($currentLesson['content_blocks'])) {
    $contentBlocks = $currentLesson['content_blocks'];
}

// Check if internship is 100% complete
$totalLessonsCheck = $db->prepare("
    SELECT COUNT(DISTINCT il.id) as total
    FROM internship_lessons il
    INNER JOIN internship_modules im ON il.module_id = im.id
    WHERE im.internship_id = ?
");
$totalLessonsCheck->execute([$internshipId]);
$totalLessonsCount = $totalLessonsCheck->fetch(PDO::FETCH_ASSOC)['total'];

$completedLessonsCheck = $db->prepare("
    SELECT COUNT(DISTINCT ilp.lesson_id) as completed
    FROM internship_lesson_progress ilp
    INNER JOIN internship_lessons il ON ilp.lesson_id = il.id
    INNER JOIN internship_modules im ON il.module_id = im.id
    WHERE im.internship_id = ? AND ilp.student_id = ? AND ilp.is_completed = 1
");
$completedLessonsCheck->execute([$internshipId, $userId]);
$completedLessonsCount = $completedLessonsCheck->fetch(PDO::FETCH_ASSOC)['completed'];

$isFullyComplete = ($totalLessonsCount > 0 && $completedLessonsCount >= $totalLessonsCount);

// Check if already requested certificate
$certReqCheck = $db->prepare("SELECT * FROM internship_certificate_requests WHERE user_id = ? AND internship_id = ?");
$certReqCheck->execute([$userId, $internshipId]);
$hasCertificateRequest = $certReqCheck->fetch(PDO::FETCH_ASSOC);

function decodeText($text) {
    return html_entity_decode($text ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <title><?php echo decodeText($internship['title']); ?> - Internship Player</title>
    
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
        }
        .prose h1, .prose h2, .prose h3 { 
            margin-top: 1.5em; 
            margin-bottom: 0.75em;
            color: #111827;
        }
        .prose p { 
            margin-bottom: 1em; 
            line-height: 1.7; 
        }
        .prose ul, .prose ol { 
            margin-left: 1.5em; 
            margin-bottom: 1em; 
        }
        .prose li { 
            margin-bottom: 0.5em; 
        }
        .prose img {
            max-width: 100%;
            height: auto;
            border-radius: 8px;
            margin: 10px 0;
        }
        .prose video {
            max-width: 100%;
            border-radius: 12px;
            margin: 20px 0;
        }
        .prose iframe {
            max-width: 100%;
            border-radius: 12px;
            margin: 20px 0;
            min-height: 400px;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        .animate-spin {
            animation: spin 1s linear infinite;
        }
        
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
        
        .mobile-content {
            padding-top: 60px;
        }
        
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
            <h1 class="text-sm font-bold text-gray-900 truncate"><?php echo decodeText($internship['title']); ?></h1>
        </div>
        <a href="/app/views/learner/my-internships.php" class="p-2 hover:bg-gray-100 rounded-lg active:bg-gray-200 transition-colors">
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
                <h2 class="text-lg font-bold text-gray-900">Internship Content</h2>
                <button onclick="toggleSidebar()" class="p-2 hover:bg-gray-100 rounded-lg">
                    <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            <a href="/app/views/learner/my-internships.php" class="text-sm text-gray-600 hover:text-primary-600 inline-flex items-center gap-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Back to My Internships
            </a>
        </div>
        
        <div class="p-3">
            <?php if (empty($modules)): ?>
                <div class="text-center py-8 text-gray-500">
                    <svg class="w-12 h-12 text-gray-400 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <p class="text-sm">No content available</p>
                </div>
            <?php else: ?>
                <div class="space-y-2">
                    <?php foreach ($modules as $moduleIndex => $module): ?>
                        <div class="border border-gray-200 rounded-lg overflow-hidden">
                            <div class="bg-gray-50 px-3 py-2 border-b border-gray-200">
                                <div class="flex items-center gap-2">
                                    <span class="flex items-center justify-center w-5 h-5 rounded-full bg-primary-600 text-white text-xs font-bold flex-shrink-0">
                                        <?php echo $moduleIndex + 1; ?>
                                    </span>
                                    <span class="font-semibold text-gray-900 text-sm"><?php echo decodeText($module['title']); ?></span>
                                </div>
                            </div>
                            
                            <?php if (!empty($module['lessons'])): ?>
                                <div class="divide-y divide-gray-100">
                                    <?php foreach ($module['lessons'] as $lessonIndex => $lesson): ?>
                                        <?php if (!empty($lesson['is_locked'])): ?>
                                            <div class="block px-3 py-2 bg-gray-50/60 cursor-not-allowed select-none" title="Unlocks <?php echo htmlspecialchars($lesson['unlock_date']); ?>">
                                                <div class="flex items-start gap-2">
                                                    <div class="flex-shrink-0 mt-0.5">
                                                        <svg class="w-4 h-4 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
                                                        </svg>
                                                    </div>
                                                    <div class="flex-1 min-w-0">
                                                        <div class="text-xs text-gray-400 mb-0.5">Day <?php echo $lesson['day_number']; ?> · Locked</div>
                                                        <div class="font-medium text-gray-400 text-sm truncate"><?php echo decodeText($lesson['title']); ?></div>
                                                        <div class="text-xs text-gray-400 mt-1">🔒 Unlocks <?php echo htmlspecialchars($lesson['unlock_date']); ?></div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                        <a href="?id=<?php echo $internshipId; ?>&lesson=<?php echo $lesson['id']; ?>" 
                                           onclick="toggleSidebar()"
                                           class="block px-3 py-2 hover:bg-gray-50 transition-colors <?php echo ($currentLesson && $currentLesson['id'] == $lesson['id']) ? 'bg-primary-50 border-l-4 border-primary-600' : ''; ?>">
                                            <div class="flex items-start gap-2">
                                                <div class="flex-shrink-0 mt-0.5">
                                                    <?php if (!empty($lesson['is_completed'])): ?>
                                                        <svg class="w-4 h-4 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                                        </svg>
                                                    <?php else: ?>
                                                        <div class="w-4 h-4 rounded-full border-2 border-gray-300"></div>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <div class="flex-1 min-w-0">
                                                    <div class="text-xs text-gray-500 mb-0.5">Lesson <?php echo $lessonIndex + 1; ?></div>
                                                    <div class="font-medium text-gray-900 text-sm <?php echo ($currentLesson && $currentLesson['id'] == $lesson['id']) ? 'text-primary-700' : ''; ?>">
                                                        <?php echo decodeText($lesson['title']); ?>
                                                    </div>
                                                    <?php if (!empty($lesson['duration_minutes'])): ?>
                                                        <div class="text-xs text-gray-500 mt-1">⏱️ <?php echo $lesson['duration_minutes']; ?> min</div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </a>
                                        <?php endif; ?>
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
            <a href="/app/views/learner/my-internships.php" class="text-sm text-gray-600 hover:text-primary-600 mb-3 inline-flex items-center gap-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Back to My Internships
            </a>
            <h2 class="text-xl font-bold text-gray-900 mt-2"><?php echo decodeText($internship['title']); ?></h2>
        </div>
        
        <div class="p-4">
            <?php if (empty($modules)): ?>
                <div class="text-center py-8 text-gray-500">
                    <svg class="w-12 h-12 text-gray-400 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <p class="text-sm">No content available</p>
                </div>
            <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($modules as $moduleIndex => $module): ?>
                        <div class="border border-gray-200 rounded-lg overflow-hidden">
                            <div class="bg-gray-50 px-4 py-3 border-b border-gray-200">
                                <div class="flex items-center gap-2">
                                    <span class="flex items-center justify-center w-6 h-6 rounded-full bg-primary-600 text-white text-xs font-bold">
                                        <?php echo $moduleIndex + 1; ?>
                                    </span>
                                    <span class="font-semibold text-gray-900"><?php echo decodeText($module['title']); ?></span>
                                </div>
                            </div>
                            
                            <?php if (!empty($module['lessons'])): ?>
                                <div class="divide-y divide-gray-100">
                                    <?php foreach ($module['lessons'] as $lessonIndex => $lesson): ?>
                                        <?php if (!empty($lesson['is_locked'])): ?>
                                            <div class="block px-4 py-3 bg-gray-50/60 cursor-not-allowed select-none" title="Unlocks <?php echo htmlspecialchars($lesson['unlock_date']); ?>">
                                                <div class="flex items-start gap-3">
                                                    <div class="flex-shrink-0 mt-0.5">
                                                        <svg class="w-5 h-5 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
                                                        </svg>
                                                    </div>
                                                    <div class="flex-1 min-w-0">
                                                        <div class="text-xs text-gray-400 mb-1">Day <?php echo $lesson['day_number']; ?> · Locked</div>
                                                        <div class="font-medium text-gray-400"><?php echo decodeText($lesson['title']); ?></div>
                                                        <div class="text-xs text-gray-400 mt-1">🔒 Unlocks <?php echo htmlspecialchars($lesson['unlock_date']); ?></div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                        <a href="?id=<?php echo $internshipId; ?>&lesson=<?php echo $lesson['id']; ?>" 
                                           class="block px-4 py-3 hover:bg-gray-50 transition-colors <?php echo ($currentLesson && $currentLesson['id'] == $lesson['id']) ? 'bg-primary-50 border-l-4 border-primary-600' : ''; ?>">
                                            <div class="flex items-start gap-3">
                                                <div class="flex-shrink-0 mt-0.5">
                                                    <?php if (!empty($lesson['is_completed'])): ?>
                                                        <svg class="w-5 h-5 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                                        </svg>
                                                    <?php else: ?>
                                                        <div class="w-5 h-5 rounded-full border-2 border-gray-300"></div>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <div class="flex-1 min-w-0">
                                                    <div class="text-xs text-gray-500 mb-1">Lesson <?php echo $lessonIndex + 1; ?></div>
                                                    <div class="font-medium text-gray-900 <?php echo ($currentLesson && $currentLesson['id'] == $lesson['id']) ? 'text-primary-700' : ''; ?>">
                                                        <?php echo decodeText($lesson['title']); ?>
                                                    </div>
                                                    <?php if (!empty($lesson['duration_minutes'])): ?>
                                                        <div class="text-xs text-gray-500 mt-1">⏱️ <?php echo $lesson['duration_minutes']; ?> minutes</div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </a>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Main Content Area -->
    <div class="flex-1 overflow-y-auto bg-white mobile-content">
        <?php if ($currentLesson): ?>
            <div class="max-w-4xl mx-auto p-4 sm:p-6 md:p-8">
                <div class="mb-6 sm:mb-8 pb-4 sm:pb-6 border-b border-gray-200">
                    <div class="flex items-center gap-2 text-xs sm:text-sm text-primary-600 font-medium mb-2 sm:mb-3">
                        <svg class="w-3 h-3 sm:w-4 sm:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
                        </svg>
                        <?php echo decodeText($currentModule['title']); ?>
                    </div>
                    <h1 class="text-2xl sm:text-3xl md:text-4xl font-bold text-gray-900 mb-2 sm:mb-4">
                        <?php echo decodeText($currentLesson['title']); ?>
                    </h1>
                    <?php if ($currentLesson['description']): ?>
                        <p class="text-gray-600"><?php echo decodeText($currentLesson['description']); ?></p>
                    <?php endif; ?>
                </div>
                
                <?php if ($currentLessonLocked): ?>
                    <div class="bg-gradient-to-br from-gray-50 to-gray-100 border-2 border-gray-200 rounded-2xl p-8 sm:p-14 text-center">
                        <div class="w-16 h-16 sm:w-20 sm:h-20 bg-white rounded-full shadow-md flex items-center justify-center mx-auto mb-5">
                            <svg class="w-8 h-8 sm:w-10 sm:h-10 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        <h3 class="text-xl sm:text-2xl font-bold text-gray-900 mb-2">Day <?php echo $currentLesson['day_number']; ?> is locked</h3>
                        <p class="text-sm sm:text-base text-gray-600 max-w-md mx-auto mb-2">
                            This lesson unlocks on <strong><?php echo htmlspecialchars($currentLesson['unlock_date']); ?></strong> at 12:00 AM (IST).
                        </p>
                        <p class="text-sm text-gray-500 max-w-md mx-auto mb-6">
                            <?php if ($currentLesson['unlock_in'] == 1): ?>
                                Come back tomorrow — the next day unlocks in 1 day.
                            <?php else: ?>
                                New content is released one day at a time. This day unlocks in <?php echo $currentLesson['unlock_in']; ?> days.
                            <?php endif; ?>
                        </p>
                        <a href="?id=<?php echo $internshipId; ?>" class="inline-flex items-center gap-2 bg-primary-600 hover:bg-primary-700 text-white px-6 py-3 rounded-xl font-semibold text-sm sm:text-base transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                            </svg>
                            Go to today's lesson
                        </a>
                    </div>
                <?php elseif (empty($contentBlocks)): ?>
                    <div class="bg-yellow-50 border-2 border-yellow-200 rounded-xl p-8 sm:p-12 text-center">
                        <svg class="w-12 h-12 sm:w-16 sm:h-16 text-yellow-500 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        <h3 class="text-lg sm:text-xl font-bold text-yellow-900 mb-2">No Content Available</h3>
                        <p class="text-sm sm:text-base text-yellow-800">This lesson doesn't have any content yet.</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-6 sm:space-y-8">
                        <?php foreach ($contentBlocks as $block): ?>
                            <?php if ($block['type'] === 'text'): ?>
                                <div class="content-block">
                                    <div class="prose prose-sm sm:prose-base lg:prose-lg max-w-none">
                                        <?php echo html_entity_decode($block['content'], ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>
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
                
                <div class="mt-8 sm:mt-12 pt-6 sm:pt-8 border-t-2 border-gray-200">
                    <?php if ($currentLessonLocked): ?>
                        <!-- locked lesson: no completion action -->
                    <?php elseif (empty($currentLesson['is_completed'])): ?>
                        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between bg-gray-50 rounded-xl p-4 sm:p-6 gap-4">
                            <div class="flex-1">
                                <h3 class="font-bold text-gray-900 text-base sm:text-lg mb-1">Completed this lesson?</h3>
                                <p class="text-gray-600 text-sm">Mark it as complete to track your progress</p>
                            </div>
                            <button onclick="markAsComplete(<?php echo $currentLesson['id']; ?>, <?php echo $internshipId; ?>)" 
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
                                <p class="text-green-700 text-sm">You completed this lesson</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="flex items-center justify-center h-full p-8">
                <div class="text-center max-w-md">
                    <svg class="w-20 h-20 sm:w-24 sm:h-24 text-gray-400 mx-auto mb-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                    </svg>
                    <h3 class="text-xl sm:text-2xl font-bold text-gray-900 mb-3">No Lessons Available</h3>
                    <p class="text-sm sm:text-base text-gray-600 mb-6">This internship doesn't have any lessons yet.</p>
                    <a href="/app/views/learner/my-internships.php" class="inline-block bg-primary-600 hover:bg-primary-700 text-white px-6 py-3 rounded-lg font-semibold text-sm sm:text-base">
                        Back to My Internships
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Certificate Completion Modal -->
<?php if ($isFullyComplete && !$hasCertificateRequest): ?>
<div id="certificateModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.7); backdrop-filter: blur(8px); z-index: 9999; align-items: center; justify-content: center; padding: 20px;">
    <div style="background: white; border-radius: 24px; max-width: 600px; width: 100%; padding: 40px; box-shadow: 0 25px 70px rgba(0, 0, 0, 0.4); animation: modalSlideIn 0.3s ease-out; text-align: center;">
        
        <div style="width: 100px; height: 100px; background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 24px; box-shadow: 0 8px 24px rgba(34, 197, 94, 0.3);">
            <svg style="width: 50px; height: 50px; color: white;" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
            </svg>
        </div>
        
        <h2 style="font-family: 'Poppins', sans-serif; font-size: 28px; font-weight: 800; color: #111827; margin-bottom: 16px; line-height: 1.3;">
            🎉 Congratulations!
        </h2>
        
        <p style="font-size: 16px; color: #6b7280; line-height: 1.6; margin-bottom: 24px;">
            You've successfully completed <strong><?php echo htmlspecialchars($internship['title']); ?></strong>! 
            Request your certificate now.
        </p>
        
        <div style="background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%); border-radius: 16px; padding: 20px; margin-bottom: 24px;">
            <div style="display: flex; align-items: center; justify-content: center; gap: 12px; margin-bottom: 8px;">
                <svg style="width: 24px; height: 24px; color: #16a34a;" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M6 2a2 2 0 00-2 2v12a2 2 0 002 2h8a2 2 0 002-2V7.414A2 2 0 0015.414 6L12 2.586A2 2 0 0010.586 2H6zm5 6a1 1 0 10-2 0v3.586l-1.293-1.293a1 1 0 10-1.414 1.414l3 3a1 1 0 001.414 0l3-3a1 1 0 00-1.414-1.414L11 11.586V8z" clip-rule="evenodd"/>
                </svg>
                <span style="font-weight: 700; font-size: 18px; color: #15803d;">Certificate Available</span>
            </div>
            <p style="font-size: 14px; color: #166534;">
                All <?php echo $totalLessonsCount; ?> lessons completed • 100% Progress
            </p>
        </div>
        
        <div style="display: flex; flex-direction: column; gap: 12px;">
            <a href="/app/views/learner/request-internship-certificate.php?id=<?php echo $internshipId; ?>" 
               style="width: 100%; background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%); color: white; padding: 18px 24px; border-radius: 14px; font-weight: 700; font-size: 16px; border: none; cursor: pointer; box-shadow: 0 6px 20px rgba(251, 191, 36, 0.35); transition: all 0.3s ease; font-family: 'Poppins', sans-serif; text-decoration: none; display: block; text-align: center;">
                🎓 Request Certificate Now
            </a>
            
            <button onclick="closeCertificateModal()" 
                    style="width: 100%; background: #f3f4f6; color: #374151; padding: 14px 24px; border-radius: 14px; font-weight: 600; font-size: 15px; border: none; cursor: pointer; transition: all 0.3s ease;">
                Maybe Later
            </button>
        </div>
    </div>
</div>

<script>
window.addEventListener('DOMContentLoaded', function() {
    const certificateModal = document.getElementById('certificateModal');
    if (certificateModal) {
        const modalClosed = sessionStorage.getItem('certificateModalClosed_<?php echo $internshipId; ?>');
        if (!modalClosed) {
            certificateModal.style.display = 'flex';
        }
    }
});

function closeCertificateModal() {
    const modal = document.getElementById('certificateModal');
    if (modal) {
        modal.style.display = 'none';
        sessionStorage.setItem('certificateModalClosed_<?php echo $internshipId; ?>', 'true');
    }
}
</script>
<?php endif; ?>

<script>
const internshipId = <?php echo $internshipId; ?>;

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

function markAsComplete(lessonId, internshipId) {
    const btn = document.getElementById('completeBtn');
    if (!btn) return;
    
    btn.disabled = true;
    btn.innerHTML = `
        <svg class="animate-spin w-5 h-5 inline-block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        <span class="ml-2">Marking...</span>
    `;
    
    fetch('/api/internship-lesson-complete.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        },
        body: JSON.stringify({ lesson_id: lessonId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            btn.innerHTML = `
                <svg class="w-6 h-6 inline-block" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
                <span class="ml-2">Completed!</span>
            `;
            btn.classList.remove('bg-primary-600', 'hover:bg-primary-700');
            btn.classList.add('bg-green-600');
            
            setTimeout(() => {
                findAndNavigateToNextLesson(lessonId);
            }, 1500);
        } else {
            throw new Error(data.message || 'Failed to mark as complete');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error: ' + error.message);
        btn.disabled = false;
        btn.innerHTML = `
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            <span class="ml-2">Mark as Complete</span>
        `;
    });
}

function findAndNavigateToNextLesson(currentLessonId) {
    const lessonLinks = document.querySelectorAll('a[href*="lesson="]');
    let foundCurrent = false;
    let nextLessonUrl = null;
    
    for (let i = 0; i < lessonLinks.length; i++) {
        const link = lessonLinks[i];
        const href = link.getAttribute('href');
        const lessonIdMatch = href.match(/lesson=(\d+)/);
        
        if (lessonIdMatch) {
            const linkLessonId = parseInt(lessonIdMatch[1]);
            
            if (foundCurrent) {
                nextLessonUrl = href;
                break;
            }
            
            if (linkLessonId === currentLessonId) {
                foundCurrent = true;
            }
        }
    }
    
    if (nextLessonUrl) {
        window.location.href = nextLessonUrl;
    } else {
        // Last lesson completed - reload to show certificate modal
        <?php if (!$hasCertificateRequest): ?>
            sessionStorage.removeItem('certificateModalClosed_<?php echo $internshipId; ?>');
        <?php endif; ?>
        location.reload();
    }
}
</script>

</body>
</html>
