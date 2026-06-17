<?php
session_start();
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

// Load models
$internshipModel = new Internship($db);
$internships = $internshipModel->getAll();

// Calculate stats
foreach ($internships as &$internship) {
    try {
        $internship['enrollment_count'] = $internshipModel->getEnrollmentCount($internship['id']);
        $internship['stats'] = $internshipModel->getStats($internship['id']);
    } catch (Exception $e) {
        $internship['enrollment_count'] = 0;
        $internship['stats'] = ['total_enrolled' => 0, 'completed' => 0, 'active' => 0];
    }
}
unset($internship); // IMPORTANT: break the by-reference binding from the loop
                    // above, otherwise the later `foreach ($internships as $internship)`
                    // corrupts the last element and renders a duplicate card.

// Overall statistics
$totalInternships = count($internships);
$activePrograms = count(array_filter($internships, fn($i) => $i['is_active']));
$totalEnrollments = array_sum(array_column($internships, 'enrollment_count'));
$completedCount = array_sum(array_column(array_column($internships, 'stats'), 'completed'));

// Get search/filter
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';

if ($search) {
    $internships = array_filter($internships, fn($i) => 
        stripos($i['title'], $search) !== false || 
        stripos($i['category'], $search) !== false
    );
}

if ($status && $status !== 'all') {
    $internships = array_filter($internships, fn($i) => 
        ($status === 'published' && $i['is_active']) ||
        ($status === 'draft' && !$i['is_active'])
    );
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Internships - Internship Adda</title>
    <link rel="icon" type="image/png" href="https://internshipadda.com/icons.png">
    <link rel="shortcut icon" type="image/png" href="https://internshipadda.com/icons.png">
    <link rel="apple-touch-icon" href="https://internshipadda.com/icons.png">
    
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
        .internship-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            transition: all 0.3s ease;
            overflow: hidden;
        }
        .internship-card:hover {
            box-shadow: 0 8px 24px rgba(34, 197, 94, 0.15);
            border-color: #22c55e;
            transform: translateY(-4px);
        }
        .stat-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            padding: 24px;
            transition: all 0.3s ease;
        }
        .stat-card:hover {
            box-shadow: 0 8px 24px rgba(34, 197, 94, 0.1);
            transform: translateY(-2px);
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
                <a href="create.php" class="bg-primary-600 hover:bg-primary-700 text-white px-6 py-3 rounded-lg font-semibold transition-colors flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                    Create Internship
                </a>
            </div>
        </div>
    </header>

    <main class="p-8">
        <!-- Success/Error Messages -->
        <?php if (isset($_GET['success'])): ?>
            <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6 flex items-center gap-3">
                <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <p class="text-green-800 font-medium"><?= htmlspecialchars($_GET['success']) ?></p>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
            <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6 flex items-center gap-3">
                <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <p class="text-red-800 font-medium"><?= htmlspecialchars($_GET['error']) ?></p>
            </div>
        <?php endif; ?>

        <!-- Stats Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="stat-card">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                </div>
                <h3 class="text-2xl font-bold text-gray-900"><?= $totalInternships ?></h3>
                <p class="text-sm text-gray-600 mt-1">Total Internships</p>
            </div>

            <div class="stat-card">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-12 h-12 bg-primary-100 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <span class="text-xs font-semibold text-primary-600 bg-primary-100 px-2 py-1 rounded-full">
                        <?= $totalInternships > 0 ? round(($activePrograms/$totalInternships)*100) : 0 ?>% Active
                    </span>
                </div>
                <h3 class="text-2xl font-bold text-gray-900"><?= $activePrograms ?></h3>
                <p class="text-sm text-gray-600 mt-1">Active Programs</p>
            </div>

            <div class="stat-card">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                    </div>
                </div>
                <h3 class="text-2xl font-bold text-gray-900"><?= $totalEnrollments ?></h3>
                <p class="text-sm text-gray-600 mt-1">Total Enrollments</p>
            </div>

            <div class="stat-card">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z"></path>
                        </svg>
                    </div>
                </div>
                <h3 class="text-2xl font-bold text-gray-900"><?= $completedCount ?></h3>
                <p class="text-sm text-gray-600 mt-1">Completed</p>
            </div>
        </div>

        <!-- Search and Filter -->
        <div class="bg-white rounded-xl border border-gray-200 p-6 mb-6">
            <form method="GET" class="flex flex-wrap gap-4">
                <div class="flex-1 min-w-[250px]">
                    <input type="search" name="search" value="<?= htmlspecialchars($search) ?>" 
                           placeholder="Course title..." 
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                </div>
                <div class="min-w-[200px]">
                    <select name="status" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                        <option value="">All Status</option>
                        <option value="published" <?= $status === 'published' ? 'selected' : '' ?>>Published</option>
                        <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>Draft</option>
                    </select>
                </div>
                <button type="submit" class="bg-primary-600 hover:bg-primary-700 text-white px-8 py-3 rounded-lg font-semibold transition-colors">
                    Apply Filters
                </button>
                <?php if ($search || $status): ?>
                    <a href="list.php" class="px-8 py-3 border border-gray-300 rounded-lg font-semibold text-gray-700 hover:bg-gray-50 transition-colors">
                        Clear
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Internships Grid -->
        <?php if (empty($internships)): ?>
            <div class="bg-white rounded-2xl border-2 border-dashed border-gray-300 p-12 text-center">
                <div class="w-24 h-24 bg-primary-100 rounded-full flex items-center justify-center mx-auto mb-6">
                    <svg class="w-12 h-12 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
                    </svg>
                </div>
                <h3 class="text-2xl font-bold text-gray-900 mb-3">No Internships Found</h3>
                <p class="text-gray-600 mb-8 text-lg">Get started by creating your first internship program</p>
                <a href="create.php" class="inline-block bg-primary-600 hover:bg-primary-700 text-white px-8 py-4 rounded-lg font-semibold transition-colors">
                    Create First Internship
                </a>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php
                $internshipTitleCounts = [];
                foreach ($internships as $__i) { $__t = strtolower(trim($__i['title'])); $internshipTitleCounts[$__t] = ($internshipTitleCounts[$__t] ?? 0) + 1; }
                ?>
                <?php foreach ($internships as $internship): ?>
                    <div class="internship-card">
                        <!-- Cover Image -->
                        <div class="h-48 bg-gradient-to-br from-primary-500 to-primary-700 flex items-center justify-center">
                            <?php if (!empty($internship['cover_image'])): ?>
                                <img src="<?= htmlspecialchars($internship['cover_image']) ?>" 
                                     alt="<?= htmlspecialchars($internship['title']) ?>"
                                     class="w-full h-full object-cover">
                            <?php else: ?>
                                <svg class="w-20 h-20 text-white opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                </svg>
                            <?php endif; ?>
                        </div>
<!-- Content -->
<div class="p-6">
    <!-- Status Badge -->
    <div class="flex items-center justify-between mb-3">
        <span class="text-xs px-3 py-1 rounded-full font-semibold <?= $internship['is_active'] ? 'bg-green-100 text-green-700' : 'bg-gray-200 text-gray-700' ?>">
            <?= $internship['is_active'] ? 'Published' : 'Draft' ?>
        </span>
        <span class="text-xs px-2 py-1 rounded-md bg-primary-100 text-primary-700 font-medium">
            <?= htmlspecialchars($internship['category']) ?>
        </span>
    </div>

    <!-- Title -->
    <h3 class="text-lg font-bold text-gray-900 mb-1 line-clamp-2">
        <?= htmlspecialchars($internship['title']) ?>
    </h3>
    <div class="flex items-center flex-wrap gap-2 mb-2">
        <span class="text-xs text-gray-400 font-mono">#<?= (int)$internship['id'] ?></span>
        <?php if (($internshipTitleCounts[strtolower(trim($internship['title']))] ?? 0) > 1): ?>
            <span class="text-[10px] font-bold bg-red-100 text-red-700 px-2 py-0.5 rounded-full" title="Another internship has the same title">⚠ Duplicate title</span>
        <?php endif; ?>
    </div>

    <!-- Meta Info -->
    <div class="flex items-center gap-4 text-sm text-gray-600 mb-4">
        <span class="flex items-center gap-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
            </svg>
            <?= $internship['enrollment_count'] ?> enrolled
        </span>
        <span class="capitalize"><?= htmlspecialchars($internship['skill_level']) ?></span>
    </div>

    <!-- Price/Duration Section -->
    <div class="flex items-center justify-between mb-4 pb-4 border-b border-gray-200">
        <div class="flex items-center gap-2 text-sm text-gray-600">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <?= $internship['duration_weeks'] ?> weeks
        </div>
        <div class="text-right">
            <?php if (isset($internship['price']) && $internship['price'] > 0): ?>
                <?php if (isset($internship['discount_price']) && $internship['discount_price'] > 0 && $internship['discount_price'] < $internship['price']): ?>
                    <!-- Discounted Price -->
                    <div class="flex items-center gap-2">
                        <span class="text-lg font-bold text-primary-600">₹<?= number_format($internship['discount_price']) ?></span>
                        <span class="text-sm text-gray-500 line-through">₹<?= number_format($internship['price']) ?></span>
                    </div>
                    <span class="inline-block text-xs font-bold bg-red-100 text-red-600 px-2 py-0.5 rounded-full mt-1">
                        <?php 
                            $discount = round((($internship['price'] - $internship['discount_price']) / $internship['price']) * 100);
                            echo $discount . '% OFF';
                        ?>
                    </span>
                <?php else: ?>
                    <!-- Regular Price -->
                    <span class="text-lg font-bold text-primary-600">₹<?= number_format($internship['price']) ?></span>
                <?php endif; ?>
            <?php else: ?>
                <!-- Free -->
                <span class="text-lg font-bold text-green-600">FREE</span>
            <?php endif; ?>
        </div>
    </div>

    <!-- Action Buttons -->
    <div class="grid grid-cols-4 gap-2">
        <a href="view.php?id=<?= $internship['id'] ?>" 
           class="flex items-center justify-center bg-blue-50 hover:bg-blue-100 text-blue-700 p-3 rounded-lg transition-colors"
           title="View">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
            </svg>
        </a>
        <a href="edit.php?id=<?= $internship['id'] ?>" 
           class="flex items-center justify-center bg-green-50 hover:bg-green-100 text-green-700 p-3 rounded-lg transition-colors"
           title="Edit">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
            </svg>
        </a>
        <a href="builder.php?id=<?= $internship['id'] ?>" 
           class="flex items-center justify-center bg-purple-50 hover:bg-purple-100 text-purple-700 p-3 rounded-lg transition-colors"
           title="Builder">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
            </svg>
        </a>
        <button onclick="deleteInternship(<?= $internship['id'] ?>)" 
                class="flex items-center justify-center bg-red-50 hover:bg-red-100 text-red-700 p-3 rounded-lg transition-colors"
                title="Delete">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
    async function deleteInternship(id) {
        if (!confirm('Are you sure you want to delete this internship? This action cannot be undone.')) {
            return;
        }

        try {
            const response = await fetch('/api/internships.php?action=delete', {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id })
            });

            const result = await response.json();
            
            if (result.success) {
                window.location.href = 'list.php?success=Internship deleted successfully';
            } else {
                alert('Failed: ' + result.message);
            }
        } catch (error) {
            alert('Network error');
        }
    }
</script>

</body>
</html>
