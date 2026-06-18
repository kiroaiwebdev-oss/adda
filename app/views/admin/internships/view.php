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

// Get stats
$stats = $internshipModel->getStats($id);
$enrollmentCount = $internshipModel->getEnrollmentCount($id);
$isFull = $internshipModel->isEnrollmentFull($id);

// Parse requirements and learning outcomes
$requirements = array_filter(explode("\n", $internship['requirements'] ?? ''));
$learningOutcomes = array_filter(explode("\n", $internship['what_you_learn'] ?? ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($internship['title']) ?> - View Internship</title>
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
        .info-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            padding: 24px;
            transition: all 0.3s ease;
        }
        .info-card:hover {
            box-shadow: 0 8px 24px rgba(34, 197, 94, 0.1);
        }
        .stat-box {
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
            border: 2px solid #86efac;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/../../components/sidebar-admin.php'; ?>

<div class="ml-64 min-h-screen">
    <!-- Header with Cover Image -->
    <div class="relative h-80 bg-gradient-to-br from-primary-500 to-primary-700 overflow-hidden">
        <?php if (!empty($internship['cover_image'])): ?>
            <img src="<?= htmlspecialchars($internship['cover_image']) ?>" 
                 alt="<?= htmlspecialchars($internship['title']) ?>"
                 class="w-full h-full object-cover opacity-80">
            <div class="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent"></div>
        <?php else: ?>
            <div class="absolute inset-0 flex items-center justify-center">
                <svg class="w-32 h-32 text-white opacity-20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                </svg>
            </div>
        <?php endif; ?>
        
        <!-- Header Content -->
        <div class="absolute bottom-0 left-0 right-0 p-8 text-white">
            <div class="flex items-center gap-3 mb-4">
                <span class="text-xs px-3 py-1 rounded-full font-semibold <?= $internship['is_active'] ? 'bg-green-500' : 'bg-gray-500' ?>">
                    <?= $internship['is_active'] ? 'Active' : 'Draft' ?>
                </span>
                <span class="text-xs px-3 py-1 rounded-full bg-white/20 backdrop-blur-md font-semibold">
                    <?= htmlspecialchars($internship['category']) ?>
                </span>
                <span class="text-xs px-3 py-1 rounded-full bg-white/20 backdrop-blur-md font-semibold capitalize">
                    <?= htmlspecialchars($internship['skill_level']) ?>
                </span>
            </div>
            <h1 class="text-4xl font-bold mb-2"><?= htmlspecialchars($internship['title']) ?></h1>
            <p class="text-white/90 text-lg">ID: #<?= $internship['id'] ?> • Created: <?= date('F j, Y', strtotime($internship['created_at'])) ?></p>
        </div>
    </div>

    <!-- Action Buttons Bar -->
    <div class="sticky top-0 z-30 bg-white border-b border-gray-200 px-8 py-4">
        <div class="flex items-center justify-between">
            <a href="list.php" class="text-gray-600 hover:text-gray-900 font-semibold flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                </svg>
                Back to List
            </a>
            <div class="flex items-center gap-3">
                <a href="edit.php?id=<?= $internship['id'] ?>" 
                   class="bg-primary-600 hover:bg-primary-700 text-white px-6 py-3 rounded-lg font-semibold transition-colors flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                    </svg>
                    Edit Details
                </a>
                <a href="builder.php?id=<?= $internship['id'] ?>" 
                   class="bg-purple-600 hover:bg-purple-700 text-white px-6 py-3 rounded-lg font-semibold transition-colors flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                    </svg>
                    Open Builder
                </a>
                <button onclick="deleteInternship(<?= $internship['id'] ?>)" 
                        class="bg-red-600 hover:bg-red-700 text-white px-6 py-3 rounded-lg font-semibold transition-colors flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                    </svg>
                    Delete
                </button>
            </div>
        </div>
    </div>

    <main class="p-8">
        <div class="max-w-7xl mx-auto">
            <!-- Stats Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="stat-box">
                    <div class="flex items-center justify-center mb-3">
                        <div class="w-12 h-12 bg-primary-600 rounded-full flex items-center justify-center">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                            </svg>
                        </div>
                    </div>
                    <h3 class="text-3xl font-bold text-gray-900"><?= $enrollmentCount ?></h3>
                    <p class="text-sm text-gray-600 font-semibold mt-1">Total Enrolled</p>
                    <?php if ($internship['enrollment_limit']): ?>
                        <p class="text-xs text-gray-500 mt-1">Limit: <?= $internship['enrollment_limit'] ?></p>
                    <?php endif; ?>
                </div>

                <div class="stat-box">
                    <div class="flex items-center justify-center mb-3">
                        <div class="w-12 h-12 bg-blue-600 rounded-full flex items-center justify-center">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                            </svg>
                        </div>
                    </div>
                    <h3 class="text-3xl font-bold text-gray-900"><?= $stats['active'] ?? 0 ?></h3>
                    <p class="text-sm text-gray-600 font-semibold mt-1">Active Students</p>
                </div>

                <div class="stat-box">
                    <div class="flex items-center justify-center mb-3">
                        <div class="w-12 h-12 bg-green-600 rounded-full flex items-center justify-center">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                    </div>
                    <h3 class="text-3xl font-bold text-gray-900"><?= $stats['completed'] ?? 0 ?></h3>
                    <p class="text-sm text-gray-600 font-semibold mt-1">Completed</p>
                </div>

                <div class="stat-box">
                    <div class="flex items-center justify-center mb-3">
                        <div class="w-12 h-12 bg-orange-600 rounded-full flex items-center justify-center">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                    </div>
                    <h3 class="text-3xl font-bold text-gray-900"><?= $internship['duration_weeks'] ?></h3>
                    <p class="text-sm text-gray-600 font-semibold mt-1">Weeks Duration</p>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Main Content -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Description -->
                    <div class="info-card">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <h2 class="text-2xl font-bold text-gray-900">About This Internship</h2>
                        </div>
                        <p class="text-gray-700 leading-relaxed whitespace-pre-wrap"><?= htmlspecialchars($internship['description']) ?></p>
                    </div>

                    <!-- What You'll Learn -->
                    <?php if (!empty($learningOutcomes)): ?>
                    <div class="info-card">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center">
                                <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
                                </svg>
                            </div>
                            <h2 class="text-2xl font-bold text-gray-900">What You'll Learn</h2>
                        </div>
                        <ul class="space-y-3">
                            <?php foreach ($learningOutcomes as $outcome): ?>
                                <li class="flex items-start gap-3">
                                    <svg class="w-6 h-6 text-primary-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    <span class="text-gray-700"><?= htmlspecialchars(trim($outcome)) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>

                    <!-- Requirements -->
                    <?php if (!empty($requirements)): ?>
                    <div class="info-card">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-10 h-10 bg-orange-100 rounded-lg flex items-center justify-center">
                                <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                                </svg>
                            </div>
                            <h2 class="text-2xl font-bold text-gray-900">Prerequisites</h2>
                        </div>
                        <ul class="space-y-3">
                            <?php foreach ($requirements as $requirement): ?>
                                <li class="flex items-start gap-3">
                                    <svg class="w-6 h-6 text-orange-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                    </svg>
                                    <span class="text-gray-700"><?= htmlspecialchars(trim($requirement)) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Sidebar -->
                <div class="space-y-6">
                    <!-- Pricing Card -->
                    <div class="info-card bg-gradient-to-br from-primary-50 to-blue-50">
                        <div class="text-center mb-6">
                            <div class="inline-flex items-center justify-center w-16 h-16 bg-primary-600 rounded-full mb-4">
                                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <h3 class="text-lg font-bold text-gray-900 mb-2">Pricing</h3>
                            
                            <?php if ($internship['price'] > 0): ?>
                                <?php if (!empty($internship['discount_price']) && $internship['discount_price'] < $internship['price']): ?>
                                    <!-- Discounted Price -->
                                    <div class="mb-3">
                                        <div class="text-4xl font-bold text-primary-600 mb-1">
                                            ₹<?= number_format($internship['discount_price']) ?>
                                        </div>
                                        <div class="text-xl text-gray-500 line-through mb-2">
                                            ₹<?= number_format($internship['price']) ?>
                                        </div>
                                        <span class="inline-block bg-red-100 text-red-700 px-4 py-2 rounded-full text-sm font-bold">
                                            <?php 
                                                $discount = round((($internship['price'] - $internship['discount_price']) / $internship['price']) * 100);
                                                echo $discount . '% OFF';
                                            ?>
                                        </span>
                                    </div>
                                    <p class="text-sm text-green-700 font-semibold">
                                        💰 Save ₹<?= number_format($internship['price'] - $internship['discount_price']) ?>
                                    </p>
                                <?php else: ?>
                                    <!-- Regular Price -->
                                    <div class="text-4xl font-bold text-primary-600">
                                        ₹<?= number_format($internship['price']) ?>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <!-- Free -->
                                <div class="text-4xl font-bold text-green-600">FREE</div>
                                <p class="text-sm text-gray-600 mt-2">🎉 No cost to enroll</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Quick Info -->
                    <div class="info-card">
                        <h3 class="text-lg font-bold text-gray-900 mb-4">Quick Information</h3>
                        <div class="space-y-4">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center flex-shrink-0">
                                    <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500 font-semibold">Category</p>
                                    <p class="text-sm font-bold text-gray-900"><?= htmlspecialchars($internship['category']) ?></p>
                                </div>
                            </div>

                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center flex-shrink-0">
                                    <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500 font-semibold">Skill Level</p>
                                    <p class="text-sm font-bold text-gray-900 capitalize"><?= htmlspecialchars($internship['skill_level']) ?></p>
                                </div>
                            </div>

                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center flex-shrink-0">
                                    <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500 font-semibold">Created</p>
                                    <p class="text-sm font-bold text-gray-900"><?= date('M j, Y', strtotime($internship['created_at'])) ?></p>
                                </div>
                            </div>

                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center flex-shrink-0">
                                    <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500 font-semibold">Last Updated</p>
                                    <p class="text-sm font-bold text-gray-900"><?= date('M j, Y', strtotime($internship['updated_at'])) ?></p>
                                </div>
                            </div>

                            <?php if ($internship['enrollment_limit']): ?>
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center flex-shrink-0">
                                    <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500 font-semibold">Enrollment Limit</p>
                                    <p class="text-sm font-bold text-gray-900">
                                        <?= $enrollmentCount ?> / <?= $internship['enrollment_limit'] ?>
                                        <?php if ($isFull): ?>
                                            <span class="text-xs bg-red-100 text-red-700 px-2 py-0.5 rounded-full ml-2">FULL</span>
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Status Toggle -->
                    <div class="info-card">
                        <h3 class="text-lg font-bold text-gray-900 mb-4">Status</h3>
                        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                            <div class="flex items-center gap-3">
                                <div class="w-3 h-3 rounded-full <?= $internship['is_active'] ? 'bg-green-500' : 'bg-gray-400' ?>"></div>
                                <span class="font-semibold text-gray-900">
                                    <?= $internship['is_active'] ? 'Published' : 'Draft' ?>
                                </span>
                            </div>
                            <button onclick="toggleStatus(<?= $internship['id'] ?>)" 
                                    class="px-4 py-2 rounded-lg text-sm font-semibold transition-colors <?= $internship['is_active'] ? 'bg-yellow-100 text-yellow-700 hover:bg-yellow-200' : 'bg-green-100 text-green-700 hover:bg-green-200' ?>">
                                <?= $internship['is_active'] ? 'Unpublish' : 'Publish' ?>
                            </button>
                        </div>
                        <p class="text-xs text-gray-500 mt-3">
                            <?= $internship['is_active'] ? '✅ Visible to students' : '⚠️ Hidden from students' ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
    async function toggleStatus(id) {
        if (!confirm('Change publication status?')) return;

        try {
            const response = await fetch('/api/internships.php?action=toggle-status', {
                method: 'PUT',
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
            alert('Failed to update status');
        }
    }

    async function deleteInternship(id) {
        if (!confirm('⚠️ Are you sure you want to delete this internship?\n\nThis will also delete:\n• All modules and content\n• Student enrollments\n• Progress data\n\nThis action CANNOT be undone!')) {
            return;
        }

        // Double confirmation
        if (!confirm('🔴 FINAL WARNING: This is permanent! Delete internship?')) {
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
            console.error('Error:', error);
            alert('Failed to delete internship');
        }
    }
</script>

</body>
</html>
