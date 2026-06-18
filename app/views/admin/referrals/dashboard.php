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
    <title>Referral Dashboard - Admin</title>
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
                    <h1 class="text-3xl font-bold text-gray-900">Referral System</h1>
                    <p class="text-gray-600 mt-1">Monitor referrals, earnings, and performance</p>
                </div>
                <div class="flex items-center gap-4">
                    <a href="/app/views/admin/referrals/settings.php" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold rounded-lg transition-colors">
                        ⚙️ Settings
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Content -->
    <main class="p-8">
        <!-- Loading State -->
        <div id="loading" class="text-center py-12">
            <div class="inline-block animate-spin rounded-full h-12 w-12 border-4 border-primary-500 border-t-transparent"></div>
            <p class="text-gray-600 mt-4">Loading statistics...</p>
        </div>

        <!-- Stats Grid -->
        <div id="content" class="hidden">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <!-- Total Referrals -->
                <div class="stat-card">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                            </svg>
                        </div>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900" id="totalReferrals">0</h3>
                    <p class="text-sm text-gray-600 mt-1">Total Referrals</p>
                </div>

                <!-- Completed -->
                <div class="stat-card">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900" id="completedReferrals">0</h3>
                    <p class="text-sm text-gray-600 mt-1">Completed Referrals</p>
                </div>

                <!-- Pending -->
                <div class="stat-card">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900" id="pendingReferrals">0</h3>
                    <p class="text-sm text-gray-600 mt-1">Pending Purchase</p>
                </div>

                <!-- Points Awarded -->
                <div class="stat-card bg-gradient-to-br from-primary-50 to-primary-100">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 bg-primary-500 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                    </div>
                    <h3 class="text-2xl font-bold text-primary-900" id="totalPointsAwarded">0</h3>
                    <p class="text-sm text-primary-700 mt-1">Points Awarded</p>
                </div>

                <!-- Points Redeemed -->
                <div class="stat-card">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"></path>
                            </svg>
                        </div>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900" id="totalPointsRedeemed">0</h3>
                    <p class="text-sm text-gray-600 mt-1">Points Redeemed</p>
                </div>

                <!-- Coupons Generated -->
                <div class="stat-card">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                            </svg>
                        </div>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900" id="totalCoupons">0</h3>
                    <p class="text-sm text-gray-600 mt-1">Coupons Generated</p>
                </div>

                <!-- Coupons Used -->
                <div class="stat-card">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 bg-pink-100 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6 text-pink-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                            </svg>
                        </div>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900" id="couponsUsed">0</h3>
                    <p class="text-sm text-gray-600 mt-1">Coupons Used</p>
                </div>

                <!-- Active Points -->
                <div class="stat-card">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 bg-indigo-100 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                            </svg>
                        </div>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900" id="activePoints">0</h3>
                    <p class="text-sm text-gray-600 mt-1">Active Points (Net)</p>
                </div>
            </div>

            <!-- Recent Referrals Table -->
            <div class="stat-card mb-8">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-xl font-bold text-gray-900">Recent Referrals</h3>
                    <a href="#" onclick="loadReferrals(); return false;" class="text-primary-600 hover:text-primary-700 font-semibold text-sm">Refresh</a>
                </div>
                
                <div id="referralsTable" class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b-2 border-gray-200">
                                <th class="text-left py-3 px-4 text-xs font-semibold text-gray-600 uppercase">Referrer</th>
                                <th class="text-left py-3 px-4 text-xs font-semibold text-gray-600 uppercase">Referred User</th>
                                <th class="text-left py-3 px-4 text-xs font-semibold text-gray-600 uppercase">Code</th>
                                <th class="text-left py-3 px-4 text-xs font-semibold text-gray-600 uppercase">Status</th>
                                <th class="text-left py-3 px-4 text-xs font-semibold text-gray-600 uppercase">Points</th>
                                <th class="text-left py-3 px-4 text-xs font-semibold text-gray-600 uppercase">Date</th>
                            </tr>
                        </thead>
                        <tbody id="referralsBody">
                            <tr>
                                <td colspan="6" class="text-center py-8 text-gray-500">Loading...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
    let statsData = null;

    async function loadStats() {
        try {
            const response = await fetch('/api/admin-referrals.php?action=stats');
            const result = await response.json();

            if (result.success) {
                statsData = result.stats;
                updateStatsUI(result.stats);
                document.getElementById('loading').classList.add('hidden');
                document.getElementById('content').classList.remove('hidden');
            } else {
                alert('Failed to load stats: ' + result.error);
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Network error. Please refresh.');
        }
    }

    function updateStatsUI(stats) {
        document.getElementById('totalReferrals').textContent = stats.total_referrals;
        document.getElementById('completedReferrals').textContent = stats.completed_referrals;
        document.getElementById('pendingReferrals').textContent = stats.pending_referrals;
        document.getElementById('totalPointsAwarded').textContent = stats.total_points_awarded;
        document.getElementById('totalPointsRedeemed').textContent = stats.total_points_redeemed;
        document.getElementById('totalCoupons').textContent = stats.total_coupons_generated;
        document.getElementById('couponsUsed').textContent = stats.coupons_used;
        document.getElementById('activePoints').textContent = stats.total_points_awarded - stats.total_points_redeemed;
    }

    async function loadReferrals() {
        try {
            const response = await fetch('/api/admin-referrals.php?action=list&limit=20');
            const result = await response.json();

            if (result.success) {
                displayReferrals(result.referrals);
            }
        } catch (error) {
            console.error('Error:', error);
        }
    }

    function displayReferrals(referrals) {
        const tbody = document.getElementById('referralsBody');
        
        if (referrals.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center py-8 text-gray-500">No referrals yet</td></tr>';
            return;
        }

        tbody.innerHTML = referrals.map(ref => `
            <tr class="border-b border-gray-100 hover:bg-gray-50">
                <td class="py-3 px-4">
                    <p class="font-semibold text-sm text-gray-900">${ref.referrer_name}</p>
                    <p class="text-xs text-gray-500">${ref.referrer_email}</p>
                </td>
                <td class="py-3 px-4">
                    <p class="font-semibold text-sm text-gray-900">${ref.referred_name}</p>
                    <p class="text-xs text-gray-500">${ref.referred_email}</p>
                </td>
                <td class="py-3 px-4">
                    <code class="text-xs bg-gray-100 px-2 py-1 rounded">${ref.referral_code}</code>
                </td>
                <td class="py-3 px-4">
                    <span class="px-2 py-1 rounded-full text-xs font-semibold ${
                        ref.status === 'completed' ? 'bg-green-100 text-green-700' : 
                        ref.status === 'pending' ? 'bg-yellow-100 text-yellow-700' : 
                        'bg-red-100 text-red-700'
                    }">
                        ${ref.status.charAt(0).toUpperCase() + ref.status.slice(1)}
                    </span>
                </td>
                <td class="py-3 px-4">
                    <span class="font-bold text-primary-600">${ref.points_awarded || 0}</span>
                </td>
                <td class="py-3 px-4">
                    <p class="text-xs text-gray-600">${new Date(ref.signup_date).toLocaleDateString()}</p>
                    ${ref.first_purchase_date ? `<p class="text-xs text-gray-500">Purchased: ${new Date(ref.first_purchase_date).toLocaleDateString()}</p>` : ''}
                </td>
            </tr>
        `).join('');
    }

    // Initialize
    window.addEventListener('DOMContentLoaded', () => {
        loadStats();
        loadReferrals();
    });
</script>

</body>
</html>
