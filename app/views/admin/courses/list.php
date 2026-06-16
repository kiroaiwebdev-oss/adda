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

// Filters
$statusFilter = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

$courseModel = new Course($db);
$courses = $courseModel->getAll([
    'status' => $statusFilter,
    'search' => $search
]);

// Count identical titles so duplicate-looking courses can be told apart in the UI
$courseTitleCounts = [];
foreach ($courses as $__c) {
    $__t = strtolower(trim($__c['title']));
    $courseTitleCounts[$__t] = ($courseTitleCounts[$__t] ?? 0) + 1;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Courses - Admin</title>
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
        .cover-image-overlay {
            opacity: 0;
            transition: opacity 0.3s;
        }
        .course-card:hover .cover-image-overlay {
            opacity: 1;
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
                    <h1 class="text-3xl font-bold text-gray-900">Course Management</h1>
                    <p class="text-gray-600 mt-1">Create and manage all courses</p>
                </div>
                <a href="/app/views/admin/courses/create.php" class="bg-primary-600 hover:bg-primary-700 text-white px-6 py-3 rounded-lg font-semibold transition-colors flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                    Create Course
                </a>
            </div>
        </div>
    </header>

    <main class="p-8">
        <!-- Filters -->
        <div class="bg-white rounded-xl border border-gray-200 p-6 mb-6">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Search</label>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Course title..." class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Status</label>
                    <select name="status" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        <option value="">All Status</option>
                        <option value="published" <?php echo $statusFilter === 'published' ? 'selected' : ''; ?>>Published</option>
                        <option value="draft" <?php echo $statusFilter === 'draft' ? 'selected' : ''; ?>>Draft</option>
                    </select>
                </div>
                <div class="flex items-end">
                    <button type="submit" class="w-full bg-primary-600 hover:bg-primary-700 text-white px-6 py-2 rounded-lg font-semibold transition-colors">
                        Apply Filters
                    </button>
                </div>
            </form>
        </div>

        <!-- Courses Grid -->
        <?php if (empty($courses)): ?>
            <div class="bg-white rounded-xl border border-gray-200 p-12 text-center">
                <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                </svg>
                <h3 class="text-xl font-bold text-gray-900 mb-2">No courses found</h3>
                <p class="text-gray-600 mb-6">Create your first course to get started</p>
                <a href="/app/views/admin/courses/create.php" class="inline-block bg-primary-600 hover:bg-primary-700 text-white px-6 py-3 rounded-lg font-semibold transition-colors">
                    Create Course
                </a>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    <?php foreach ($courses as $course): 
        $enrollmentCount = $courseModel->getEnrollmentCount($course['id']);
        $hasCoverImage = !empty($course['cover_image']);
    ?>
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden hover:shadow-lg transition-shadow course-card">
            <!-- Cover Image Section -->
            <div class="relative h-48 group">
                <?php if ($hasCoverImage): ?>
                    <img src="<?php echo htmlspecialchars($course['cover_image']); ?>" 
                         alt="<?php echo htmlspecialchars($course['title']); ?>"
                         class="w-full h-full object-cover"
                         id="cover-img-<?php echo $course['id']; ?>">
                <?php else: ?>
                    <div class="w-full h-full bg-gradient-to-br from-primary-500 to-primary-700 flex items-center justify-center" id="cover-placeholder-<?php echo $course['id']; ?>">
                        <svg class="w-16 h-16 text-white opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                        </svg>
                    </div>
                <?php endif; ?>
                
                <!-- Hover Overlay with Actions -->
                <div class="cover-image-overlay absolute inset-0 bg-black bg-opacity-60 flex items-center justify-center gap-2">
                    <label for="upload-cover-<?php echo $course['id']; ?>" class="bg-white hover:bg-gray-100 text-gray-800 px-4 py-2 rounded-lg font-semibold cursor-pointer transition-colors flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                        <?php echo $hasCoverImage ? 'Change' : 'Upload'; ?>
                    </label>
                    <input type="file" 
                           id="upload-cover-<?php echo $course['id']; ?>" 
                           class="hidden" 
                           accept="image/jpeg,image/jpg,image/png,image/webp"
                           onchange="uploadCoverImage(<?php echo $course['id']; ?>, this)">
                    
                    <?php if ($hasCoverImage): ?>
                        <button onclick="deleteCoverImage(<?php echo $course['id']; ?>)" 
                                class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg font-semibold transition-colors flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                            </svg>
                            Delete
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <div class="p-6">
                <div class="flex items-center justify-between mb-3">
                    <span class="px-3 py-1 text-xs font-semibold rounded-full <?php echo $course['status'] === 'published' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700'; ?>">
                        <?php echo ucfirst($course['status']); ?>
                    </span>
                    <span class="text-sm font-bold text-primary-600">₹<?php echo number_format($course['price']); ?></span>
                </div>
                
                <h3 class="text-lg font-bold text-gray-900 mb-1 line-clamp-2"><?php echo $course['title']; ?></h3>
                <div class="flex items-center flex-wrap gap-2 mb-2">
                    <span class="text-xs text-gray-400 font-mono">#<?php echo (int)$course['id']; ?><?php if (!empty($course['slug'])): ?> · <?php echo htmlspecialchars($course['slug']); ?><?php endif; ?></span>
                    <?php if (($courseTitleCounts[strtolower(trim($course['title']))] ?? 0) > 1): ?>
                        <span class="text-[10px] font-bold bg-red-100 text-red-700 px-2 py-0.5 rounded-full" title="Another course has the same title">⚠ Duplicate title</span>
                    <?php endif; ?>
                </div>
                <p class="text-sm text-gray-600 mb-4 line-clamp-2"><?php echo $course['description']; ?></p>
                
                <div class="flex items-center gap-4 text-sm text-gray-500 mb-4">
                    <div class="flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                        </svg>
                        <span><?php echo $enrollmentCount; ?> enrolled</span>
                    </div>
                </div>

                <div class="flex gap-2 pt-4 border-t border-gray-200">
                    <a href="/app/views/admin/courses/view.php?id=<?php echo $course['id']; ?>" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-semibold text-sm text-center transition-colors">
                        View
                    </a>
                    <a href="/app/views/admin/courses/edit.php?id=<?php echo $course['id']; ?>" class="flex-1 bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-lg font-semibold text-sm text-center transition-colors">
                        Edit
                    </a>
                    <a href="/app/views/admin/courses/builder.php?id=<?php echo $course['id']; ?>" class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg font-semibold text-sm text-center transition-colors">
                        Builder
                    </a>
                    <button onclick="deleteCourse(<?php echo $course['id']; ?>)" class="px-4 py-2 bg-red-100 hover:bg-red-200 text-red-700 rounded-lg font-semibold text-sm transition-colors">
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
    </main>
</div>

<script>
    function uploadCoverImage(courseId, input) {
        if (!input.files || !input.files[0]) return;
        
        const file = input.files[0];
        const maxSize = 5 * 1024 * 1024; // 5MB
        
        if (file.size > maxSize) {
            alert('File size must be less than 5MB');
            input.value = '';
            return;
        }
        
        const formData = new FormData();
        formData.append('cover_image', file);
        formData.append('course_id', courseId);
        
        // Show loading state
        const overlay = input.closest('.course-card').querySelector('.cover-image-overlay');
        overlay.innerHTML = '<div class="text-white">Uploading...</div>';
        
        fetch('/api/upload-course-cover.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                // Reload page to show new image
                location.reload();
            } else {
                alert(data.error || 'Upload failed');
                location.reload();
            }
        })
        .catch(err => {
            alert('Upload failed: ' + err.message);
            location.reload();
        });
    }
    
    function deleteCoverImage(courseId) {
        if (!confirm('Delete cover image?')) return;
        
        fetch('/api/delete-course-cover.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ course_id: courseId })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert(data.error || 'Failed to delete cover image');
            }
        });
    }

    function deleteCourse(courseId) {
        if (!confirm('Delete this course permanently? This will also delete all chapters, topics, and enrollments.')) return;
        
        fetch('/api/courses.php?action=delete', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ course_id: courseId })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert(data.error || 'Failed to delete course');
            }
        });
    }
</script>

</body>
</html>
