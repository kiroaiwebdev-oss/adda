<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

// Database connection
$dbConfig = require __DIR__ . '/../../config/database.php';
$dsn = sprintf("mysql:host=%s;port=%s;dbname=%s;charset=%s",
    $dbConfig['host'], $dbConfig['port'], $dbConfig['database'], $dbConfig['charset']
);
$db = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options']);

// Autoload
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
$auth->requireAdmin();

$currentUser = $auth->user();

// ✅ FETCH REVENUE FROM BOTH TABLES
try {
    // Course revenue (from enrollments table)
    $courseRevenueQuery = "SELECT COALESCE(SUM(amount_paid), 0) FROM enrollments WHERE payment_status = 'paid'";
    $courseRevenue = $db->query($courseRevenueQuery)->fetchColumn();
    
    // Course revenue this month
    $courseRevenueMonthQuery = "SELECT COALESCE(SUM(amount_paid), 0) FROM enrollments WHERE payment_status = 'paid' AND MONTH(enrolled_at) = MONTH(CURRENT_DATE()) AND YEAR(enrolled_at) = YEAR(CURRENT_DATE())";
    $courseRevenueMonth = $db->query($courseRevenueMonthQuery)->fetchColumn();
} catch (Exception $e) {
    $courseRevenue = 0;
    $courseRevenueMonth = 0;
}

try {
    // Internship revenue (from internship_enrollments table)
    $internshipRevenueQuery = "SELECT COALESCE(SUM(payment_amount), 0) FROM internship_enrollments WHERE payment_status = 'completed'";
    $internshipRevenue = $db->query($internshipRevenueQuery)->fetchColumn();
    
    // Internship revenue this month
    $internshipRevenueMonthQuery = "SELECT COALESCE(SUM(payment_amount), 0) FROM internship_enrollments WHERE payment_status = 'completed' AND MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())";
    $internshipRevenueMonth = $db->query($internshipRevenueMonthQuery)->fetchColumn();
} catch (Exception $e) {
    $internshipRevenue = 0;
    $internshipRevenueMonth = 0;
}

// ✅ COMBINED REVENUE
$totalRevenue = floatval($courseRevenue) + floatval($internshipRevenue);
$totalRevenueMonth = floatval($courseRevenueMonth) + floatval($internshipRevenueMonth);

// ✅ FETCH INTERNSHIP STATS
try {
    $totalInternships = $db->query("SELECT COUNT(*) FROM internships")->fetchColumn();
    $activeInternships = $db->query("SELECT COUNT(*) FROM internships WHERE is_active = 1")->fetchColumn();
} catch (Exception $e) {
    $totalInternships = 0;
    $activeInternships = 0;
}

// Fetch dashboard stats
$stats = [
    'total_users' => $db->query("SELECT COUNT(*) FROM users WHERE role = 'learner'")->fetchColumn(),
    'new_users_month' => $db->query("SELECT COUNT(*) FROM users WHERE role = 'learner' AND MONTH(created_at) = MONTH(CURRENT_DATE())")->fetchColumn(),
    'total_courses' => $db->query("SELECT COUNT(*) FROM courses")->fetchColumn(),
    'published_courses' => $db->query("SELECT COUNT(*) FROM courses WHERE status = 'published'")->fetchColumn(),
    'total_internships' => $totalInternships,
    'active_internships' => $activeInternships,
    'total_enrollments' => $db->query("SELECT COUNT(*) FROM enrollments")->fetchColumn(),
    'active_learners' => $db->query("SELECT COUNT(DISTINCT user_id) FROM enrollments WHERE DATE(enrolled_at) >= DATE_SUB(CURRENT_DATE(), INTERVAL 30 DAY)")->fetchColumn(),
    'total_revenue' => $totalRevenue,
    'revenue_month' => $totalRevenueMonth,
    'course_revenue' => floatval($courseRevenue),
    'internship_revenue' => floatval($internshipRevenue),
];

