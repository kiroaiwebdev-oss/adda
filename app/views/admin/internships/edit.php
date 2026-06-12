<?php
session_start();

$id = $_GET['id'] ?? null;

require_once __DIR__ . '/../../../config/database.php';

spl_autoload_register(function ($class) {
    $paths = [
        __DIR__ . '/../../../core/' . $class . '.php',
        __DIR__ . '/../../../models/' . $class . '.php',
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
$currentUser = $auth->user();

if (!$id || !is_numeric($id)) {
    header('Location: list.php?error=Invalid internship ID');
    exit;
}

$internshipModel = new Internship($db);
$internship = $internshipModel->getById($id);

if (!$internship) {
    header('Location: list.php?error=Internship not found');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Internship - Admin</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#f0fdf4', 100: '#dcfce7', 200: '#bbf7d0', 300: '#86efac',
                            400: '#4ade80', 500: '#22c55e', 600: '#16a34a', 700: '#15803d',
                        }
                    }
                }
            }
        }
    </script>
    
    <style>
        body { font-family: 'Inter', sans-serif; background: #fafafa; }
        h1, h2, h3 { font-family: 'Poppins', sans-serif; font-weight: 700; }
        .form-section {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 20px;
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
                        <a href="list.php" class="hover:text-primary-600">Internships</a>
                        <span>/</span>
                        <span>Edit</span>
                    </div>
                    <h1 class="text-3xl font-bold text-gray-900">Edit Internship</h1>
                    <p class="text-gray-600 mt-1">Update internship details</p>
                </div>
                <a href="list.php" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-6 py-3 rounded-lg font-semibold transition-colors flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                    Back to List
                </a>
            </div>
        </div>
    </header>

    <main class="p-8">
        <form id="editForm" class="max-w-5xl mx-auto">
            <input type="hidden" name="id" value="<?= $internship['id'] ?>">
            
            <!-- Basic Information -->
            <div class="form-section">
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-10 h-10 bg-primary-100 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <h2 class="text-xl font-bold text-gray-900">Basic Information</h2>
                </div>

                <div class="space-y-5">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            Internship Title <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="title" required
                               value="<?= htmlspecialchars($internship['title']) ?>"
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                               placeholder="e.g., Full Stack Web Development Internship">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            Description <span class="text-red-500">*</span>
                        </label>
                        <textarea name="description" required rows="5"
                                  class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                                  placeholder="Describe what students will learn and achieve..."><?= htmlspecialchars($internship['description']) ?></textarea>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                Category <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="category" required
                                   value="<?= htmlspecialchars($internship['category']) ?>"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                                   placeholder="e.g., Web Development">
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                Duration (weeks) <span class="text-red-500">*</span>
                            </label>
                            <input type="number" name="duration_weeks" required min="1"
                                   value="<?= $internship['duration_weeks'] ?>"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                                   placeholder="e.g., 12">
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                Skill Level <span class="text-red-500">*</span>
                            </label>
                            <select name="skill_level" required
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                                <option value="beginner" <?= $internship['skill_level'] === 'beginner' ? 'selected' : '' ?>>Beginner</option>
                                <option value="intermediate" <?= $internship['skill_level'] === 'intermediate' ? 'selected' : '' ?>>Intermediate</option>
                                <option value="advanced" <?= $internship['skill_level'] === 'advanced' ? 'selected' : '' ?>>Advanced</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
<!-- Cover Image Section -->
<div class="form-section">
    <div class="flex items-center gap-3 mb-6">
        <div class="w-10 h-10 bg-indigo-100 rounded-lg flex items-center justify-center">
            <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
            </svg>
        </div>
        <h2 class="text-xl font-bold text-gray-900">Cover Image</h2>
    </div>

    <div class="space-y-4">
        <!-- Current Cover Image Preview -->
        <?php if (!empty($internship['cover_image'])): ?>
            <div class="relative group">
                <img src="<?= htmlspecialchars($internship['cover_image']) ?>" 
                     alt="Current cover" 
                     class="w-full h-64 object-cover rounded-lg border-2 border-gray-200">
                <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-50 transition-all rounded-lg flex items-center justify-center">
                    <button type="button" onclick="removeCoverImage()" 
                            class="opacity-0 group-hover:opacity-100 bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg font-semibold transition-all">
                        <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                        </svg>
                        Remove Image
                    </button>
                </div>
            </div>
        <?php else: ?>
            <div class="h-64 bg-gradient-to-br from-primary-100 to-indigo-100 rounded-lg border-2 border-dashed border-gray-300 flex items-center justify-center">
                <div class="text-center">
                    <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                    <p class="text-gray-600 font-medium">No cover image set</p>
                </div>
            </div>
        <?php endif; ?>

        <!-- Upload Options -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- Upload from Device -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                    </svg>
                    Upload from Device
                </label>
                <input type="file" id="coverImageFile" accept="image/*" 
                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                <p class="text-xs text-gray-500 mt-1">Recommended: 1200x600px, Max 2MB</p>
            </div>

            <!-- URL Input -->
          <!-- URL Input -->
<div>
    <label class="block text-sm font-semibold text-gray-700 mb-2">
        <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path>
        </svg>
        Or Enter Image URL (Optional)
    </label>
    <input type="text" name="cover_image" id="coverImageUrl" 
           value="<?= htmlspecialchars($internship['cover_image'] ?? '') ?>"
           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
           placeholder="https://example.com/image.jpg or leave empty">
    <p class="text-xs text-gray-500 mt-1">
        <span class="font-semibold text-primary-600">💡 Tip:</span> Upload from device will auto-fill this field
    </p>
</div>

        </div>

        <!-- Upload Progress -->
        <div id="uploadProgress" class="hidden">
            <div class="bg-primary-50 border border-primary-200 rounded-lg p-4">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-sm font-semibold text-primary-700">Uploading image...</span>
                    <span id="uploadPercent" class="text-sm font-bold text-primary-600">0%</span>
                </div>
                <div class="w-full bg-primary-200 rounded-full h-2">
                    <div id="uploadBar" class="bg-primary-600 h-2 rounded-full transition-all" style="width: 0%"></div>
                </div>
            </div>
        </div>

        <!-- Preview New Upload -->
        <div id="imagePreview" class="hidden">
            <p class="text-sm font-semibold text-gray-700 mb-2">Preview:</p>
            <img id="previewImg" src="" alt="Preview" class="w-full h-64 object-cover rounded-lg border-2 border-primary-300">
        </div>
    </div>
</div>

            <!-- Pricing Section -->
            <div class="form-section">
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <h2 class="text-xl font-bold text-gray-900">Pricing & Discount</h2>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            Regular Price (₹) <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-500 font-semibold">₹</span>
                            <input type="number" name="price" required min="0" step="0.01"
                                   value="<?= $internship['price'] ?? 0 ?>"
                                   class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                                   placeholder="0.00">
                        </div>
                        <p class="text-xs text-gray-500 mt-1">Enter 0 for free internship</p>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            Discount Price (₹)
                        </label>
                        <div class="relative">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-500 font-semibold">₹</span>
                            <input type="number" name="discount_price" min="0" step="0.01"
                                   value="<?= $internship['discount_price'] ?? '' ?>"
                                   class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                                   placeholder="Optional">
                        </div>
                        <p class="text-xs text-gray-500 mt-1">Leave empty if no discount</p>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            Discount Percentage
                        </label>
                        <div class="relative">
                            <input type="number" name="discount_percentage" min="0" max="100"
                                   value="<?= $internship['discount_percentage'] ?? '' ?>"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                                   placeholder="Optional">
                            <span class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-500 font-semibold">%</span>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">Auto-calculated if discount price set</p>
                    </div>
                </div>

                <!-- Pricing Preview -->
                <div id="pricingPreview" class="mt-6 p-4 bg-primary-50 border border-primary-200 rounded-lg hidden">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600">Students will pay:</p>
                            <p class="text-2xl font-bold text-primary-600" id="finalPrice">₹0</p>
                        </div>
                        <div class="text-right" id="savingsSection" style="display: none;">
                            <p class="text-sm text-gray-600">Savings:</p>
                            <p class="text-xl font-bold text-green-600" id="savingsAmount">₹0</p>
                            <span class="text-xs font-semibold bg-green-100 text-green-700 px-2 py-1 rounded-full" id="savingsPercent"></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Enrollment Settings -->
            <div class="form-section">
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-10 h-10 bg-orange-100 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                    </div>
                    <h2 class="text-xl font-bold text-gray-900">Enrollment Settings</h2>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        Enrollment Limit (Optional)
                    </label>
                    <input type="number" name="enrollment_limit" min="1"
                           value="<?= $internship['enrollment_limit'] ?>"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                           placeholder="Leave empty for unlimited enrollments">
                    <p class="text-xs text-gray-500 mt-1">Maximum number of students who can enroll</p>
                </div>
            </div>

            <!-- Requirements & Learning -->
            <div class="form-section">
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                        </svg>
                    </div>
                    <h2 class="text-xl font-bold text-gray-900">Requirements & Learning Outcomes</h2>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            Prerequisites (One per line)
                        </label>
                        <textarea name="requirements" rows="8"
                                  class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent font-mono text-sm"
                                  placeholder="Basic HTML, CSS knowledge&#10;Understanding of JavaScript&#10;Passion for learning"><?= htmlspecialchars($internship['requirements']) ?></textarea>
                        <p class="text-xs text-gray-500 mt-1">What students need before starting</p>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                            </svg>
                            What You'll Learn (One per line)
                        </label>
                        <textarea name="what_you_learn" rows="8"
                                  class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent font-mono text-sm"
                                  placeholder="React and Vue.js frameworks&#10;Backend development with Node.js&#10;Database design and management"><?= htmlspecialchars($internship['what_you_learn']) ?></textarea>
                        <p class="text-xs text-gray-500 mt-1">Skills students will gain</p>
                    </div>
                </div>
            </div>

            <!-- Status -->
            <div class="form-section">
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <h2 class="text-xl font-bold text-gray-900">Publication Status</h2>
                </div>

                <div class="flex items-start gap-3 p-4 bg-gray-50 rounded-lg">
                    <input type="checkbox" name="is_active" id="is_active" value="1" 
                           <?= $internship['is_active'] ? 'checked' : '' ?>
                           class="w-5 h-5 text-primary-600 border-gray-300 rounded focus:ring-2 focus:ring-primary-500 mt-0.5">
                    <div>
                        <label for="is_active" class="font-semibold text-gray-900 cursor-pointer">
                            Active & Published
                        </label>
                        <p class="text-sm text-gray-600 mt-1">Make this internship visible to learners and allow enrollments</p>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex items-center gap-4 sticky bottom-0 bg-white p-6 border-t border-gray-200 rounded-xl">
                <button type="submit"
                        class="flex-1 bg-primary-600 hover:bg-primary-700 text-white px-8 py-4 rounded-lg font-bold text-lg transition-colors flex items-center justify-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    Save Changes
                </button>
                <a href="list.php"
                   class="flex-1 px-8 py-4 border-2 border-gray-300 rounded-lg font-bold text-lg text-gray-700 hover:bg-gray-50 transition-colors text-center">
                    Cancel
                </a>
            </div>
        </form>
    </main>
</div>

<script>
    // Pricing Preview Calculator
    function updatePricingPreview() {
        const price = parseFloat(document.querySelector('input[name="price"]').value) || 0;
        const discountPrice = parseFloat(document.querySelector('input[name="discount_price"]').value) || 0;
        const preview = document.getElementById('pricingPreview');
        const finalPriceEl = document.getElementById('finalPrice');
        const savingsSection = document.getElementById('savingsSection');
        const savingsAmount = document.getElementById('savingsAmount');
        const savingsPercent = document.getElementById('savingsPercent');
        
        if (price > 0) {
            preview.classList.remove('hidden');
            
            if (discountPrice > 0 && discountPrice < price) {
                const savings = price - discountPrice;
                const percent = Math.round((savings / price) * 100);
                
                finalPriceEl.textContent = '₹' + discountPrice.toFixed(2);
                savingsAmount.textContent = '₹' + savings.toFixed(2);
                savingsPercent.textContent = percent + '% OFF';
                savingsSection.style.display = 'block';
                
                // Auto-fill percentage
                document.querySelector('input[name="discount_percentage"]').value = percent;
            } else {
                finalPriceEl.textContent = '₹' + price.toFixed(2);
                savingsSection.style.display = 'none';
            }
        } else {
            preview.classList.add('hidden');
        }
    }
    
    document.querySelector('input[name="price"]').addEventListener('input', updatePricingPreview);
    document.querySelector('input[name="discount_price"]').addEventListener('input', updatePricingPreview);
    
    // Initial preview
    updatePricingPreview();
    
    // Form submission
    document.getElementById('editForm').addEventListener('submit', async (e) => {
        e.preventDefault();

        const formData = new FormData(e.target);
        const data = {
            id: parseInt(formData.get('id')),
            title: formData.get('title'),
            description: formData.get('description'),
            category: formData.get('category'),
            duration_weeks: parseInt(formData.get('duration_weeks')),
            skill_level: formData.get('skill_level'),
            price: parseFloat(formData.get('price')) || 0,
            discount_price: parseFloat(formData.get('discount_price')) || null,
            discount_percentage: parseInt(formData.get('discount_percentage')) || null,
            enrollment_limit: formData.get('enrollment_limit') ? parseInt(formData.get('enrollment_limit')) : null,
            requirements: formData.get('requirements'),
            what_you_learn: formData.get('what_you_learn'),
            is_active: formData.get('is_active') ? 1 : 0
        };

        try {
            const response = await fetch('/api/internships.php?action=update', {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });

            const result = await response.json();

            if (result.success) {
                window.location.href = 'list.php?success=Internship updated successfully!';
            } else {
                alert('Failed: ' + result.message);
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Failed to update internship');
        }
    });
    
</script>
<script>
    // Cover Image Upload Handler
    document.getElementById('coverImageFile').addEventListener('change', async (e) => {
        const file = e.target.files[0];
        if (!file) return;

        // Validate file size (2MB max)
        if (file.size > 2 * 1024 * 1024) {
            alert('File size must be less than 2MB');
            e.target.value = '';
            return;
        }

        // Validate file type
        if (!file.type.startsWith('image/')) {
            alert('Please select an image file');
            e.target.value = '';
            return;
        }

        // Show preview immediately
        const reader = new FileReader();
        reader.onload = (event) => {
            document.getElementById('previewImg').src = event.target.result;
            document.getElementById('imagePreview').classList.remove('hidden');
        };
        reader.readAsDataURL(file);

        // Upload to server
        const formData = new FormData();
        formData.append('image', file);
        formData.append('type', 'internship_cover');

        try {
            document.getElementById('uploadProgress').classList.remove('hidden');
            document.getElementById('uploadPercent').textContent = '0%';
            document.getElementById('uploadBar').style.width = '0%';
            
            // Simulate progress (you can implement real progress tracking)
            let progress = 0;
            const progressInterval = setInterval(() => {
                progress += 10;
                if (progress <= 90) {
                    document.getElementById('uploadPercent').textContent = progress + '%';
                    document.getElementById('uploadBar').style.width = progress + '%';
                }
            }, 100);

            const response = await fetch('/api/upload-image.php', {
                method: 'POST',
                body: formData
            });

            clearInterval(progressInterval);
            document.getElementById('uploadPercent').textContent = '100%';
            document.getElementById('uploadBar').style.width = '100%';

            const result = await response.json();

            if (result.success) {
                // Set the URL in the hidden input
                document.getElementById('coverImageUrl').value = result.url;
                
                // Hide progress after 500ms
                setTimeout(() => {
                    document.getElementById('uploadProgress').classList.add('hidden');
                }, 500);
                
                // Show success message
                const successMsg = document.createElement('div');
                successMsg.className = 'bg-green-50 border border-green-200 rounded-lg p-3 mt-4 flex items-center gap-2';
                successMsg.innerHTML = `
                    <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    <span class="text-green-800 font-semibold">✅ Image uploaded successfully!</span>
                `;
                document.getElementById('imagePreview').appendChild(successMsg);
                
                // Remove success message after 3 seconds
                setTimeout(() => successMsg.remove(), 3000);
            } else {
                alert('Upload failed: ' + result.message);
                document.getElementById('uploadProgress').classList.add('hidden');
            }
        } catch (error) {
            console.error('Upload error:', error);
            alert('Upload failed. Please try again.');
            document.getElementById('uploadProgress').classList.add('hidden');
        }
    });

    // Remove cover image
    function removeCoverImage() {
        if (confirm('Remove current cover image?')) {
            document.getElementById('coverImageUrl').value = '';
            location.reload();
        }
    }

    // URL input preview - ONLY when user enters URL manually
    document.getElementById('coverImageUrl').addEventListener('blur', (e) => {
        const url = e.target.value.trim();
        // Only show preview if URL starts with http/https (external URL)
        if (url && (url.startsWith('http://') || url.startsWith('https://'))) {
            const img = document.getElementById('previewImg');
            img.src = url;
            img.onerror = () => {
                alert('Failed to load image from URL. Please check the URL.');
            };
            img.onload = () => {
                document.getElementById('imagePreview').classList.remove('hidden');
            };
        }
    });

    // Pricing Preview Calculator
    function updatePricingPreview() {
        const price = parseFloat(document.querySelector('input[name="price"]').value) || 0;
        const discountPrice = parseFloat(document.querySelector('input[name="discount_price"]').value) || 0;
        const preview = document.getElementById('pricingPreview');
        const finalPriceEl = document.getElementById('finalPrice');
        const savingsSection = document.getElementById('savingsSection');
        const savingsAmount = document.getElementById('savingsAmount');
        const savingsPercent = document.getElementById('savingsPercent');
        
        if (price > 0) {
            preview.classList.remove('hidden');
            
            if (discountPrice > 0 && discountPrice < price) {
                const savings = price - discountPrice;
                const percent = Math.round((savings / price) * 100);
                
                finalPriceEl.textContent = '₹' + discountPrice.toFixed(2);
                savingsAmount.textContent = '₹' + savings.toFixed(2);
                savingsPercent.textContent = percent + '% OFF';
                savingsSection.style.display = 'block';
                
                document.querySelector('input[name="discount_percentage"]').value = percent;
            } else {
                finalPriceEl.textContent = '₹' + price.toFixed(2);
                savingsSection.style.display = 'none';
            }
        } else {
            preview.classList.add('hidden');
        }
    }
    
    document.querySelector('input[name="price"]').addEventListener('input', updatePricingPreview);
    document.querySelector('input[name="discount_price"]').addEventListener('input', updatePricingPreview);
    
    updatePricingPreview();
    
    // Form submission
    document.getElementById('editForm').addEventListener('submit', async (e) => {
        e.preventDefault();

        const formData = new FormData(e.target);
        const coverImageValue = formData.get('cover_image') ? formData.get('cover_image').trim() : null;
        
        const data = {
            id: parseInt(formData.get('id')),
            title: formData.get('title'),
            description: formData.get('description'),
            category: formData.get('category'),
            duration_weeks: parseInt(formData.get('duration_weeks')),
            skill_level: formData.get('skill_level'),
            cover_image: coverImageValue || null, // No validation, can be empty
            price: parseFloat(formData.get('price')) || 0,
            discount_price: formData.get('discount_price') ? parseFloat(formData.get('discount_price')) : null,
            discount_percentage: formData.get('discount_percentage') ? parseInt(formData.get('discount_percentage')) : null,
            enrollment_limit: formData.get('enrollment_limit') ? parseInt(formData.get('enrollment_limit')) : null,
            requirements: formData.get('requirements'),
            what_you_learn: formData.get('what_you_learn'),
            is_active: formData.get('is_active') ? 1 : 0
        };

        console.log('Sending data:', data);

        try {
            const response = await fetch('/api/internships.php?action=update', {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });

            const result = await response.json();
            console.log('Response:', result);

            if (result.success) {
                window.location.href = 'list.php?success=Internship updated successfully!';
            } else {
                alert('Failed: ' + result.message);
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Failed to update internship');
        }
    });
</script>


</body>
</html>
