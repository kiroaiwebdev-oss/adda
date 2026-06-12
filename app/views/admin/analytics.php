<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

$dbConfig = require __DIR__ . '/../../config/database.php';
$dsn = sprintf("mysql:host=%s;port=%s;dbname=%s;charset=%s",
    $dbConfig['host'], $dbConfig['port'], $dbConfig['database'], $dbConfig['charset']
);
$db = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options']);

spl_autoload_register(function ($class) {
    $paths = [
        __DIR__ . '/../../core/' . $class . '.php',
        __DIR__ . '/../../models/' . $class . '.php',
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
$auth->requireAdmin();

// Get analytics data
$stats = [];

// Total users
$stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE role = 'learner'");
$stats['total_learners'] = $stmt->fetch()['count'];

// Total courses
$stmt = $db->query("SELECT COUNT(*) as count FROM courses WHERE status = 'published'");
$stats['total_courses'] = $stmt->fetch()['count'];

// Total enrollments
$stmt = $db->query("SELECT COUNT(*) as count FROM enrollments");
$stats['total_enrollments'] = $stmt->fetch()['count'];

// Total revenue
$stmt = $db->query("SELECT SUM(amount) as total FROM orders WHERE status = 'completed'");
$stats['total_revenue'] = $stmt->fetch()['total'] ?? 0;

// Completed courses
$stmt = $db->query("SELECT COUNT(*) as count FROM enrollments WHERE completed_at IS NOT NULL");
$stats['completed_enrollments'] = $stmt->fetch()['count'];

// Certificates issued
$stmt = $db->query("SELECT COUNT(*) as count FROM certificates WHERE revoked = 0");
$stats['total_certificates'] = $stmt->fetch()['count'];

// Recent enrollments (last 7 days)
$stmt = $db->query("SELECT COUNT(*) as count FROM enrollments WHERE enrolled_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
$stats['recent_enrollments'] = $stmt->fetch()['count'];

// Top courses by enrollment
$topCourses = $db->query("
    SELECT c.id, c.title, COUNT(e.id) as enrollment_count
    FROM courses c
    LEFT JOIN enrollments e ON c.id = e.course_id
    WHERE c.status = 'published'
    GROUP BY c.id
    ORDER BY enrollment_count DESC
    LIMIT 5
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics - Internship Adda Admin</title>
    <link rel="icon" type="image/png" href="https://internshipadda.com/icons.png">
    <link rel="shortcut icon" type="image/png" href="https://internshipadda.com/icons.png">
    <link rel="apple-touch-icon" href="https://internshipadda.com/icons.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        h1, h2, h3 { font-family: 'Poppins', sans-serif; }
    </style>
</head>
<body class="bg-gray-50">

<?php include __DIR__ . '/../components/sidebar-admin.php'; ?>

<div class="ml-64 min-h-screen">
    <header class="bg-white border-b border-gray-200 sticky top-0 z-30">
        <div class="px-8 py-6">
            <h1 class="text-3xl font-bold text-gray-900">Analytics Dashboard</h1>
            <p class="text-gray-600 mt-1">Platform performance and insights</p>
        </div>
    </header>

    <main class="p-8">
        <!-- Stats Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                        </svg>
                    </div>
                </div>
                <h3 class="text-3xl font-bold text-gray-900"><?php echo number_format($stats['total_learners']); ?></h3>
                <p class="text-gray-600 text-sm mt-1">Total Learners</p>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                        </svg>
                    </div>
                </div>
                <h3 class="text-3xl font-bold text-gray-900"><?php echo number_format($stats['total_courses']); ?></h3>
                <p class="text-gray-600 text-sm mt-1">Published Courses</p>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                </div>
                <h3 class="text-3xl font-bold text-gray-900"><?php echo number_format($stats['total_enrollments']); ?></h3>
                <p class="text-gray-600 text-sm mt-1">Total Enrollments</p>
                <p class="text-xs text-gray-500 mt-1">+<?php echo $stats['recent_enrollments']; ?> this week</p>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                </div>
                <h3 class="text-3xl font-bold text-gray-900">₹<?php echo number_format($stats['total_revenue'], 0); ?></h3>
                <p class="text-gray-600 text-sm mt-1">Total Revenue</p>
            </div>
        </div>

        <!-- Additional Stats -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <h3 class="text-lg font-bold text-gray-900 mb-4">Completion Rate</h3>
                <div class="space-y-4">
                    <div>
                        <div class="flex justify-between text-sm mb-2">
                            <span class="text-gray-600">Completed Courses</span>
                            <span class="font-semibold text-gray-900"><?php echo $stats['completed_enrollments']; ?> / <?php echo $stats['total_enrollments']; ?></span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <?php 
                            $completionRate = $stats['total_enrollments'] > 0 ? ($stats['completed_enrollments'] / $stats['total_enrollments']) * 100 : 0;
                            ?>
                            <div class="bg-green-600 h-2 rounded-full" style="width: <?php echo $completionRate; ?>%"></div>
                        </div>
                        <p class="text-xs text-gray-500 mt-1"><?php echo number_format($completionRate, 1); ?>% completion rate</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <h3 class="text-lg font-bold text-gray-900 mb-4">Certificates Issued</h3>
                <div class="flex items-center gap-4">
                    <div class="w-16 h-16 bg-yellow-100 rounded-lg flex items-center justify-center">
                        <svg class="w-8 h-8 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"></path>
                        </svg>
                    </div>
                    <div>
                        <h4 class="text-3xl font-bold text-gray-900"><?php echo number_format($stats['total_certificates']); ?></h4>
                        <p class="text-gray-600 text-sm">Active Certificates</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top Courses Table -->
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-bold text-gray-900">Top Courses by Enrollment</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Course</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Enrollments</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($topCourses as $course): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4">
                                    <span class="font-semibold text-gray-900"><?php echo htmlspecialchars($course['title']); ?></span>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="text-gray-900"><?php echo $course['enrollment_count']; ?> students</span>
                                </td>
                                <td class="px-6 py-4">
                                    <a href="/app/views/admin/courses/edit.php?id=<?php echo $course['id']; ?>" class="text-primary-600 hover:text-primary-700 font-semibold text-sm">
                                        View Details →
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

</body>
</html>
