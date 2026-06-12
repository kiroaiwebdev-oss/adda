<?php
session_start();
require_once __DIR__ . '/../../../config/database.php';

$dbConfig = require __DIR__ . '/../../../config/database.php';
$dsn = sprintf("mysql:host=%s;port=%s;dbname=%s;charset=%s",
    $dbConfig['host'], $dbConfig['port'], $dbConfig['database'], $dbConfig['charset']
);
$db = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options']);

// Autoload
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

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Search & Filter
$search = $_GET['search'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$courseFilter = $_GET['course'] ?? '';

// Build query
$where = [];
$params = [];

if ($search) {
    $where[] = "(u.name LIKE ? OR u.email LIKE ? OR c.title LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($statusFilter) {
    $where[] = "e.status = ?";
    $params[] = $statusFilter;
}

if ($courseFilter) {
    $where[] = "e.course_id = ?";
    $params[] = $courseFilter;
}

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Get total count
$countSQL = "SELECT COUNT(*) FROM enrollments e 
             JOIN users u ON e.user_id = u.id 
             JOIN courses c ON e.course_id = c.id 
             $whereSQL";
$stmt = $db->prepare($countSQL);
$stmt->execute($params);
$totalEnrollments = $stmt->fetchColumn();
$totalPages = ceil($totalEnrollments / $perPage);

// Get enrollments
$sql = "SELECT e.*, 
               u.name as user_name, 
               u.email as user_email,
               c.title as course_title,
               c.price as course_price
        FROM enrollments e
        JOIN users u ON e.user_id = u.id
        JOIN courses c ON e.course_id = c.id
        $whereSQL
        ORDER BY e.enrolled_at DESC
        LIMIT $perPage OFFSET $offset";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$enrollments = $stmt->fetchAll();

// Get courses for filter dropdown
$courses = $db->query("SELECT id, title FROM courses ORDER BY title")->fetchAll();

// Calculate stats
$stats = [
    'total' => $db->query("SELECT COUNT(*) FROM enrollments")->fetchColumn(),
    'active' => $db->query("SELECT COUNT(*) FROM enrollments WHERE status = 'active'")->fetchColumn(),
    'total_revenue' => $db->query("SELECT COALESCE(SUM(amount_paid), 0) FROM enrollments WHERE payment_status = 'paid'")->fetchColumn(),
    'this_month' => $db->query("SELECT COUNT(*) FROM enrollments WHERE MONTH(enrolled_at) = MONTH(CURRENT_DATE()) AND YEAR(enrolled_at) = YEAR(CURRENT_DATE())")->fetchColumn(),
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Course Enrollments - Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {'50':'#f0fdf4','100':'#dcfce7','200':'#bbf7d0','300':'#86efac','400':'#4ade80','500':'#22c55e','600':'#16a34a','700':'#15803d','800':'#166534','900':'#14532d'}
                    }
                }
            }
        }
    </script>
    <style>
        body { font-family: 'Inter', sans-serif; background: #fafafa; }
        h1, h2, h3 { font-family: 'Poppins', sans-serif; }
    </style>
</head>
<body>

<?php include __DIR__ . '/../../../views/components/sidebar-admin.php'; ?>

<div class="ml-64 min-h-screen">
    <header class="bg-white border-b border-gray-200 sticky top-0 z-30">
        <div class="px-8 py-6">
            <h1 class="text-3xl font-bold text-gray-900">📚 Course Enrollments</h1>
            <p class="text-gray-600 mt-1">Manage all course enrollments</p>
        </div>
    </header>

    <main class="p-8">
        <!-- Stats -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600">Total Enrollments</p>
                        <p class="text-2xl font-bold text-gray-900 mt-1"><?php echo number_format($stats['total']); ?></p>
                    </div>
                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600">Active Students</p>
                        <p class="text-2xl font-bold text-green-600 mt-1"><?php echo number_format($stats['active']); ?></p>
                    </div>
                    <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600">Total Revenue</p>
                        <p class="text-2xl font-bold text-primary-600 mt-1">₹<?php echo number_format($stats['total_revenue'], 2); ?></p>
                    </div>
                    <div class="w-12 h-12 bg-primary-100 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600">This Month</p>
                        <p class="text-2xl font-bold text-orange-600 mt-1"><?php echo number_format($stats['this_month']); ?></p>
                    </div>
                    <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-xl border border-gray-200 p-6 mb-6">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Search</label>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                           placeholder="Name, email, course..." 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Course</label>
                    <select name="course" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                        <option value="">All Courses</option>
                        <?php foreach ($courses as $course): ?>
                            <option value="<?php echo $course['id']; ?>" <?php echo $courseFilter == $course['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($course['title']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Status</label>
                    <select name="status" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                        <option value="">All Status</option>
                        <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="completed" <?php echo $statusFilter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="suspended" <?php echo $statusFilter === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                    </select>
                </div>

                <div class="flex items-end gap-2">
                    <button type="submit" class="flex-1 bg-primary-600 hover:bg-primary-700 text-white px-6 py-2 rounded-lg font-semibold transition-colors">
                        Filter
                    </button>
                    <a href="?" class="px-6 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                        Reset
                    </a>
                </div>
            </form>
        </div>

        <!-- Table -->
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">ID</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">Student</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">Course</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">Progress</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">Amount Paid</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">Payment</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">Status</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">Enrolled</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php if (empty($enrollments)): ?>
                            <tr>
                                <td colspan="8" class="px-6 py-12 text-center text-gray-500">No enrollments found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($enrollments as $enrollment): ?>
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-6 py-4 text-sm font-mono text-gray-900">#<?php echo $enrollment['id']; ?></td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="w-10 h-10 bg-gradient-to-br from-primary-500 to-primary-700 rounded-full flex items-center justify-center text-white font-semibold text-sm">
                                                <?php echo strtoupper(substr($enrollment['user_name'], 0, 1)); ?>
                                            </div>
                                            <div>
                                                <p class="font-semibold text-gray-900 text-sm"><?php echo htmlspecialchars($enrollment['user_name']); ?></p>
                                                <p class="text-xs text-gray-500"><?php echo htmlspecialchars($enrollment['user_email']); ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <p class="font-medium text-gray-900 text-sm"><?php echo htmlspecialchars($enrollment['course_title']); ?></p>
                                        <p class="text-xs text-gray-500">₹<?php echo number_format($enrollment['course_price'], 2); ?></p>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="w-full bg-gray-200 rounded-full h-2">
                                            <div class="bg-primary-600 h-2 rounded-full" style="width: <?php echo $enrollment['progress'] ?? 0; ?>%"></div>
                                        </div>
                                        <p class="text-xs text-gray-600 mt-1"><?php echo number_format($enrollment['progress'] ?? 0, 1); ?>%</p>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="font-bold text-primary-600">₹<?php echo number_format($enrollment['amount_paid'] ?? 0, 2); ?></span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?php
                                        $paymentStatus = $enrollment['payment_status'] ?? 'pending';
                                        $badgeColors = [
                                            'paid' => 'bg-green-100 text-green-700',
                                            'pending' => 'bg-yellow-100 text-yellow-700',
                                            'failed' => 'bg-red-100 text-red-700',
                                        ];
                                        ?>
                                        <span class="px-3 py-1 rounded-full text-xs font-semibold <?php echo $badgeColors[$paymentStatus] ?? 'bg-gray-100 text-gray-700'; ?>">
                                            <?php echo ucfirst($paymentStatus); ?>
                                        </span>
                                        <?php if (!empty($enrollment['payment_method'])): ?>
                                            <p class="text-xs text-gray-500 mt-1"><?php echo ucfirst($enrollment['payment_method']); ?></p>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?php
                                        $status = $enrollment['status'] ?? 'active';
                                        $statusColors = [
                                            'active' => 'bg-green-100 text-green-700',
                                            'completed' => 'bg-blue-100 text-blue-700',
                                            'suspended' => 'bg-red-100 text-red-700',
                                        ];
                                        ?>
                                        <span class="px-3 py-1 rounded-full text-xs font-semibold <?php echo $statusColors[$status] ?? 'bg-gray-100 text-gray-700'; ?>">
                                            <?php echo ucfirst($status); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500">
                                        <?php echo date('M j, Y', strtotime($enrollment['enrolled_at'])); ?>
                                        <br><span class="text-xs text-gray-400"><?php echo date('g:i A', strtotime($enrollment['enrolled_at'])); ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="px-6 py-4 border-t border-gray-200 flex items-center justify-between">
                    <p class="text-sm text-gray-600">
                        Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $perPage, $totalEnrollments); ?> of <?php echo $totalEnrollments; ?> enrollments
                    </p>
                    <div class="flex gap-2">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($statusFilter); ?>&course=<?php echo urlencode($courseFilter); ?>" 
                               class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">Previous</a>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                            <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($statusFilter); ?>&course=<?php echo urlencode($courseFilter); ?>" 
                               class="px-4 py-2 border rounded-lg <?php echo $i === $page ? 'bg-primary-600 text-white border-primary-600' : 'border-gray-300 hover:bg-gray-50'; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($statusFilter); ?>&course=<?php echo urlencode($courseFilter); ?>" 
                               class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">Next</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

</body>
</html>
