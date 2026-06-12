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

// Get all modules with lessons
$db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
$stmt = $db->prepare("SELECT * FROM internship_modules WHERE internship_id = ? ORDER BY sort_order ASC, id ASC");
$stmt->execute([$internshipId]);
$modulesTemp = $stmt->fetchAll(PDO::FETCH_ASSOC);

$modules = [];
$moduleIndex = 0;

foreach ($modulesTemp as $moduleRow) {
    $modules[$moduleIndex] = $moduleRow;
    
    $stmt = $db->prepare("SELECT * FROM internship_lessons WHERE module_id = ? ORDER BY sort_order ASC");
    $stmt->execute([$moduleRow['id']]);
    $lessonsTemp = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $modules[$moduleIndex]['lessons'] = [];
    $lessonIndex = 0;
    
    foreach ($lessonsTemp as $lessonRow) {
        $modules[$moduleIndex]['lessons'][$lessonIndex] = $lessonRow;
        
        // Get content blocks
        $stmt = $db->prepare("SELECT * FROM internship_content_blocks WHERE lesson_id = ? ORDER BY sort_order ASC");
        $stmt->execute([$lessonRow['id']]);
        $modules[$moduleIndex]['lessons'][$lessonIndex]['content_blocks'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Check if lesson has quiz
        $quizStmt = $db->prepare("SELECT COUNT(*) as quiz_count FROM internship_quiz_questions WHERE lesson_id = ?");
        $quizStmt->execute([$lessonRow['id']]);
        $quizResult = $quizStmt->fetch(PDO::FETCH_ASSOC);
        $modules[$moduleIndex]['lessons'][$lessonIndex]['quiz_count'] = $quizResult['quiz_count'];
        
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
    <title>Internship Builder - <?php echo htmlspecialchars($internship['title']); ?></title>
    
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
        
        .module-card {
            background: white;
            border: 2px solid #e5e7eb;
            border-radius: 16px;
            transition: all 0.3s ease;
        }
        
        .module-card:hover {
            border-color: #22c55e;
            box-shadow: 0 4px 20px rgba(34, 197, 94, 0.1);
        }
        
        .lesson-card {
            background: #f9fafb;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            transition: all 0.3s ease;
        }
        
        .lesson-card:hover {
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
            content: 'Start typing your lesson content here...';
            color: #9ca3af;
        }

        #contentEditor:focus {
            outline: none;
            border-color: #22c55e;
        }

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

        #contentEditor img {
            max-width: 100%;
            height: auto;
            border-radius: 8px;
            margin: 10px 0;
            display: block;
        }

        #contentEditor video {
            max-width: 100%;
            border-radius: 12px;
            margin: 20px 0;
            display: block;
        }

        #contentEditor iframe {
            max-width: 100%;
            border-radius: 12px;
            margin: 20px 0;
            display: block;
            min-height: 400px;
        }

        #contentEditor a {
            color: #2563eb;
            text-decoration: underline;
        }

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

        #contentEditor blockquote {
            border-left: 4px solid #22c55e;
            padding-left: 16px;
            margin: 10px 0;
            color: #6b7280;
            font-style: italic;
        }

        #contentEditor hr {
            border: none;
            border-top: 2px solid #e5e7eb;
            margin: 20px 0;
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
                        <a href="/app/views/admin/internships/list.php" class="hover:text-primary-600">Internships</a>
                        <span>/</span>
                        <span>Builder</span>
                    </div>
                    <h1 class="text-3xl font-bold text-gray-900">🎓 <?php echo htmlspecialchars($internship['title']); ?></h1>
                    <p class="text-gray-600 mt-1">Build your internship curriculum</p>
                </div>
                <div class="flex items-center gap-3">
                    <button onclick="window.history.back()" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-6 py-3 rounded-lg font-semibold transition-colors">
                        ← Back
                    </button>
                    <a href="/app/views/admin/internships/views.php?id=<?php echo $internship['id']; ?>" class="bg-gray-600 hover:bg-gray-700 text-white px-6 py-3 rounded-lg font-semibold transition-colors">
                        👁️ View
                    </a>
                    <button onclick="openAddModuleModal()" class="btn-primary flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                        Add Module
                    </button>
                </div>
            </div>
        </div>
    </header>

    <main class="p-8">
        <div id="alertContainer" class="mb-6 max-w-5xl mx-auto"></div>

        <?php if (empty($modules)): ?>
            <div class="max-w-3xl mx-auto">
                <div class="bg-white rounded-2xl border-2 border-dashed border-gray-300 p-12 text-center">
                    <div class="w-24 h-24 bg-primary-100 rounded-full flex items-center justify-center mx-auto mb-6">
                        <svg class="w-12 h-12 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                        </svg>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 mb-3">Start Building Your Internship</h3>
                    <p class="text-gray-600 mb-8 text-lg">Create your first module to organize your content</p>
                    <button onclick="openAddModuleModal()" class="btn-primary inline-flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                        Add First Module
                    </button>
                </div>
            </div>
        <?php else: ?>
            <div class="max-w-5xl mx-auto space-y-6">
                <?php foreach ($modules as $index => $module): ?>
                    <div class="module-card">
                        <div class="p-6">
                            <div class="flex items-center justify-between mb-6">
                                <div class="flex items-center gap-4 flex-1">
                                    <div class="w-12 h-12 bg-gradient-to-br from-primary-500 to-primary-700 rounded-xl flex items-center justify-center text-white font-bold text-xl">
                                        <?php echo $index + 1; ?>
                                    </div>
                                    <div class="flex-1">
                                        <h3 class="text-xl font-bold text-gray-900"><?php echo htmlspecialchars($module['title']); ?></h3>
                                        <p class="text-sm text-gray-500 mt-1"><?php echo count($module['lessons']); ?> lessons</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <button onclick="openAddLessonModal(<?php echo $module['id']; ?>)" class="bg-primary-50 text-primary-700 hover:bg-primary-100 px-4 py-2 rounded-lg font-semibold text-sm transition-colors">
                                        + Add Lesson
                                    </button>
                                    <button onclick="editModule(<?php echo $module['id']; ?>, '<?php echo addslashes($module['title']); ?>')" class="text-gray-600 hover:text-gray-800 p-2 rounded-lg hover:bg-gray-100 transition-colors">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                        </svg>
                                    </button>
                                    <button onclick="deleteModule(<?php echo $module['id']; ?>)" class="text-red-600 hover:text-red-700 p-2 rounded-lg hover:bg-red-50 transition-colors">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            <!-- Lessons -->
                            <?php if (empty($module['lessons'])): ?>
                                <div class="bg-gray-50 border-2 border-dashed border-gray-300 rounded-lg p-8 text-center">
                                    <p class="text-gray-500 mb-4">No lessons yet in this module</p>
                                    <button onclick="openAddLessonModal(<?php echo $module['id']; ?>)" class="text-primary-600 hover:text-primary-700 font-semibold">
                                        + Add First Lesson
                                    </button>
                                </div>
                            <?php else: ?>
                                <div class="space-y-3">
                                    <?php foreach ($module['lessons'] as $lessonIndex => $lesson): ?>
                                        <div class="lesson-card p-4">
                                            <div class="flex items-center justify-between">
                                                <div class="flex items-center gap-3 flex-1">
                                                    <div class="w-8 h-8 bg-white rounded-lg flex items-center justify-center text-primary-600 font-bold text-sm border-2 border-primary-200">
                                                        <?php echo $lessonIndex + 1; ?>
                                                    </div>
                                                    <div class="flex-1">
                                                        <h4 class="font-semibold text-gray-900"><?php echo htmlspecialchars($lesson['title']); ?></h4>
                                                        <div class="flex items-center gap-3 mt-1">
                                                            <span class="text-xs text-gray-500">
                                                                <?php echo count($lesson['content_blocks']); ?> content blocks
                                                            </span>
                                                            <?php if ($lesson['quiz_count'] > 0): ?>
                                                                <span class="text-xs bg-orange-100 text-orange-700 px-2 py-0.5 rounded-full font-semibold">
                                                                    📝 <?php echo $lesson['quiz_count']; ?> quiz questions
                                                                </span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="flex items-center gap-2">
                                                    <button onclick="openContentModal(<?php echo $lesson['id']; ?>, '<?php echo addslashes($lesson['title']); ?>')" class="bg-blue-50 text-blue-700 hover:bg-blue-100 px-3 py-1.5 rounded-lg text-sm font-semibold transition-colors" title="Edit Content">
                                                        📄 Content
                                                    </button>
                                                    <button onclick="openQuizModal(<?php echo $lesson['id']; ?>, '<?php echo addslashes($lesson['title']); ?>')" class="bg-orange-50 text-orange-700 hover:bg-orange-100 px-3 py-1.5 rounded-lg text-sm font-semibold transition-colors" title="Manage Quiz">
                                                        📝 Quiz
                                                    </button>
                                                    <button onclick="editLesson(<?php echo $lesson['id']; ?>, <?php echo $module['id']; ?>, '<?php echo addslashes($lesson['title']); ?>')" class="text-gray-600 hover:text-gray-800 p-2 rounded-lg hover:bg-gray-100 transition-colors" title="Edit Lesson">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                                        </svg>
                                                    </button>
                                                    <button onclick="deleteLesson(<?php echo $lesson['id']; ?>)" class="text-red-600 hover:text-red-700 p-2 rounded-lg hover:bg-red-50 transition-colors" title="Delete Lesson">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                        </svg>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
