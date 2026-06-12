<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

require_once __DIR__ . '/../../../config/database.php';
$dbConfig = require __DIR__ . '/../../../config/database.php';

$dsn = sprintf("mysql:host=%s;port=%s;dbname=%s;charset=%s",
    $dbConfig['host'], 
    $dbConfig['port'], 
    $dbConfig['database'], 
    $dbConfig['charset']
);

try {
    $db = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options']);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

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

// Get filters
$filterCoupon = $_GET['coupon_id'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

// Check if coming from specific coupon link
$isDirectLink = !empty($filterCoupon) && empty($dateFrom) && empty($dateTo) && !isset($_GET['filtered']);

// Get all coupons for filter
$couponsStmt = $db->query("SELECT id, code FROM coupons ORDER BY code ASC");
$allCoupons = $couponsStmt->fetchAll();

// Get specific coupon details if direct link
$selectedCoupon = null;
if ($isDirectLink && !empty($filterCoupon)) {
    $couponStmt = $db->prepare("SELECT * FROM coupons WHERE id = ?");
    $couponStmt->execute([$filterCoupon]);
    $selectedCoupon = $couponStmt->fetch();
}

// Build query for order-based system
$query = "
    SELECT 
        cu.*,
        c.code as coupon_code,
        c.discount_type,
        c.discount_value,
        u.name as user_name,
        u.email as user_email,
        u.mobile as user_mobile,
        COALESCE(cu.order_id, 'N/A') as order_reference
    FROM coupon_usage cu
    JOIN coupons c ON cu.coupon_id = c.id
    JOIN users u ON cu.user_id = u.id
    WHERE 1=1
";

$params = [];

if (!empty($filterCoupon)) {
    $query .= " AND cu.coupon_id = ?";
    $params[] = $filterCoupon;
}

if (!empty($dateFrom)) {
    $query .= " AND DATE(cu.used_at) >= ?";
    $params[] = $dateFrom;
}

if (!empty($dateTo)) {
    $query .= " AND DATE(cu.used_at) <= ?";
    $params[] = $dateTo;
}

$query .= " ORDER BY cu.used_at DESC LIMIT 100";

$stmt = $db->prepare($query);
$stmt->execute($params);
$usageRecords = $stmt->fetchAll();

// Get statistics
$statsQuery = "
    SELECT 
        COALESCE(COUNT(*), 0) as total_uses,
        COALESCE(COUNT(DISTINCT user_id), 0) as unique_users,
        COALESCE(COUNT(DISTINCT coupon_id), 0) as coupons_used,
        COALESCE(SUM(discount_amount), 0) as total_discount_given
    FROM coupon_usage
    WHERE 1=1
";

$statsParams = [];

if (!empty($filterCoupon)) {
    $statsQuery .= " AND coupon_id = ?";
    $statsParams[] = $filterCoupon;
}

if (!empty($dateFrom)) {
    $statsQuery .= " AND DATE(used_at) >= ?";
    $statsParams[] = $dateFrom;
}

if (!empty($dateTo)) {
    $statsQuery .= " AND DATE(used_at) <= ?";
    $statsParams[] = $dateTo;
}

$statsStmt = $db->prepare($statsQuery);
$statsStmt->execute($statsParams);
$stats = $statsStmt->fetch();

// Get total active coupons from coupons table
$activeCouponsStmt = $db->query("SELECT COUNT(*) as total_active FROM coupons WHERE is_active = 1");
$activeCouponsCount = $activeCouponsStmt->fetch()['total_active'] ?? 0;

// Ensure all stats are numeric
$stats['total_uses'] = (int)($stats['total_uses'] ?? 0);
$stats['unique_users'] = (int)($stats['unique_users'] ?? 0);
$stats['coupons_used'] = (int)($stats['coupons_used'] ?? 0);
$stats['total_discount_given'] = (float)($stats['total_discount_given'] ?? 0);

function safeText($text) {
    return htmlspecialchars_decode($text, ENT_QUOTES);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coupon Usage Tracking - Admin</title>
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
        }
        h1, h2, h3, h4, h5, h6 {
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
        }
        @media (max-width: 1024px) {
            .ml-64 {
                margin-left: 0;
            }
        }
    </style>
</head>
<body class="bg-gray-50">

<?php include __DIR__ . '/../../components/sidebar-admin.php'; ?>

<div class="lg:ml-64 min-h-screen">
    <header class="bg-white border-b border-gray-200 sticky top-0 z-30">
        <div class="px-4 sm:px-6 lg:px-8 py-4 sm:py-6">
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                <div>
                    <?php if ($isDirectLink && $selectedCoupon): ?>
                        <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">
                            🎫 Tracking: <span class="text-primary-600"><?php echo safeText($selectedCoupon['code']); ?></span>
                        </h1>
                        <p class="text-gray-600 mt-1 text-sm sm:text-base">Usage history for this specific coupon</p>
                    <?php else: ?>
                        <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">🎫 Coupon Usage Tracking</h1>
                        <p class="text-gray-600 mt-1 text-sm sm:text-base">Monitor coupon usage and discount analytics</p>
                    <?php endif; ?>
                </div>
                <div class="flex gap-2">
                    <?php if ($isDirectLink): ?>
                        <a href="tracking.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 sm:px-6 py-2 sm:py-3 rounded-lg font-semibold transition-colors text-sm sm:text-base whitespace-nowrap flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                            </svg>
                            View All
                        </a>
                    <?php endif; ?>
                    <a href="list.php" class="bg-gray-600 hover:bg-gray-700 text-white px-4 sm:px-6 py-2 sm:py-3 rounded-lg font-semibold transition-colors text-sm sm:text-base whitespace-nowrap">
                        ← Back to Coupons
                    </a>
                </div>
            </div>
        </div>
    </header>

    <main class="p-4 sm:p-6 lg:p-8">
        <?php if ($isDirectLink && $selectedCoupon): ?>
            <!-- Specific Coupon View - Clean Layout -->
            <div class="bg-gradient-to-br from-primary-50 to-green-50 rounded-xl border-2 border-primary-200 p-6 mb-6">
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <div class="flex items-center gap-3 mb-4">
                            <span class="text-3xl font-bold text-primary-600"><?php echo safeText($selectedCoupon['code']); ?></span>
                            <span class="px-3 py-1 rounded-full text-xs font-semibold <?php echo $selectedCoupon['is_active'] ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'; ?>">
                                <?php echo $selectedCoupon['is_active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                        </div>
                        
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                            <div>
                                <p class="text-xs text-gray-600 mb-1">Discount Type</p>
                                <p class="font-semibold text-gray-900">
                                    <?php echo $selectedCoupon['discount_type'] === 'percentage' ? 'Percentage' : 'Fixed Amount'; ?>
                                </p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-600 mb-1">Discount Value</p>
                                <p class="font-semibold text-gray-900">
                                    <?php echo $selectedCoupon['discount_type'] === 'percentage' ? $selectedCoupon['discount_value'] . '%' : '₹' . number_format($selectedCoupon['discount_value'], 2); ?>
                                </p>
                            </div>
                            <?php if ($selectedCoupon['min_purchase'] > 0): ?>
                            <div>
                                <p class="text-xs text-gray-600 mb-1">Min Purchase</p>
                                <p class="font-semibold text-gray-900">₹<?php echo number_format($selectedCoupon['min_purchase'], 2); ?></p>
                            </div>
                            <?php endif; ?>
                            <?php if ($selectedCoupon['usage_limit'] > 0): ?>
                            <div>
                                <p class="text-xs text-gray-600 mb-1">Usage Limit</p>
                                <p class="font-semibold text-gray-900"><?php echo $selectedCoupon['usage_limit']; ?> times</p>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="grid grid-cols-3 gap-4 pt-4 border-t border-primary-200">
                            <div class="text-center">
                                <p class="text-2xl font-bold text-primary-600"><?php echo number_format($stats['total_uses']); ?></p>
                                <p class="text-xs text-gray-600">Times Used</p>
                            </div>
                            <div class="text-center">
                                <p class="text-2xl font-bold text-green-600"><?php echo number_format($stats['unique_users']); ?></p>
                                <p class="text-xs text-gray-600">Unique Users</p>
                            </div>
                            <div class="text-center">
                                <p class="text-2xl font-bold text-red-600">₹<?php echo number_format($stats['total_discount_given'], 2); ?></p>
                                <p class="text-xs text-gray-600">Total Discount</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        <?php else: ?>
            <!-- All Coupons View - Statistics Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6 mb-6 sm:mb-8">
                <div class="bg-white rounded-xl border border-gray-200 p-4 sm:p-6 shadow-sm hover:shadow-md transition-shadow">
                    <div class="flex items-center gap-3 sm:gap-4">
                        <div class="w-10 h-10 sm:w-12 sm:h-12 bg-blue-100 rounded-lg flex items-center justify-center flex-shrink-0">
                            <svg class="w-5 h-5 sm:w-6 sm:h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                            </svg>
                        </div>
                        <div>
                            <p class="text-xs sm:text-sm text-gray-600">Total Uses</p>
                            <p class="text-xl sm:text-2xl font-bold text-gray-900"><?php echo number_format($stats['total_uses']); ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl border border-gray-200 p-4 sm:p-6 shadow-sm hover:shadow-md transition-shadow">
                    <div class="flex items-center gap-3 sm:gap-4">
                        <div class="w-10 h-10 sm:w-12 sm:h-12 bg-green-100 rounded-lg flex items-center justify-center flex-shrink-0">
                            <svg class="w-5 h-5 sm:w-6 sm:h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                            </svg>
                        </div>
                        <div>
                            <p class="text-xs sm:text-sm text-gray-600">Unique Users</p>
                            <p class="text-xl sm:text-2xl font-bold text-gray-900"><?php echo number_format($stats['unique_users']); ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl border border-gray-200 p-4 sm:p-6 shadow-sm hover:shadow-md transition-shadow">
                    <div class="flex items-center gap-3 sm:gap-4">
                        <div class="w-10 h-10 sm:w-12 sm:h-12 bg-purple-100 rounded-lg flex items-center justify-center flex-shrink-0">
                            <svg class="w-5 h-5 sm:w-6 sm:h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                            </svg>
                        </div>
                        <div>
                            <p class="text-xs sm:text-sm text-gray-600">Active Coupons</p>
                            <p class="text-xl sm:text-2xl font-bold text-gray-900"><?php echo number_format($activeCouponsCount); ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl border border-gray-200 p-4 sm:p-6 shadow-sm hover:shadow-md transition-shadow">
                    <div class="flex items-center gap-3 sm:gap-4">
                        <div class="w-10 h-10 sm:w-12 sm:h-12 bg-red-100 rounded-lg flex items-center justify-center flex-shrink-0">
                            <svg class="w-5 h-5 sm:w-6 sm:h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <div>
                            <p class="text-xs sm:text-sm text-gray-600">Total Discount</p>
                            <p class="text-xl sm:text-2xl font-bold text-gray-900">₹<?php echo number_format($stats['total_discount_given'], 2); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filter Section - Only show when not direct link -->
            <div class="bg-white rounded-xl border border-gray-200 p-4 sm:p-6 mb-6 shadow-sm">
                <h3 class="font-bold text-gray-900 mb-4 text-sm sm:text-base flex items-center gap-2">
                    <svg class="w-5 h-5 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path>
                    </svg>
                    Filter Results
                </h3>
                <form method="GET" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <input type="hidden" name="filtered" value="1">
                    <div>
                        <label class="block text-xs sm:text-sm font-semibold text-gray-700 mb-2">Coupon Code</label>
                        <select name="coupon_id" class="w-full px-3 sm:px-4 py-2 sm:py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 text-sm">
                            <option value="">All Coupons</option>
                            <?php foreach ($allCoupons as $coupon): ?>
                                <option value="<?php echo $coupon['id']; ?>" <?php echo $filterCoupon == $coupon['id'] ? 'selected' : ''; ?>>
                                    <?php echo safeText($coupon['code']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-xs sm:text-sm font-semibold text-gray-700 mb-2">From Date</label>
                        <input type="date" name="date_from" value="<?php echo htmlspecialchars($dateFrom); ?>" class="w-full px-3 sm:px-4 py-2 sm:py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 text-sm">
                    </div>
                    
                    <div>
                        <label class="block text-xs sm:text-sm font-semibold text-gray-700 mb-2">To Date</label>
                        <input type="date" name="date_to" value="<?php echo htmlspecialchars($dateTo); ?>" class="w-full px-3 sm:px-4 py-2 sm:py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 text-sm">
                    </div>
                    
                    <div class="sm:col-span-2 lg:col-span-3 flex flex-col sm:flex-row gap-2">
                        <button type="submit" class="bg-primary-600 hover:bg-primary-700 text-white px-4 sm:px-6 py-2 sm:py-3 rounded-lg font-semibold transition-colors text-sm flex items-center justify-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                            Apply Filters
                        </button>
                        <?php if (!empty($filterCoupon) || !empty($dateFrom) || !empty($dateTo)): ?>
                            <a href="tracking.php" class="text-center bg-gray-600 hover:bg-gray-700 text-white px-4 sm:px-6 py-2 sm:py-3 rounded-lg font-semibold transition-colors text-sm flex items-center justify-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                                Clear Filters
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <!-- Usage Table -->
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden shadow-sm">
            <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                <h3 class="font-bold text-gray-900 text-sm sm:text-base">
                    <?php echo $isDirectLink ? 'Usage History' : 'Usage History (Last 100 Records)'; ?>
                </h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="px-3 sm:px-6 py-3 sm:py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">ID</th>
                            <?php if (!$isDirectLink): ?>
                            <th class="px-3 sm:px-6 py-3 sm:py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">COUPON</th>
                            <?php endif; ?>
                            <th class="px-3 sm:px-6 py-3 sm:py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">USER</th>
                            <th class="px-3 sm:px-6 py-3 sm:py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider hidden sm:table-cell">ORDER ID</th>
                            <th class="px-3 sm:px-6 py-3 sm:py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">DISCOUNT</th>
                            <th class="px-3 sm:px-6 py-3 sm:py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider hidden lg:table-cell">USED AT</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php if (empty($usageRecords)): ?>
                            <tr>
                                <td colspan="<?php echo $isDirectLink ? '5' : '6'; ?>" class="px-3 sm:px-6 py-12 text-center">
                                    <svg class="w-12 h-12 sm:w-16 sm:h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                    </svg>
                                    <p class="text-gray-500 font-semibold text-sm sm:text-base">No usage records found</p>
                                    <p class="text-gray-400 text-xs sm:text-sm mt-1">
                                        <?php echo $isDirectLink ? 'This coupon has not been used yet' : 'Records will appear when users apply coupons'; ?>
                                    </p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($usageRecords as $record): ?>
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-3 sm:px-6 py-3 sm:py-4">
                                        <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-gray-100 text-gray-600 font-semibold text-xs">
                                            #<?php echo $record['id']; ?>
                                        </span>
                                    </td>
                                    <?php if (!$isDirectLink): ?>
                                    <td class="px-3 sm:px-6 py-3 sm:py-4">
                                        <span class="inline-block bg-primary-100 text-primary-700 px-2 sm:px-3 py-1 rounded-full text-xs font-bold">
                                            <?php echo safeText($record['coupon_code']); ?>
                                        </span>
                                    </td>
                                    <?php endif; ?>
                                    <td class="px-3 sm:px-6 py-3 sm:py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 bg-gradient-to-br from-primary-400 to-primary-600 rounded-full flex items-center justify-center text-white font-bold text-xs flex-shrink-0">
                                                <?php echo strtoupper(substr($record['user_name'], 0, 1)); ?>
                                            </div>
                                            <div class="min-w-0">
                                                <p class="font-semibold text-gray-900 text-xs sm:text-sm truncate"><?php echo safeText($record['user_name']); ?></p>
                                                <p class="text-gray-500 text-xs truncate"><?php echo safeText($record['user_email']); ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-3 sm:px-6 py-3 sm:py-4 hidden sm:table-cell">
                                        <span class="text-xs sm:text-sm text-gray-600 font-mono">
                                            <?php echo safeText($record['order_reference']); ?>
                                        </span>
                                    </td>
                                    <td class="px-3 sm:px-6 py-3 sm:py-4">
                                        <div class="flex items-center gap-1">
                                            <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                            </svg>
                                            <span class="text-xs sm:text-sm font-bold text-red-600">₹<?php echo number_format((float)($record['discount_amount'] ?? 0), 2); ?></span>
                                        </div>
                                    </td>
                                    <td class="px-3 sm:px-6 py-3 sm:py-4 text-xs sm:text-sm text-gray-500 hidden lg:table-cell whitespace-nowrap">
                                        <?php echo date('M j, Y', strtotime($record['used_at'])); ?>
                                        <span class="text-gray-400 block text-xs"><?php echo date('g:i A', strtotime($record['used_at'])); ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

</body>
</html>
