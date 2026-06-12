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

$hasCoverImage = !empty($course['cover_image']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Course - <?php echo htmlspecialchars($course['title']); ?></title>
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
        .cover-image-overlay {
            opacity: 0;
            transition: opacity 0.3s;
        }
        .cover-image-container:hover .cover-image-overlay {
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
                    <div class="flex items-center gap-2 text-sm text-gray-500 mb-2">
                        <a href="/app/views/admin/courses/list.php" class="hover:text-primary-600">Courses</a>
                        <span>/</span>
                        <span>Edit Course</span>
                    </div>
                    <h1 class="text-3xl font-bold text-gray-900">Edit Course</h1>
                </div>
                <div class="flex gap-3">
                    <button onclick="window.history.back()" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-6 py-3 rounded-lg font-semibold transition-colors flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                        </svg>
                        Back
                    </button>
                    <a href="/app/views/admin/courses/builder.php?id=<?php echo $course['id']; ?>" class="bg-primary-600 hover:bg-primary-700 text-white px-6 py-3 rounded-lg font-semibold transition-colors flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 4a2 2 0 114 0v1a1 1 0 001 1h3a1 1 0 011 1v3a1 1 0 01-1 1h-1a2 2 0 100 4h1a1 1 0 011 1v3a1 1 0 01-1 1h-3a1 1 0 01-1-1v-1a2 2 0 10-4 0v1a1 1 0 01-1 1H7a1 1 0 01-1-1v-3a1 1 0 00-1-1H4a2 2 0 110-4h1a1 1 0 001-1V7a1 1 0 011-1h3a1 1 0 001-1V4z"></path>
                        </svg>
                        Open Builder
                    </a>
                </div>
            </div>
        </div>
    </header>

    <main class="p-8">
        <div class="max-w-3xl mx-auto space-y-6">
            
            <!-- Cover Image Section -->
            <div class="bg-white rounded-xl border border-gray-200 p-8">
                <h2 class="text-xl font-bold text-gray-900 mb-4">Course Cover Image</h2>
                <p class="text-sm text-gray-600 mb-6">Upload a cover image for your course. Recommended size: 1200x600px (JPG, PNG, WEBP, max 5MB)</p>
                
                <div class="relative cover-image-container rounded-xl overflow-hidden border-2 border-dashed border-gray-300">
                    <?php if ($hasCoverImage): ?>
                        <img src="<?php echo htmlspecialchars($course['cover_image']); ?>" 
                             alt="Cover Image"
                             class="w-full h-64 object-cover"
                             id="coverImagePreview">
                    <?php else: ?>
                        <div class="w-full h-64 bg-gradient-to-br from-primary-500 to-primary-700 flex items-center justify-center" id="coverImagePlaceholder">
                            <div class="text-center text-white">
                                <svg class="w-16 h-16 mx-auto mb-3 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                </svg>
                                <p class="font-semibold">No cover image uploaded</p>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Hover Overlay -->
                    <div class="cover-image-overlay absolute inset-0 bg-black bg-opacity-60 flex items-center justify-center gap-3">
                        <label for="uploadCoverInput" class="bg-white hover:bg-gray-100 text-gray-800 px-6 py-3 rounded-lg font-semibold cursor-pointer transition-colors flex items-center gap-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                            <?php echo $hasCoverImage ? 'Change Image' : 'Upload Image'; ?>
                        </label>
                        <input type="file" 
                               id="uploadCoverInput" 
                               class="hidden" 
                               accept="image/jpeg,image/jpg,image/png,image/webp"
                               onchange="uploadCoverImage(<?php echo $courseId; ?>, this)">
                        
                        <?php if ($hasCoverImage): ?>
                            <button onclick="deleteCoverImage(<?php echo $courseId; ?>)" 
                                    class="bg-red-600 hover:bg-red-700 text-white px-6 py-3 rounded-lg font-semibold transition-colors flex items-center gap-2">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                </svg>
                                Delete Image
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Course Details Form -->
            <div class="bg-white rounded-xl border border-gray-200 p-8">
                <h2 class="text-xl font-bold text-gray-900 mb-6">Course Details</h2>
                
                <div id="alertContainer" class="mb-6"></div>

                <form id="editCourseForm" class="space-y-6">
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
                            <select id="status" name="status" class="form-input">
                                <option value="draft" <?php echo $course['status'] === 'draft' ? 'selected' : ''; ?>>Draft</option>
                                <option value="published" <?php echo $course['status'] === 'published' ? 'selected' : ''; ?>>Published</option>
                            </select>
                        </div>
                    </div>

                    <div class="pt-6 border-t border-gray-200 flex gap-4">
                        <button type="submit" id="saveBtn" class="flex-1 bg-primary-600 hover:bg-primary-700 text-white px-6 py-3 rounded-lg font-semibold transition-colors">
                            💾 Save Changes
                        </button>
                        <button type="button" onclick="window.history.back()" class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 px-6 py-3 rounded-lg font-semibold transition-colors">
                            ❌ Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>
</div>

<script>
    const form = document.getElementById('editCourseForm');
    const alertContainer = document.getElementById('alertContainer');
    const saveBtn = document.getElementById('saveBtn');

    // Course Form Submission
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
                alertContainer.innerHTML = `<div class="bg-green-50 text-green-700 px-4 py-3 rounded-lg border border-green-200 font-semibold">✅ ${result.message || 'Course updated successfully'}</div>`;
                
                setTimeout(() => {
                    window.location.href = '/app/views/admin/courses/list.php';
                }, 1000);
            } else {
                alertContainer.innerHTML = `<div class="bg-red-50 text-red-700 px-4 py-3 rounded-lg border border-red-200 font-semibold">❌ ${result.error || 'Failed to update course'}</div>`;
                saveBtn.disabled = false;
                saveBtn.innerHTML = '💾 Save Changes';
            }
        } catch (error) {
            console.error('Error:', error);
            alertContainer.innerHTML = '<div class="bg-red-50 text-red-700 px-4 py-3 rounded-lg border border-red-200 font-semibold">❌ Network error. Please try again.</div>';
            saveBtn.disabled = false;
            saveBtn.innerHTML = '💾 Save Changes';
        }
    });

    // Upload Cover Image Function
    function uploadCoverImage(courseId, input) {
        if (!input.files || !input.files[0]) {
            alert('Please select a file');
            return;
        }
        
        const file = input.files[0];
        const maxSize = 5 * 1024 * 1024; // 5MB
        
        const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
        const fileType = file.type.toLowerCase();
        
        if (!allowedTypes.includes(fileType)) {
            alert('Invalid file type. Please upload JPG, PNG, or WEBP image');
            input.value = '';
            return;
        }
        
        if (file.size > maxSize) {
            alert('File size must be less than 5MB');
            input.value = '';
            return;
        }
        
        const formData = new FormData();
        formData.append('cover_image', file);
        formData.append('course_id', courseId);
        
        // Show loading overlay
        const container = document.querySelector('.cover-image-container');
        const overlay = container.querySelector('.cover-image-overlay');
        const originalContent = overlay.innerHTML;
        overlay.style.opacity = '1';
        overlay.innerHTML = `
            <div class="text-white text-center">
                <svg class="animate-spin h-12 w-12 mx-auto mb-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <p class="font-semibold text-lg">Uploading...</p>
            </div>
        `;
        
        fetch('/api/upload-course-cover.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.text().then(text => {
            console.log('Raw response:', text);
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('JSON parse error:', e);
                throw new Error('Invalid server response');
            }
        }))
        .then(data => {
            if (data.success) {
                alert('✅ Cover image uploaded successfully!');
                location.reload();
            } else {
                alert('❌ Upload failed: ' + (data.error || 'Unknown error'));
                overlay.innerHTML = originalContent;
                overlay.style.opacity = '';
            }
        })
        .catch(err => {
            console.error('Upload error:', err);
            alert('❌ Upload failed: ' + err.message);
            overlay.innerHTML = originalContent;
            overlay.style.opacity = '';
        });
        
        input.value = '';
    }
    
    // Delete Cover Image Function
    function deleteCoverImage(courseId) {
        if (!confirm('Are you sure you want to delete this cover image?')) return;
        
        fetch('/api/delete-course-cover.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ course_id: courseId })
        })
        .then(response => response.text().then(text => {
            console.log('Delete response:', text);
            try {
                return JSON.parse(text);
            } catch (e) {
                throw new Error('Invalid server response');
            }
        }))
        .then(data => {
            if (data.success) {
                alert('✅ Cover image deleted successfully!');
                location.reload();
            } else {
                alert('❌ Failed to delete: ' + (data.error || 'Unknown error'));
            }
        })
        .catch(err => {
            console.error('Delete error:', err);
            alert('❌ Failed to delete: ' + err.message);
        });
    }
</script>

</body>
</html>