</div>

<!-- Add Module Modal -->
<div id="addModuleModal" class="modal">
    <div class="modal-content">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-2xl font-bold text-gray-900">Add New Module</h2>
            <button onclick="closeModal('addModuleModal')" class="text-gray-400 hover:text-gray-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        <form id="addModuleForm" onsubmit="handleAddModule(event)">
            <div class="mb-4">
                <label class="block text-sm font-semibold text-gray-700 mb-2">Module Title *</label>
                <input type="text" name="title" required class="form-input" placeholder="e.g., Introduction to Web Development">
            </div>
            <div class="mb-4">
                <label class="block text-sm font-semibold text-gray-700 mb-2">Description (Optional)</label>
                <textarea name="description" rows="3" class="form-textarea" placeholder="Brief description of this module"></textarea>
            </div>
            <div class="flex gap-3">
                <button type="submit" class="btn-primary flex-1">Add Module</button>
                <button type="button" onclick="closeModal('addModuleModal')" class="px-6 py-3 border-2 border-gray-300 rounded-lg font-semibold hover:bg-gray-50 transition-colors">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Module Modal -->
<div id="editModuleModal" class="modal">
    <div class="modal-content">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-2xl font-bold text-gray-900">Edit Module</h2>
            <button onclick="closeModal('editModuleModal')" class="text-gray-400 hover:text-gray-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        <form id="editModuleForm" onsubmit="handleEditModule(event)">
            <input type="hidden" name="module_id" id="editModuleId">
            <div class="mb-4">
                <label class="block text-sm font-semibold text-gray-700 mb-2">Module Title *</label>
                <input type="text" name="title" id="editModuleTitle" required class="form-input">
            </div>
            <div class="flex gap-3">
                <button type="submit" class="btn-primary flex-1">Save Changes</button>
                <button type="button" onclick="closeModal('editModuleModal')" class="px-6 py-3 border-2 border-gray-300 rounded-lg font-semibold hover:bg-gray-50 transition-colors">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Add Lesson Modal -->
