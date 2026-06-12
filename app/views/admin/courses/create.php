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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Course - Admin</title>
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
    </style>
</head>
<body>

<?php include __DIR__ . '/../../../views/components/sidebar-admin.php'; ?>

<div class="ml-64 min-h-screen">
    <header class="bg-white border-b border-gray-200 sticky top-0 z-30">
        <div class="px-8 py-6">
            <div class="flex items-center gap-2 text-sm text-gray-500 mb-2">
                <a href="/admin/courses/list.php" class="hover:text-primary-600">Courses</a>
                <span>/</span>
                <span>Create New</span>
            </div>
            <h1 class="text-3xl font-bold text-gray-900">Create New Course</h1>
        </div>
    </header>

    <main class="p-8">
        <div class="max-w-3xl mx-auto">
            <div class="bg-white rounded-xl border border-gray-200 p-8">
                <div id="alertContainer" class="mb-6"></div>

                <form id="createCourseForm" class="space-y-6">
                    <div>
                        <label for="title" class="block text-sm font-semibold text-gray-700 mb-2">Course Title *</label>
                        <input 
                            type="text" 
                            id="title" 
                            name="title" 
                            class="form-input" 
                            placeholder="e.g., Full Stack Web Development"
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
                            placeholder="Describe what students will learn..."
                            required
                        ></textarea>
                    </div>

                    <div class="grid grid-cols-2 gap-6">
                        <div>
                            <label for="price" class="block text-sm font-semibold text-gray-700 mb-2">Price (₹) *</label>
                            <input 
                                type="number" 
                                id="price" 
                                name="price" 
                                class="form-input" 
                                placeholder="999"
                                min="0"
                                step="0.01"
                                required
                            >
                        </div>

                        <div>
                            <label for="status" class="block text-sm font-semibold text-gray-700 mb-2">Status *</label>
                            <select id="status" name="status" class="form-input">
                                <option value="draft">Draft</option>
                                <option value="published">Published</option>
                            </select>
                        </div>
                    </div>

                    <div class="pt-6 border-t border-gray-200 flex gap-4">
                        <button type="submit" id="createBtn" class="flex-1 bg-primary-600 hover:bg-primary-700 text-white px-6 py-3 rounded-lg font-semibold transition-colors">
                            Create Course
                        </button>
                        <a href="/admin/courses/list.php" class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 px-6 py-3 rounded-lg font-semibold transition-colors text-center">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </main>
</div>

<script>
    const form = document.getElementById('createCourseForm');
    const alertContainer = document.getElementById('alertContainer');

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const formData = new FormData(form);
        const data = {
            title: formData.get('title'),
            description: formData.get('description'),
            price: parseFloat(formData.get('price')),
            status: formData.get('status')
        };

        try {
            const response = await fetch('/api/courses.php?action=create', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });

            const result = await response.json();

            if (result.success) {
                alertContainer.innerHTML = `<div class="bg-green-50 text-green-700 px-4 py-3 rounded-lg border border-green-200">Course created successfully! Redirecting...</div>`;
                setTimeout(() => {
                    window.location.href = '/admin/courses/edit.php?id=' + result.data.course_id;
                }, 1500);
            } else {
                alertContainer.innerHTML = `<div class="bg-red-50 text-red-700 px-4 py-3 rounded-lg border border-red-200">${result.error || 'Failed to create course'}</div>`;
            }
        } catch (error) {
            alertContainer.innerHTML = '<div class="bg-red-50 text-red-700 px-4 py-3 rounded-lg border border-red-200">Network error</div>';
        }
    });
</script>

</body>
</html>
