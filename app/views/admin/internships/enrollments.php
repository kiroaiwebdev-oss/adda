<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

try {
    require_once __DIR__ . '/../../../config/database.php';
    
    $dbConfig = require __DIR__ . '/../../../config/database.php';
    $dsn = sprintf("mysql:host=%s;port=%s;dbname=%s;charset=%s",
        $dbConfig['host'], $dbConfig['port'], $dbConfig['database'], $dbConfig['charset']
    );
    $db = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options']);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

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

try {
    $auth = new Auth($db);
    $auth->requireAdmin();
    $currentUser = $auth->user();
} catch (Exception $e) {
    die("Authentication error: " . $e->getMessage());
}

$internshipId = $_GET['internship_id'] ?? null;
$statusFilter = $_GET['status'] ?? null;
$paymentFilter = $_GET['payment_status'] ?? null;

// ✅ GET ENROLLMENTS WITH ALL PAYMENT DETAILS
try {
    $query = "
        SELECT 
            ie.id,
            ie.user_id,
            ie.internship_id,
            ie.payment_id,
            ie.payment_amount,
            ie.payment_status,
            ie.status,
            ie.coupon_id,
            ie.coupon_discount,
            ie.final_amount,
            ie.created_at,
            u.name as user_name,
            u.email as user_email,
            i.title as internship_title,
            i.price as internship_price,
            i.discount_price as internship_discount_price,
            c.code as coupon_code
        FROM internship_enrollments ie
        INNER JOIN users u ON ie.user_id = u.id
        INNER JOIN internships i ON ie.internship_id = i.id
        LEFT JOIN coupons c ON ie.coupon_id = c.id
        WHERE 1=1
    ";
    
    $params = [];
    
    if ($internshipId) {
        $query .= " AND ie.internship_id = ?";
        $params[] = $internshipId;
    }
    
    if ($statusFilter) {
        $query .= " AND ie.status = ?";
        $params[] = $statusFilter;
    }
    
    if ($paymentFilter) {
        $query .= " AND ie.payment_status = ?";
        $params[] = $paymentFilter;
    }
    
    $query .= " ORDER BY ie.created_at DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $enrollments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching enrollments: " . $e->getMessage());
    echo "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4'><strong>SQL Error:</strong> " . $e->getMessage() . "</div>";
    $enrollments = [];
}

// ✅ GET INTERNSHIPS FOR FILTER
try {
    $stmt = $db->prepare("SELECT id, title FROM internships ORDER BY title ASC");
    $stmt->execute();
    $internships = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching internships: " . $e->getMessage());
    $internships = [];
}