<div id="addLessonModal" class="modal">
    <div class="modal-content">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-2xl font-bold text-gray-900">Add New Lesson</h2>
            <button onclick="closeModal('addLessonModal')" class="text-gray-400 hover:text-gray-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        <form id="addLessonForm" onsubmit="handleAddLesson(event)">
            <input type="hidden" name="module_id" id="addLessonModuleId">
            <div class="mb-4">
                <label class="block text-sm font-semibold text-gray-700 mb-2">Lesson Title *</label>
                <input type="text" name="title" required class="form-input" placeholder="e.g., Introduction to HTML">
            </div>
            <div class="mb-4">
                <label class="block text-sm font-semibold text-gray-700 mb-2">Description (Optional)</label>
                <textarea name="description" rows="3" class="form-textarea" placeholder="What students will learn in this lesson"></textarea>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-semibold text-gray-700 mb-2">Duration (minutes)</label>
                <input type="number" name="duration_minutes" min="0" class="form-input" placeholder="e.g., 30">
            </div>
            <div class="flex gap-3">
                <button type="submit" class="btn-primary flex-1">Add Lesson</button>
                <button type="button" onclick="closeModal('addLessonModal')" class="px-6 py-3 border-2 border-gray-300 rounded-lg font-semibold hover:bg-gray-50 transition-colors">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Lesson Modal -->
<div id="editLessonModal" class="modal">
    <div class="modal-content">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-2xl font-bold text-gray-900">Edit Lesson</h2>
            <button onclick="closeModal('editLessonModal')" class="text-gray-400 hover:text-gray-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        <form id="editLessonForm" onsubmit="handleEditLesson(event)">
            <input type="hidden" name="lesson_id" id="editLessonId">
            <div class="mb-4">
                <label class="block text-sm font-semibold text-gray-700 mb-2">Lesson Title *</label>
                <input type="text" name="title" id="editLessonTitle" required class="form-input">
            </div>
            <div class="flex gap-3">
                <button type="submit" class="btn-primary flex-1">Save Changes</button>
                <button type="button" onclick="closeModal('editLessonModal')" class="px-6 py-3 border-2 border-gray-300 rounded-lg font-semibold hover:bg-gray-50 transition-colors">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Content Editor Modal - ENHANCED -->
