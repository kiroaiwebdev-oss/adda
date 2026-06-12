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

// Get enrollment count
$stmt = $db->prepare("SELECT COUNT(*) FROM enrollments WHERE course_id = ?");
$stmt->execute([$courseId]);
$enrollmentCount = $stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Course Settings - <?php echo htmlspecialchars($course['title']); ?></title>
    
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
                        <span>Course Settings</span>
                    </div>
                    <h1 class="text-3xl font-bold text-gray-900">Course Settings</h1>
                </div>
                <div class="flex gap-3">
                    <button onclick="window.history.back()" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-6 py-3 rounded-lg font-semibold transition-colors">
                        ← Back
                    </button>
                    <a href="/app/views/admin/courses/builder.php?id=<?php echo $course['id']; ?>" class="bg-primary-600 hover:bg-primary-700 text-white px-6 py-3 rounded-lg font-semibold transition-colors">
                        📝 Course Builder
                    </a>
                </div>
            </div>
        </div>
    </header>

    <main class="p-8">
        <div class="max-w-4xl mx-auto">
            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                <div class="bg-white rounded-xl border border-gray-200 p-6">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Total Enrollments</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo $enrollmentCount; ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl border border-gray-200 p-6">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Course Price</p>
                            <p class="text-2xl font-bold text-gray-900">₹<?php echo number_format($course['price']); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl border border-gray-200 p-6">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Status</p>
                            <p class="text-2xl font-bold <?php echo $course['status'] === 'published' ? 'text-green-600' : 'text-yellow-600'; ?>">
                                <?php echo ucfirst($course['status']); ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Settings Form -->
            <div class="bg-white rounded-xl border border-gray-200 p-8">
                <div id="alertContainer" class="mb-6"></div>

                <form id="settingsForm" class="space-y-6">
                    <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">

                    <div>
                        <label for="title" class="block text-sm font-semibold text-gray-700 mb-2">Course Title *</label>
                        <input 
                            type="text" 
                            id="title" 
                            name="title" 
                            class="form-input" 
                            value="<?php echo htmlspecialchars($course['title']); ?>"
                            required
                        >
                    </div>

                    <div>
                        <label for="description" class="block text-sm font-semibold text-gray-700 mb-2">Description *</label>
                        <textarea 
                            id="description" 
                            name="description" 
                            rows="5"
                            class="form-textarea" 
                            required
                        ><?php echo htmlspecialchars($course['description']); ?></textarea>
                    </div>

                    <div class="grid grid-cols-2 gap-6">
                        <div>
                            <label for="price" class="block text-sm font-semibold text-gray-700 mb-2">Price (₹) *</label>
                            <input 
                                type="number" 
                                id="price" 
                                name="price" 
                                class="form-input" 
                                value="<?php echo $course['price']; ?>"
                                min="0"
                                step="0.01"
                                required
                            >
                        </div>

                        <div>
                            <label for="status" class="block text-sm font-semibold text-gray-700 mb-2">Status *</label>
                            <select id="status" name="status" class="form-select">
                                <option value="draft" <?php echo $course['status'] === 'draft' ? 'selected' : ''; ?>>Draft</option>
                                <option value="published" <?php echo $course['status'] === 'published' ? 'selected' : ''; ?>>Published</option>
                            </select>
                        </div>
                    </div>

                    <div class="pt-6 border-t border-gray-200">
                        <button type="submit" id="saveBtn" class="w-full bg-primary-600 hover:bg-primary-700 text-white px-6 py-3 rounded-lg font-semibold transition-colors">
                            💾 Save Settings
                        </button>
                    </div>
                </form>

                <!-- Danger Zone -->
                <div class="mt-8 pt-8 border-t-2 border-red-200">
                    <h3 class="text-lg font-bold text-red-600 mb-4">⚠️ Danger Zone</h3>
                    <p class="text-sm text-gray-600 mb-4">Permanently delete this course and all associated content.</p>
                    <button onclick="deleteCourse()" class="bg-red-600 hover:bg-red-700 text-white px-6 py-3 rounded-lg font-semibold transition-colors">
                        🗑️ Delete Course
                    </button>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
    const form = document.getElementById('settingsForm');
    const alertContainer = document.getElementById('alertContainer');
    const saveBtn = document.getElementById('saveBtn');

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        saveBtn.disabled = true;
        saveBtn.innerHTML = '⏳ Saving...';
        
        const formData = new FormData(form);
        const data = {
            course_id: formData.get('course_id'),
            title: formData.get('title'),
            description: formData.get('description'),
            price: parseFloat(formData.get('price')),
            status: formData.get('status')
        };

        try {
            const response = await fetch('/api/courses.php?action=update', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });

            const result = await response.json();

            if (result.success) {
                alertContainer.innerHTML = `<div class="bg-green-50 text-green-700 px-4 py-3 rounded-lg border border-green-200 font-semibold">✅ Settings saved successfully!</div>`;
                
                setTimeout(() => {
                    alertContainer.innerHTML = '';
                }, 3000);
            } else {
                alertContainer.innerHTML = `<div class="bg-red-50 text-red-700 px-4 py-3 rounded-lg border border-red-200 font-semibold">❌ ${result.error || 'Failed to save settings'}</div>`;
            }
        } catch (error) {
            alertContainer.innerHTML = '<div class="bg-red-50 text-red-700 px-4 py-3 rounded-lg border border-red-200 font-semibold">❌ Network error</div>';
        } finally {
            saveBtn.disabled = false;
            saveBtn.innerHTML = '💾 Save Settings';
        }
    });

    async function deleteCourse() {
        if (!confirm('⚠️ Are you sure you want to delete this course? This action cannot be undone!')) return;
        if (!confirm('🚨 This will delete ALL chapters, topics, quizzes, and enrollments. Continue?')) return;
        
        try {
            const response = await fetch('/api/courses.php?action=delete', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ course_id: <?php echo $courseId; ?> })
            });

            const result = await response.json();

            if (result.success) {
                window.location.href = '/app/views/admin/courses/list.php';
            } else {
                alert('❌ ' + (result.error || 'Failed to delete course'));
            }
        } catch (error) {
            alert('❌ Network error');
        }
    }
</script>

</body>
</html>