// Recent users
$recentUsers = $db->query("
    SELECT id, name, email, created_at, status 
    FROM users 
    WHERE role = 'learner' 
    ORDER BY created_at DESC 
    LIMIT 5
")->fetchAll();

// Recent enrollments
$recentEnrollments = $db->query("
    SELECT e.*, u.name as user_name, c.title as course_title, e.progress as progress_percent
    FROM enrollments e 
    JOIN users u ON e.user_id = u.id 
    JOIN courses c ON e.course_id = c.id 
    ORDER BY e.enrolled_at DESC 
    LIMIT 5
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Internship Adda</title>
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
        .stat-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            padding: 24px;
            transition: all 0.3s ease;
        }
        .stat-card:hover {
            box-shadow: 0 8px 24px rgba(34, 197, 94, 0.1);
            border-color: rgba(34, 197, 94, 0.3);
            transform: translateY(-2px);
        }
        .table-container {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            overflow: hidden;
        }
    </style>
</head>
<body>

<!-- Sidebar -->
<?php include __DIR__ . '/../../views/components/sidebar-admin.php'; ?>

<!-- Main Content -->
<div class="ml-64 min-h-screen">
    <!-- Header -->
    <header class="bg-white border-b border-gray-200 sticky top-0 z-30">
        <div class="px-8 py-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Dashboard</h1>
                    <p class="text-gray-600 mt-1">Welcome back, <?php echo htmlspecialchars($currentUser['name']); ?></p>
                </div>
                <div class="flex items-center gap-4">
                    <div class="text-sm text-gray-500">
                        <?php echo date('l, F j, Y'); ?>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Content -->
    <main class="p-8">
        <!-- Stats Grid - NOW WITH 5 CARDS -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6 mb-8">
            <!-- Total Users -->
            <div class="stat-card">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                        </svg>
                    </div>
                    <span class="text-xs font-semibold text-green-600 bg-green-100 px-2 py-1 rounded-full">
                        +<?php echo $stats['new_users_month']; ?> this month
                    </span>
                </div>
                <h3 class="text-2xl font-bold text-gray-900"><?php echo number_format($stats['total_users']); ?></h3>
                <p class="text-sm text-gray-600 mt-1">Total Learners</p>
            </div>

            <!-- Total Courses -->
            <div class="stat-card">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                        </svg>
                    </div>
                    <span class="text-xs font-semibold text-purple-600 bg-purple-100 px-2 py-1 rounded-full">
                        <?php echo $stats['published_courses']; ?> published
                    </span>
                </div>
                <h3 class="text-2xl font-bold text-gray-900"><?php echo number_format($stats['total_courses']); ?></h3>
                <p class="text-sm text-gray-600 mt-1">Total Courses</p>
            </div>

            <!-- ✅ NEW: Total Internships -->
            <div class="stat-card">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-12 h-12 bg-indigo-100 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                    <span class="text-xs font-semibold text-indigo-600 bg-indigo-100 px-2 py-1 rounded-full">
                        <?php echo $stats['active_internships']; ?> active
                    </span>
                </div>
                <h3 class="text-2xl font-bold text-gray-900"><?php echo number_format($stats['total_internships']); ?></h3>
                <p class="text-sm text-gray-600 mt-1">Total Internships</p>
            </div>

            <!-- Total Enrollments -->
            <div class="stat-card">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <span class="text-xs font-semibold text-orange-600 bg-orange-100 px-2 py-1 rounded-full">
                        <?php echo $stats['active_learners']; ?> active
                    </span>
                </div>
                <h3 class="text-2xl font-bold text-gray-900"><?php echo number_format($stats['total_enrollments']); ?></h3>
                <p class="text-sm text-gray-600 mt-1">Total Enrollments</p>
            </div>

            <!-- Total Revenue -->
            <div class="stat-card">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-12 h-12 bg-primary-100 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <span class="text-xs font-semibold text-primary-600 bg-primary-100 px-2 py-1 rounded-full">
                        ₹<?php echo number_format($stats['revenue_month'], 2); ?> this month
                    </span>
                </div>
                <h3 class="text-2xl font-bold text-gray-900">₹<?php echo number_format($stats['total_revenue'], 2); ?></h3>
                <p class="text-sm text-gray-600 mt-1">Total Revenue</p>
                <!-- ✅ REVENUE BREAKDOWN -->
                <div class="mt-3 pt-3 border-t border-gray-100">
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-gray-500">📚 Courses:</span>
                        <span class="font-semibold text-purple-600">₹<?php echo number_format($stats['course_revenue'], 2); ?></span>
                    </div>
                    <div class="flex items-center justify-between text-xs mt-1">
                        <span class="text-gray-500">💼 Internships:</span>
                        <span class="font-semibold text-indigo-600">₹<?php echo number_format($stats['internship_revenue'], 2); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Recent Users -->
            <div class="table-container">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-bold text-gray-900">Recent Users</h3>
                </div>
                <div class="p-6">
                    <?php if (empty($recentUsers)): ?>
                        <p class="text-gray-500 text-center py-8">No users yet</p>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($recentUsers as $user): ?>
                                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 bg-gradient-to-br from-primary-500 to-primary-700 rounded-full flex items-center justify-center text-white font-semibold text-sm">
                                            <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <p class="font-semibold text-gray-900 text-sm"><?php echo htmlspecialchars($user['name']); ?></p>
                                            <p class="text-xs text-gray-500"><?php echo htmlspecialchars($user['email']); ?></p>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <span class="text-xs font-medium px-2 py-1 rounded-full <?php echo $user['status'] === 'active' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
                                            <?php echo ucfirst($user['status']); ?>
                                        </span>
                                        <p class="text-xs text-gray-500 mt-1"><?php echo date('M j, Y', strtotime($user['created_at'])); ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <div class="mt-4">
                        <a href="/app/views/admin/users/list.php" class="text-primary-600 hover:text-primary-700 font-semibold text-sm">View all users →</a>
                    </div>
                </div>
            </div>

            <!-- Recent Enrollments -->
            <div class="table-container">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-bold text-gray-900">Recent Enrollments</h3>
                </div>
                <div class="p-6">
                    <?php if (empty($recentEnrollments)): ?>
                        <p class="text-gray-500 text-center py-8">No enrollments yet</p>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($recentEnrollments as $enrollment): ?>
                                <div class="p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                                    <div class="flex items-start justify-between">
                                        <div>
                                            <p class="font-semibold text-gray-900 text-sm"><?php echo htmlspecialchars($enrollment['user_name']); ?></p>
                                            <p class="text-xs text-gray-600 mt-1">enrolled in</p>
                                            <p class="text-sm text-primary-600 font-medium mt-1"><?php echo htmlspecialchars($enrollment['course_title']); ?></p>
                                        </div>
                                        <div class="text-right">
                                            <div class="text-xs text-gray-500"><?php echo date('M j', strtotime($enrollment['enrolled_at'])); ?></div>
                                            <div class="mt-2 text-xs font-semibold text-primary-600"><?php echo number_format($enrollment['progress_percent'], 1); ?>%</div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <div class="mt-4">
                        <a href="/app/views/admin/analytics.php" class="text-primary-600 hover:text-primary-700 font-semibold text-sm">View analytics →</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="mt-8 grid grid-cols-1 md:grid-cols-3 gap-6">
            <a href="/app/views/admin/courses/create.php" class="stat-card hover:scale-105 transition-transform cursor-pointer">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 bg-primary-100 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                    </div>
                    <div>
                        <h4 class="font-bold text-gray-900">Create Course</h4>
                        <p class="text-sm text-gray-600">Add new course</p>
                    </div>
                </div>
            </a>

            <a href="/app/views/admin/users/list.php" class="stat-card hover:scale-105 transition-transform cursor-pointer">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                        </svg>
                    </div>
                    <div>
                        <h4 class="font-bold text-gray-900">Manage Users</h4>
                        <p class="text-sm text-gray-600">View all users</p>
                    </div>
                </div>
            </a>

            <a href="/app/views/admin/settings.php" class="stat-card hover:scale-105 transition-transform cursor-pointer">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 bg-gray-100 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                    </div>
                    <div>
                        <h4 class="font-bold text-gray-900">Settings</h4>
                        <p class="text-sm text-gray-600">Platform config</p>
                    </div>
                </div>
            </a>
        </div>
    </main>
</div>

</body>
</html>