<div id="contentModal" class="modal">
    <div class="modal-content" style="max-width: 1200px;">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h2 class="text-2xl font-bold text-gray-900">Lesson Content Editor</h2>
                <p class="text-sm text-gray-600 mt-1" id="contentLessonTitle"></p>
            </div>
            <button onclick="closeModal('contentModal')" class="text-gray-400 hover:text-gray-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        <!-- Toolbar -->
        <div class="bg-gray-50 rounded-t-lg border-2 border-b-0 border-gray-200 p-3 flex flex-wrap gap-2">
            <button type="button" onclick="execCmd('bold')" class="editor-btn" title="Bold">
                <i class="fas fa-bold"></i>
            </button>
            <button type="button" onclick="execCmd('italic')" class="editor-btn" title="Italic">
                <i class="fas fa-italic"></i>
            </button>
            <button type="button" onclick="execCmd('underline')" class="editor-btn" title="Underline">
                <i class="fas fa-underline"></i>
            </button>
            <div class="w-px bg-gray-300"></div>
            <button type="button" onclick="execCmd('formatBlock', 'h1')" class="editor-btn" title="Heading 1">
                H1
            </button>
            <button type="button" onclick="execCmd('formatBlock', 'h2')" class="editor-btn" title="Heading 2">
                H2
            </button>
            <button type="button" onclick="execCmd('formatBlock', 'h3')" class="editor-btn" title="Heading 3">
                H3
            </button>
            <div class="w-px bg-gray-300"></div>
            <button type="button" onclick="execCmd('insertUnorderedList')" class="editor-btn" title="Bullet List">
                <i class="fas fa-list-ul"></i>
            </button>
            <button type="button" onclick="execCmd('insertOrderedList')" class="editor-btn" title="Numbered List">
                <i class="fas fa-list-ol"></i>
            </button>
            <div class="w-px bg-gray-300"></div>
            <button type="button" onclick="insertLink()" class="editor-btn" title="Insert Link">
                <i class="fas fa-link"></i>
            </button>
            <button type="button" onclick="showImageUploader()" class="editor-btn" title="Insert Image">
                <i class="fas fa-image"></i>
            </button>
            <button type="button" onclick="showVideoUploader()" class="editor-btn" title="Insert Video">
                <i class="fas fa-video"></i>
            </button>
        </div>

        <!-- Image Upload Panel -->
        <div id="imageUploadPanel" class="hidden bg-blue-50 border-2 border-blue-200 rounded-lg p-4 mb-4">
            <h3 class="font-bold text-gray-900 mb-3">📸 Insert Image</h3>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Upload from Device</label>
                    <input type="file" id="localImageInput" accept="image/*" class="form-input">
                    <div id="imageUploadProgress" class="hidden mt-2">
                        <div class="bg-primary-200 rounded-full h-2">
                            <div id="imageUploadBar" class="bg-primary-600 h-2 rounded-full" style="width: 0%"></div>
                        </div>
                        <p class="text-xs text-gray-600 mt-1">Uploading...</p>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Or Image URL</label>
                    <input type="url" id="imageUrlInput" class="form-input mb-2" placeholder="https://example.com/image.jpg">
                    <button onclick="insertImageFromUrl()" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-semibold">
                        Insert Image
                    </button>
                </div>
            </div>
            <button onclick="closeImageUploader()" class="mt-3 text-sm text-gray-600 hover:text-gray-900">✕ Close</button>
        </div>

        <!-- Video Upload Panel -->
        <div id="videoUploadPanel" class="hidden bg-purple-50 border-2 border-purple-200 rounded-lg p-4 mb-4">
            <h3 class="font-bold text-gray-900 mb-3">🎥 Insert Video</h3>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Upload Video File</label>
                    <input type="file" id="localVideoInput" accept="video/*" class="form-input">
                    <div id="videoUploadProgress" class="hidden mt-2">
                        <div class="bg-purple-200 rounded-full h-2">
                            <div id="videoUploadBar" class="bg-purple-600 h-2 rounded-full" style="width: 0%"></div>
                        </div>
                        <p class="text-xs text-gray-600 mt-1">Uploading video...</p>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">Max 50MB</p>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">YouTube / Video URL</label>
                    <input type="url" id="videoUrlInput" class="form-input mb-2" placeholder="https://youtube.com/watch?v=...">
                    <button onclick="insertVideoFromUrl()" class="bg-purple-600 text-white px-4 py-2 rounded-lg text-sm font-semibold">
                        Insert Video
                    </button>
                </div>
            </div>
            <button onclick="closeVideoUploader()" class="mt-3 text-sm text-gray-600 hover:text-gray-900">✕ Close</button>
        </div>

        <!-- Editor -->
        <div id="contentEditor" contenteditable="true"></div>

        <!-- Save Button -->
        <div class="mt-6 flex gap-3">
            <button onclick="saveContent()" class="btn-primary flex-1">
                <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                Save Content
            </button>
            <button type="button" onclick="closeModal('contentModal')" class="px-6 py-3 border-2 border-gray-300 rounded-lg font-semibold hover:bg-gray-50 transition-colors">Cancel</button>
        </div>
    </div>
</div>

<!-- Quiz Modal -->
<div id="quizModal" class="modal">
    <div class="modal-content" style="max-width: 1000px;">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h2 class="text-2xl font-bold text-gray-900">📝 Quiz Manager</h2>
                <p class="text-sm text-gray-600 mt-1" id="quizLessonTitle"></p>
            </div>
            <button onclick="closeModal('quizModal')" class="text-gray-400 hover:text-gray-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        <!-- Quiz List -->
        <div id="quizList" class="mb-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-bold text-gray-900">Quiz Questions</h3>
                <button onclick="openAddQuestionModal()" class="bg-primary-600 text-white px-4 py-2 rounded-lg font-semibold text-sm">
                    + Add Question
                </button>
            </div>
            <div id="questionsContainer" class="space-y-3">
                <!-- Questions will be loaded here -->
            </div>
        </div>

        <div class="flex gap-3">
            <button type="button" onclick="closeModal('quizModal')" class="px-6 py-3 border-2 border-gray-300 rounded-lg font-semibold hover:bg-gray-50 transition-colors">Close</button>
        </div>
    </div>
</div>