// ✅ GET ACCURATE STATS
try {
    $query = "
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN payment_status = 'pending' THEN 1 ELSE 0 END) as pending_payments,
            SUM(CASE WHEN payment_status = 'completed' THEN 1 ELSE 0 END) as paid_count,
            COALESCE(SUM(CASE WHEN payment_status = 'completed' THEN payment_amount ELSE 0 END), 0) as total_revenue,
            COALESCE(SUM(CASE WHEN payment_status = 'pending' THEN final_amount ELSE 0 END), 0) as pending_revenue
        FROM internship_enrollments
    ";
    
    $params = [];
    
    if ($internshipId) {
        $query .= " WHERE internship_id = ?";
        $params[] = $internshipId;
    }
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $statsRow = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $stats = [
        'total' => (int)($statsRow['total'] ?? 0),
        'active' => (int)($statsRow['active'] ?? 0),
        'completed' => (int)($statsRow['completed'] ?? 0),
        'pending_payments' => (int)($statsRow['pending_payments'] ?? 0),
        'paid_count' => (int)($statsRow['paid_count'] ?? 0),
        'total_revenue' => (float)($statsRow['total_revenue'] ?? 0),
        'pending_revenue' => (float)($statsRow['pending_revenue'] ?? 0)
    ];
} catch (PDOException $e) {
    error_log("Error fetching stats: " . $e->getMessage());
    $stats = ['total' => 0, 'active' => 0, 'completed' => 0, 'pending_payments' => 0, 'paid_count' => 0, 'total_revenue' => 0, 'pending_revenue' => 0];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Internship Enrollments - Admin Panel</title>
    
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
                        }
                    }
                }
            }
        }
    </script>
    
    <style>
        body { font-family: 'Inter', sans-serif; background: #fafafa; }
        h1, h2, h3, h4, h5, h6 { font-family: 'Poppins', sans-serif; font-weight: 700; }
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

<!-- Sidebar -->
<?php include __DIR__ . '/../../components/sidebar-admin.php'; ?>

<!-- Main Content -->
<div class="ml-64 min-h-screen">
    <header class="bg-white border-b border-gray-200 sticky top-0 z-30">
        <div class="px-8 py-6">
            <div class="flex items-center gap-3 mb-2">
                <a href="list.php" class="text-gray-600 hover:text-gray-900 transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                </a>
                <h1 class="text-3xl font-bold text-gray-900">Internship Enrollments</h1>
            </div>
            <p class="text-gray-600">Track and manage student internship enrollments & payments</p>
        </div>
    </header>

    <main class="p-8">
        <!-- Top Stats Row -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <!-- Total Enrollments -->
            <div class="stat-card">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                    </div>
                </div>
                <h3 class="text-3xl font-bold text-gray-900"><?= $stats['total'] ?></h3>
                <p class="text-sm text-gray-600 mt-1">Total Enrollments</p>
            </div>

            <!-- Active Internships -->
            <div class="stat-card">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-12 h-12 bg-primary-100 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                </div>
                <h3 class="text-3xl font-bold text-gray-900"><?= $stats['active'] ?></h3>
                <p class="text-sm text-gray-600 mt-1">Active Internships</p>
            </div>

            <!-- Completed -->
            <div class="stat-card">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"></path>
                        </svg>
                    </div>
                </div>
                <h3 class="text-3xl font-bold text-gray-900"><?= $stats['completed'] ?></h3>
                <p class="text-sm text-gray-600 mt-1">Completed</p>
            </div>

            <!-- Pending Payments -->
            <div class="stat-card">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                </div>
                <h3 class="text-3xl font-bold text-gray-900"><?= $stats['pending_payments'] ?></h3>
                <p class="text-sm text-gray-600 mt-1">Pending Payments</p>
            </div>
        </div>

        <!-- Revenue Stats Row -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <!-- Total Revenue (Paid) -->
            <div class="stat-card bg-gradient-to-br from-green-50 to-green-100 border-green-200">
                <div class="flex items-center justify-between mb-2">
                    <div class="w-12 h-12 bg-green-200 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-green-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <span class="text-sm font-bold text-green-700 bg-green-200 px-3 py-1 rounded-full">
                        <?= $stats['paid_count'] ?> Paid
                    </span>
                </div>
                <p class="text-sm font-semibold text-green-700 mb-1">💰 Total Revenue Received</p>
                <h3 class="text-3xl font-bold text-green-900">₹<?= number_format($stats['total_revenue'], 2) ?></h3>
            </div>

            <!-- Pending Revenue -->
            <div class="stat-card bg-gradient-to-br from-orange-50 to-orange-100 border-orange-200">
                <div class="flex items-center justify-between mb-2">
                    <div class="w-12 h-12 bg-orange-200 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-orange-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <span class="text-sm font-bold text-orange-700 bg-orange-200 px-3 py-1 rounded-full">
                        <?= $stats['pending_payments'] ?> Pending
                    </span>
                </div>
                <p class="text-sm font-semibold text-orange-700 mb-1">⏳ Pending Revenue</p>
                <h3 class="text-3xl font-bold text-orange-900">₹<?= number_format($stats['pending_revenue'], 2) ?></h3>
            </div>

            <!-- Expected Total Revenue -->
            <div class="stat-card bg-gradient-to-br from-blue-50 to-blue-100 border-blue-200">
                <div class="flex items-center justify-between mb-2">
                    <div class="w-12 h-12 bg-blue-200 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-blue-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                        </svg>
                    </div>
                </div>
                <p class="text-sm font-semibold text-blue-700 mb-1">📊 Expected Total Revenue</p>
                <h3 class="text-3xl font-bold text-blue-900">₹<?= number_format($stats['total_revenue'] + $stats['pending_revenue'], 2) ?></h3>
                <p class="text-xs text-blue-600 mt-1">(Received + Pending)</p>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-xl border border-gray-200 p-6 mb-6">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Filter by Internship</label>
                    <select name="internship_id" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        <option value="">All Internships</option>
                        <?php foreach ($internships as $internship): ?>
                            <option value="<?= $internship['id'] ?>" <?= $internshipId == $internship['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($internship['title']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Enrollment Status</label>
                    <select name="status" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        <option value="">All Status</option>
                        <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : '' ?>>Completed</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Payment Status</label>
                    <select name="payment_status" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        <option value="">All Payments</option>
                        <option value="completed" <?= $paymentFilter === 'completed' ? 'selected' : '' ?>>Paid</option>
                        <option value="pending" <?= $paymentFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                    </select>
                </div>

                <div class="flex gap-2">
                    <button type="submit" class="flex-1 px-6 py-3 bg-primary-600 hover:bg-primary-700 text-white rounded-lg font-semibold transition-colors">
                        Apply Filters
                    </button>
                    <?php if ($internshipId || $statusFilter || $paymentFilter): ?>
                        <a href="enrollments.php" class="px-6 py-3 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg font-semibold transition-colors">
                            Clear
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Enrollments Table -->
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                <h3 class="text-lg font-bold text-gray-900">Enrollments (<?= count($enrollments) ?>)</h3>
            </div>
            
            <?php if (empty($enrollments)): ?>
                <div class="p-12 text-center">
                    <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
                        </svg>
                    </div>
                    <p class="text-gray-500 font-medium">No enrollments found</p>
                    <p class="text-sm text-gray-400 mt-1">Try changing your filters</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50 border-b border-gray-200">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">ID</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Student</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Internship</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Amount Paid</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Payment Status</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Enrollment Status</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Enrolled Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach ($enrollments as $enrollment): 
                                // Calculate displayed price
                                $displayedPrice = ($enrollment['payment_status'] === 'completed' && $enrollment['payment_amount'] > 0) 
                                    ? $enrollment['payment_amount'] 
                                    : $enrollment['final_amount'];
                                
                                $originalPrice = $enrollment['internship_discount_price'] > 0 
                                    ? $enrollment['internship_discount_price'] 
                                    : $enrollment['internship_price'];
                            ?>
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-6 py-4">
                                        <span class="font-mono text-sm text-gray-600">#<?= $enrollment['id'] ?></span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div>
                                            <p class="font-semibold text-gray-900"><?= htmlspecialchars($enrollment['user_name']) ?></p>
                                            <p class="text-sm text-gray-500"><?= htmlspecialchars($enrollment['user_email']) ?></p>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <p class="font-medium text-gray-900"><?= htmlspecialchars($enrollment['internship_title']) ?></p>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm">
                                            <p class="font-bold text-xl text-gray-900">₹<?= number_format($displayedPrice, 2) ?></p>
                                            
                                            <?php if ($enrollment['coupon_discount'] > 0): ?>
                                                <p class="text-xs text-gray-500 line-through mt-1">Original: ₹<?= number_format($originalPrice, 2) ?></p>
                                                <div class="flex gap-1 mt-1 flex-wrap">
                                                    <?php if ($enrollment['coupon_code']): ?>
                                                        <span class="bg-purple-100 text-purple-700 px-2 py-0.5 rounded text-xs font-bold">
                                                            🎟️ <?= htmlspecialchars($enrollment['coupon_code']) ?>
                                                        </span>
                                                    <?php endif; ?>
                                                    <span class="bg-green-100 text-green-700 px-2 py-0.5 rounded text-xs font-bold">
                                                        -₹<?= number_format($enrollment['coupon_discount'], 2) ?>
                                                    </span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="px-3 py-1 rounded-full text-xs font-semibold
                                            <?= $enrollment['payment_status'] === 'completed' ? 'bg-green-100 text-green-700' : '' ?>
                                            <?= $enrollment['payment_status'] === 'pending' ? 'bg-orange-100 text-orange-700' : '' ?>">
                                            <?= $enrollment['payment_status'] === 'completed' ? '✅ Paid' : '⏳ Pending' ?>
                                        </span>
                                        <?php if ($enrollment['payment_id']): ?>
                                            <p class="text-xs text-gray-500 mt-1 font-mono">ID: <?= htmlspecialchars($enrollment['payment_id']) ?></p>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="px-3 py-1 rounded-full text-xs font-semibold
                                            <?= $enrollment['status'] === 'active' ? 'bg-green-100 text-green-700' : '' ?>
                                            <?= $enrollment['status'] === 'completed' ? 'bg-blue-100 text-blue-700' : '' ?>">
                                            <?= ucfirst($enrollment['status']) ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <p class="text-sm text-gray-700"><?= date('M d, Y', strtotime($enrollment['created_at'])) ?></p>
                                        <p class="text-xs text-gray-500"><?= date('h:i A', strtotime($enrollment['created_at'])) ?></p>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

</body>
</html>
