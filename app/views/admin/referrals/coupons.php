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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Referral Coupons - Admin</title>
    
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
        }
    </style>
</head>
<body>

<!-- Sidebar -->
<?php include __DIR__ . '/../../components/sidebar-admin.php'; ?>

<!-- Main Content -->
<div class="ml-64 min-h-screen">
    <!-- Header -->
    <header class="bg-white border-b border-gray-200 sticky top-0 z-30">
        <div class="px-8 py-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Referral Coupons</h1>
                    <p class="text-gray-600 mt-1">View all coupons generated from points redemption</p>
                </div>
                <a href="/app/views/admin/referrals/dashboard.php" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold rounded-lg transition-colors">
                    ← Back to Dashboard
                </a>
            </div>
        </div>
    </header>

    <!-- Content -->
    <main class="p-8">
        <!-- Loading State -->
        <div id="loading" class="text-center py-12">
            <div class="inline-block animate-spin rounded-full h-12 w-12 border-4 border-primary-500 border-t-transparent"></div>
            <p class="text-gray-600 mt-4">Loading coupons...</p>
        </div>

        <!-- Coupons Table -->
        <div id="content" class="hidden stat-card">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b-2 border-gray-200">
                            <th class="text-left py-3 px-4 text-xs font-semibold text-gray-600 uppercase">Coupon Code</th>
                            <th class="text-left py-3 px-4 text-xs font-semibold text-gray-600 uppercase">User</th>
                            <th class="text-left py-3 px-4 text-xs font-semibold text-gray-600 uppercase">Points Redeemed</th>
                            <th class="text-left py-3 px-4 text-xs font-semibold text-gray-600 uppercase">Coupon Value</th>
                            <th class="text-left py-3 px-4 text-xs font-semibold text-gray-600 uppercase">Status</th>
                            <th class="text-left py-3 px-4 text-xs font-semibold text-gray-600 uppercase">Generated</th>
                            <th class="text-left py-3 px-4 text-xs font-semibold text-gray-600 uppercase">Used</th>
                        </tr>
                    </thead>
                    <tbody id="couponsBody">
                        <tr>
                            <td colspan="7" class="text-center py-8 text-gray-500">Loading...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<script>
    async function loadCoupons() {
        try {
            const response = await fetch('/api/admin-referrals.php?action=coupons&limit=100');
            const result = await response.json();

            if (result.success) {
                displayCoupons(result.coupons);
                document.getElementById('loading').classList.add('hidden');
                document.getElementById('content').classList.remove('hidden');
            } else {
                alert('Failed to load coupons: ' + result.error);
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Network error. Please refresh.');
        }
    }

    function displayCoupons(coupons) {
        const tbody = document.getElementById('couponsBody');
        
        if (coupons.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center py-12 text-gray-500">No referral coupons generated yet</td></tr>';
            return;
        }

        tbody.innerHTML = coupons.map(coupon => `
            <tr class="border-b border-gray-100 hover:bg-gray-50">
                <td class="py-3 px-4">
                    <code class="text-sm font-mono bg-primary-100 text-primary-700 px-3 py-1 rounded">${coupon.code}</code>
                </td>
                <td class="py-3 px-4">
                    <p class="font-semibold text-sm text-gray-900">${coupon.user_name}</p>
                    <p class="text-xs text-gray-500">${coupon.user_email}</p>
                </td>
                <td class="py-3 px-4">
                    <span class="font-bold text-primary-600">${coupon.points_redeemed || 0}</span> points
                </td>
                <td class="py-3 px-4">
                    <span class="font-bold text-gray-900">₹${parseFloat(coupon.discount_value).toFixed(2)}</span>
                </td>
                <td class="py-3 px-4">
                    <span class="px-2 py-1 rounded-full text-xs font-semibold ${
                        coupon.redeemed_at ? 'bg-green-100 text-green-700' : 
                        coupon.is_active ? 'bg-blue-100 text-blue-700' : 
                        'bg-gray-100 text-gray-700'
                    }">
                        ${coupon.redeemed_at ? '✓ Used' : coupon.is_active ? 'Active' : 'Inactive'}
                    </span>
                </td>
                <td class="py-3 px-4">
                    <p class="text-xs text-gray-600">${new Date(coupon.created_at).toLocaleDateString()}</p>
                    <p class="text-xs text-gray-500">${new Date(coupon.created_at).toLocaleTimeString()}</p>
                </td>
                <td class="py-3 px-4">
                    ${coupon.redeemed_at ? `
                        <p class="text-xs text-green-600 font-semibold">${new Date(coupon.redeemed_at).toLocaleDateString()}</p>
                        ${coupon.used_order_id ? `<p class="text-xs text-gray-500">Order #${coupon.used_order_id}</p>` : ''}
                    ` : '<span class="text-xs text-gray-400">Not used</span>'}
                </td>
            </tr>
        `).join('');
    }

    // Initialize
    window.addEventListener('DOMContentLoaded', loadCoupons);
</script>

</body>
</html>