<!-- Add Question Modal -->
<div id="addQuestionModal" class="modal">
    <div class="modal-content">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-2xl font-bold text-gray-900">Add Quiz Question</h2>
            <button onclick="closeModal('addQuestionModal')" class="text-gray-400 hover:text-gray-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        <form id="addQuestionForm" onsubmit="handleAddQuestion(event)">
            <div class="mb-4">
                <label class="block text-sm font-semibold text-gray-700 mb-2">Question *</label>
                <textarea name="question" required rows="3" class="form-textarea" placeholder="Enter your question here"></textarea>
            </div>
            
            <div class="mb-4">
                <label class="block text-sm font-semibold text-gray-700 mb-2">Question Type *</label>
                <select name="type" id="questionType" required class="form-select" onchange="toggleOptionsFields()">
                    <option value="multiple_choice">Multiple Choice</option>
                    <option value="true_false">True/False</option>
                    <option value="short_answer">Short Answer</option>
                </select>
            </div>

            <div id="optionsFields">
                <label class="block text-sm font-semibold text-gray-700 mb-2">Options</label>
                <div class="space-y-2 mb-2">
                    <div class="flex gap-2">
                        <input type="text" name="option_1" class="form-input flex-1" placeholder="Option 1">
                        <label class="flex items-center gap-2">
                            <input type="radio" name="correct_option" value="1" class="w-5 h-5">
                            <span class="text-sm">Correct</span>
                        </label>
                    </div>
                    <div class="flex gap-2">
                        <input type="text" name="option_2" class="form-input flex-1" placeholder="Option 2">
                        <label class="flex items-center gap-2">
                            <input type="radio" name="correct_option" value="2" class="w-5 h-5">
                            <span class="text-sm">Correct</span>
                        </label>
                    </div>
                    <div class="flex gap-2">
                        <input type="text" name="option_3" class="form-input flex-1" placeholder="Option 3">
                        <label class="flex items-center gap-2">
                            <input type="radio" name="correct_option" value="3" class="w-5 h-5">
                            <span class="text-sm">Correct</span>
                        </label>
                    </div>
                    <div class="flex gap-2">
                        <input type="text" name="option_4" class="form-input flex-1" placeholder="Option 4">
                        <label class="flex items-center gap-2">
                            <input type="radio" name="correct_option" value="4" class="w-5 h-5">
                            <span class="text-sm">Correct</span>
                        </label>
                    </div>
                </div>
            </div>

            <div id="answerField" class="mb-4 hidden">
                <label class="block text-sm font-semibold text-gray-700 mb-2">Correct Answer *</label>
                <input type="text" name="correct_answer" class="form-input" placeholder="Enter the correct answer">
            </div>

            <div class="mb-4">
                <label class="block text-sm font-semibold text-gray-700 mb-2">Explanation (Optional)</label>
                <textarea name="explanation" rows="2" class="form-textarea" placeholder="Explain why this is the correct answer"></textarea>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-semibold text-gray-700 mb-2">Points</label>
                <input type="number" name="points" value="10" min="1" class="form-input">
            </div>

            <div class="flex gap-3">
                <button type="submit" class="btn-primary flex-1">Add Question</button>
                <button type="button" onclick="closeModal('addQuestionModal')" class="px-6 py-3 border-2 border-gray-300 rounded-lg font-semibold hover:bg-gray-50 transition-colors">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Question Modal -->
<div id="editQuestionModal" class="modal">
    <div class="modal-content">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-2xl font-bold text-gray-900">Edit Quiz Question</h2>
            <button onclick="closeModal('editQuestionModal')" class="text-gray-400 hover:text-gray-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        <form id="editQuestionForm" onsubmit="handleEditQuestion(event)">
            <input type="hidden" name="question_id" id="editQuestionId">
            <div class="mb-4">
                <label class="block text-sm font-semibold text-gray-700 mb-2">Question *</label>
                <textarea name="question" id="editQuestionText" required rows="3" class="form-textarea"></textarea>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-semibold text-gray-700 mb-2">Points</label>
                <input type="number" name="points" id="editQuestionPoints" min="1" class="form-input">
            </div>
            <div class="flex gap-3">
                <button type="submit" class="btn-primary flex-1">Save Changes</button>
                <button type="button" onclick="closeModal('editQuestionModal')" class="px-6 py-3 border-2 border-gray-300 rounded-lg font-semibold hover:bg-gray-50 transition-colors">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
    const internshipId = <?php echo $internshipId; ?>;
    let currentLessonId = null;
    let currentQuizLessonId = null;
    
    // MODAL FUNCTIONS
    function openAddModuleModal() {
        document.getElementById('addModuleModal').classList.add('active');
    }
    
    function openAddLessonModal(moduleId) {
        document.getElementById('addLessonModuleId').value = moduleId;
        document.getElementById('addLessonModal').classList.add('active');
    }
    
    function editModule(id, title) {
        document.getElementById('editModuleId').value = id;
        document.getElementById('editModuleTitle').value = title;
        document.getElementById('editModuleModal').classList.add('active');
    }
    
    function editLesson(id, moduleId, title) {
        document.getElementById('editLessonId').value = id;
        document.getElementById('editLessonTitle').value = title;
        document.getElementById('editLessonModal').classList.add('active');
    }
    
    async function openContentModal(lessonId, lessonTitle) {
        currentLessonId = lessonId;
        document.getElementById('contentLessonTitle').textContent = lessonTitle;
        
        // Load existing content
        try {
            const response = await fetch(`/api/internship-content.php?action=get&lesson_id=${lessonId}`);
            const result = await response.json();
            
            if (result.success && result.data.length > 0) {
                document.getElementById('contentEditor').innerHTML = result.data[0].content || '';
            } else {
                document.getElementById('contentEditor').innerHTML = '';
            }
        } catch (error) {
            console.error('Error loading content:', error);
        }
        
        document.getElementById('contentModal').classList.add('active');
    }
    
    function closeModal(modalId) {
        document.getElementById(modalId).classList.remove('active');
    }
    
    // CRUD FUNCTIONS
    async function handleAddModule(e) {
        e.preventDefault();
        const formData = new FormData(e.target);
        
        try {
            const response = await fetch('/api/internship-modules.php?action=create', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    internship_id: internshipId,
                    title: formData.get('title'),
                    description: formData.get('description')
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                location.reload();
            } else {
                alert('Failed: ' + result.message);
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Failed to add module');
        }
    }
    
    async function handleEditModule(e) {
        e.preventDefault();
        const formData = new FormData(e.target);
        
        try {
            const response = await fetch('/api/internship-modules.php?action=update', {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    id: formData.get('module_id'),
                    title: formData.get('title')
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                location.reload();
            } else {
                alert('Failed: ' + result.message);
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Failed to update module');
        }
    }
    
    async function deleteModule(id) {
        if (!confirm('Delete this module and all its lessons?')) return;
        
        try {
            const response = await fetch('/api/internship-modules.php?action=delete', {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id })
            });
            
            const result = await response.json();
            
            if (result.success) {
                location.reload();
            } else {
                alert('Failed: ' + result.message);
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Failed to delete module');
        }
    }
    
    async function handleAddLesson(e) {
        e.preventDefault();
        const formData = new FormData(e.target);
        
        try {
            const response = await fetch('/api/internship-lessons.php?action=create', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    module_id: formData.get('module_id'),
                    title: formData.get('title'),
                    description: formData.get('description'),
                    duration_minutes: formData.get('duration_minutes') || 0
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                location.reload();
            } else {
                alert('Failed: ' + result.message);
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Failed to add lesson');
        }
    }
    
    async function handleEditLesson(e) {
        e.preventDefault();
        const formData = new FormData(e.target);
        
        try {
            const response = await fetch('/api/internship-lessons.php?action=update', {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    id: formData.get('lesson_id'),
                    title: formData.get('title')
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                location.reload();
            } else {
                alert('Failed: ' + result.message);
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Failed to update lesson');
        }
    }
    
    async function deleteLesson(id) {
        if (!confirm('Delete this lesson and all its content?')) return;
        
        try {
            const response = await fetch('/api/internship-lessons.php?action=delete', {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id })
            });
            
            const result = await response.json();
            
            if (result.success) {
                location.reload();
            } else {
                alert('Failed: ' + result.message);
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Failed to delete lesson');
        }
    }
    
    // CONTENT EDITOR FUNCTIONS
    function execCmd(command, value = null) {
        document.execCommand(command, false, value);
    }
    
    function insertLink() {
        const url = prompt('Enter URL:');
        if (url) {
            execCmd('createLink', url);
        }
    }
    
    function showImageUploader() {
        document.getElementById('imageUploadPanel').classList.remove('hidden');
        document.getElementById('videoUploadPanel').classList.add('hidden');
    }
    
    function closeImageUploader() {
        document.getElementById('imageUploadPanel').classList.add('hidden');
        document.getElementById('localImageInput').value = '';
        document.getElementById('imageUrlInput').value = '';
        document.getElementById('imageUploadProgress').classList.add('hidden');
    }
    
    function showVideoUploader() {
        document.getElementById('videoUploadPanel').classList.remove('hidden');
        document.getElementById('imageUploadPanel').classList.add('hidden');
    }
    
    function closeVideoUploader() {
        document.getElementById('videoUploadPanel').classList.add('hidden');
        document.getElementById('localVideoInput').value = '';
        document.getElementById('videoUrlInput').value = '';
        document.getElementById('videoUploadProgress').classList.add('hidden');
    }
    
    // ✅ FIX 1: Local Image Upload - WORKING
    document.getElementById('localImageInput')?.addEventListener('change', async (e) => {
        const file = e.target.files[0];
        if (!file) return;
        
        // Increased to 1GB
        if (file.size > 1024 * 1024 * 1024) {
            alert('Image size must be less than 1GB');
            return;
        }
        
        const formData = new FormData();
        formData.append('file', file);
        
        const progressBar = document.getElementById('imageUploadProgress');
        progressBar.classList.remove('hidden');
        
        try {
            const response = await fetch('/api/upload-media.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            console.log('Image upload result:', result);
            
            if (result.success) {
                const imageUrl = result.data.url || result.data.file_path;
                const img = `<img src="${imageUrl}" alt="Uploaded image" style="max-width: 100%; border-radius: 8px; margin: 10px 0;">`;
                
                // Insert at cursor position
                const editor = document.getElementById('contentEditor');
                editor.focus();
                document.execCommand('insertHTML', false, img);
                
                closeImageUploader();
                alert('✅ Image uploaded successfully!');
            } else {
                alert('❌ Upload failed: ' + (result.error || result.message || 'Unknown error'));
            }
        } catch (error) {
            console.error('Upload error:', error);
            alert('❌ Upload failed: ' + error.message);
        } finally {
            progressBar.classList.add('hidden');
        }
    });
    
    // ✅ FIX 2: Insert Image from URL - WORKING
    function insertImageFromUrl() {
        const url = document.getElementById('imageUrlInput').value.trim();
        
        if (!url) {
            alert('⚠️ Please enter an image URL');
            return;
        }
        
        // Validate URL format
        try {
            new URL(url);
        } catch (e) {
            alert('⚠️ Invalid URL format');
            return;
        }
        
        const img = `<img src="${url}" alt="Online image" style="max-width: 100%; border-radius: 8px; margin: 10px 0;">`;
        
        const editor = document.getElementById('contentEditor');
        editor.focus();
        document.execCommand('insertHTML', false, img);
        
        closeImageUploader();
        alert('✅ Image inserted successfully!');
    }
    
    // ✅ FIX 3: Local Video Upload - WORKING (1GB limit)
    document.getElementById('localVideoInput')?.addEventListener('change', async (e) => {
        const file = e.target.files[0];
        if (!file) return;
        
        // Increased to 1GB
        if (file.size > 1024 * 1024 * 1024) {
            alert('Video size must be less than 1GB');
            return;
        }
        
        const formData = new FormData();
        formData.append('file', file);
        
        const progressBar = document.getElementById('videoUploadProgress');
        progressBar.classList.remove('hidden');
        
        try {
            const response = await fetch('/api/upload-media.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            console.log('Video upload result:', result);
            
            if (result.success) {
                const videoUrl = result.data.url || result.data.file_path;
                const video = `<video controls style="max-width: 100%; border-radius: 12px; margin: 20px 0;"><source src="${videoUrl}" type="video/mp4">Your browser does not support the video tag.</video>`;
                
                const editor = document.getElementById('contentEditor');
                editor.focus();
                document.execCommand('insertHTML', false, video);
                
                closeVideoUploader();
                alert('✅ Video uploaded successfully!');
            } else {
                alert('❌ Upload failed: ' + (result.error || result.message || 'Unknown error'));
            }
        } catch (error) {
            console.error('Upload error:', error);
            alert('❌ Upload failed: ' + error.message);
        } finally {
            progressBar.classList.add('hidden');
        }
    });
    
    // ✅ FIX 4: Insert Video from URL - WORKING (YouTube, Vimeo, Direct)
    function insertVideoFromUrl() {
        const url = document.getElementById('videoUrlInput').value.trim();
        
        if (!url) {
            alert('⚠️ Please enter a video URL');
            return;
        }
        
        let embedCode = '';
        
        try {
            // YouTube
            if (url.includes('youtube.com') || url.includes('youtu.be')) {
                let videoId = '';
                
                if (url.includes('v=')) {
                    // https://www.youtube.com/watch?v=VIDEO_ID
                    videoId = url.split('v=')[1].split('&')[0];
                } else if (url.includes('youtu.be/')) {
                    // https://youtu.be/VIDEO_ID
                    videoId = url.split('youtu.be/')[1].split('?')[0];
                } else if (url.includes('embed/')) {
                    // https://www.youtube.com/embed/VIDEO_ID
                    videoId = url.split('embed/')[1].split('?')[0];
                }
                
                if (videoId) {
                    embedCode = `<div style="position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden; max-width: 100%; margin: 20px 0; border-radius: 12px;"><iframe src="https://www.youtube.com/embed/${videoId}" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: 0; border-radius: 12px;" allowfullscreen></iframe></div>`;
                } else {
                    alert('⚠️ Invalid YouTube URL');
                    return;
                }
            }
            // Vimeo
            else if (url.includes('vimeo.com')) {
                let videoId = url.split('vimeo.com/')[1];
                if (videoId) {
                    videoId = videoId.split('/')[0].split('?')[0];
                    embedCode = `<div style="position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden; max-width: 100%; margin: 20px 0; border-radius: 12px;"><iframe src="https://player.vimeo.com/video/${videoId}" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: 0; border-radius: 12px;" allowfullscreen></iframe></div>`;
                } else {
                    alert('⚠️ Invalid Vimeo URL');
                    return;
                }
            }
            // Direct video URL
            else {
                // Validate URL
                new URL(url);
                embedCode = `<video controls style="max-width: 100%; border-radius: 12px; margin: 20px 0;"><source src="${url}" type="video/mp4">Your browser does not support the video tag.</video>`;
            }
            
            const editor = document.getElementById('contentEditor');
            editor.focus();
            document.execCommand('insertHTML', false, embedCode);
            
            closeVideoUploader();
            alert('✅ Video inserted successfully!');
            
        } catch (e) {
            alert('⚠️ Invalid URL format');
            console.error('URL error:', e);
        }
    }
    
    async function saveContent() {
        const content = document.getElementById('contentEditor').innerHTML;
        
        if (!content.trim() || content === '<br>') {
            alert('⚠️ Please add some content before saving');
            return;
        }
        
        try {
            const response = await fetch('/api/internship-content.php?action=save', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    lesson_id: currentLessonId,
                    content: content,
                    type: 'text'
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                alert('✅ Content saved successfully!');
                closeModal('contentModal');
                location.reload();
            } else {
                alert('❌ Failed: ' + result.message);
            }
        } catch (error) {
            console.error('Error:', error);
            alert('❌ Failed to save content');
        }
    }
    
    // QUIZ FUNCTIONS
    async function openQuizModal(lessonId, lessonTitle) {
        currentQuizLessonId = lessonId;
        document.getElementById('quizLessonTitle').textContent = lessonTitle;
        
        await loadQuizQuestions(lessonId);
        
        document.getElementById('quizModal').classList.add('active');
    }
    
    async function loadQuizQuestions(lessonId) {
        try {
            const response = await fetch(`/api/internship-quiz.php?action=get&lesson_id=${lessonId}`);
            const result = await response.json();
            
            const container = document.getElementById('questionsContainer');
            
            if (result.success && result.data.length > 0) {
                container.innerHTML = result.data.map((q, index) => `
                    <div class="bg-white border-2 border-gray-200 rounded-lg p-4">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <div class="flex items-center gap-2 mb-2">
                                    <span class="bg-primary-100 text-primary-700 px-2 py-1 rounded text-xs font-bold">Q${index + 1}</span>
                                    <span class="text-xs text-gray-500">${q.type.replace('_', ' ')}</span>
                                    <span class="text-xs font-semibold text-orange-600">${q.points} points</span>
                                </div>
                                <p class="text-gray-900 font-medium mb-2">${q.question}</p>
                                ${q.options ? `
                                    <div class="space-y-1 text-sm">
                                        ${JSON.parse(q.options).map((opt, i) => `
                                            <div class="flex items-center gap-2">
                                                <span class="${i + 1 == q.correct_option ? 'text-green-600 font-bold' : 'text-gray-600'}">
                                                    ${i + 1}. ${opt} ${i + 1 == q.correct_option ? '✓' : ''}
                                                </span>
                                            </div>
                                        `).join('')}
                                    </div>
                                ` : ''}
                            </div>
                            <div class="flex items-center gap-2">
                                <button onclick="editQuestion(${q.id}, '${q.question.replace(/'/g, "\\'")}', ${q.points})" class="text-blue-600 hover:text-blue-700 p-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                    </svg>
                                </button>
                                <button onclick="deleteQuestion(${q.id})" class="text-red-600 hover:text-red-700 p-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                `).join('');
            } else {
                container.innerHTML = '<div class="text-center py-8 text-gray-500">No questions yet. Add your first question!</div>';
            }
        } catch (error) {
            console.error('Error:', error);
        }
    }
    
    function openAddQuestionModal() {
        document.getElementById('addQuestionModal').classList.add('active');
    }
    
    function toggleOptionsFields() {
        const type = document.getElementById('questionType').value;
        const optionsFields = document.getElementById('optionsFields');
        const answerField = document.getElementById('answerField');
        
        if (type === 'multiple_choice') {
            optionsFields.classList.remove('hidden');
            answerField.classList.add('hidden');
        } else if (type === 'true_false') {
            optionsFields.classList.add('hidden');
            answerField.classList.add('hidden');
        } else {
            optionsFields.classList.add('hidden');
            answerField.classList.remove('hidden');
        }
    }
    
    async function handleAddQuestion(e) {
        e.preventDefault();
        const formData = new FormData(e.target);
        const type = formData.get('type');
        
        let questionData = {
            lesson_id: currentQuizLessonId,
            question: formData.get('question'),
            type: type,
            points: parseInt(formData.get('points')),
            explanation: formData.get('explanation')
        };
        
        if (type === 'multiple_choice') {
            const options = [
                formData.get('option_1'),
                formData.get('option_2'),
                formData.get('option_3'),
                formData.get('option_4')
            ].filter(opt => opt && opt.trim());
            
            questionData.options = JSON.stringify(options);
            questionData.correct_option = parseInt(formData.get('correct_option'));
        } else if (type === 'true_false') {
            questionData.options = JSON.stringify(['True', 'False']);
            questionData.correct_option = 1;
        } else {
            questionData.correct_answer = formData.get('correct_answer');
        }
        
        try {
            const response = await fetch('/api/internship-quiz.php?action=create', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(questionData)
            });
            
            const result = await response.json();
            
            if (result.success) {
                closeModal('addQuestionModal');
                loadQuizQuestions(currentQuizLessonId);
                e.target.reset();
            } else {
                alert('Failed: ' + result.message);
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Failed to add question');
        }
    }
    
    function editQuestion(id, question, points) {
        document.getElementById('editQuestionId').value = id;
        document.getElementById('editQuestionText').value = question;
        document.getElementById('editQuestionPoints').value = points;
        document.getElementById('editQuestionModal').classList.add('active');
    }
    
    async function handleEditQuestion(e) {
        e.preventDefault();
        const formData = new FormData(e.target);
        
        try {
            const response = await fetch('/api/internship-quiz.php?action=update', {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    id: formData.get('question_id'),
                    question: formData.get('question'),
                    points: parseInt(formData.get('points'))
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                closeModal('editQuestionModal');
                loadQuizQuestions(currentQuizLessonId);
            } else {
                alert('Failed: ' + result.message);
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Failed to update question');
        }
    }
    
    async function deleteQuestion(id) {
        if (!confirm('Delete this question?')) return;
        
        try {
            const response = await fetch('/api/internship-quiz.php?action=delete', {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id })
            });
            
            const result = await response.json();
            
            if (result.success) {
                loadQuizQuestions(currentQuizLessonId);
            } else {
                alert('Failed: ' + result.message);
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Failed to delete question');
        }
    }
</script>


</body>
</html>
