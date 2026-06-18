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
    <title>Referral Settings - Admin</title>
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
                    <h1 class="text-3xl font-bold text-gray-900">Referral Settings</h1>
                    <p class="text-gray-600 mt-1">Configure referral system parameters</p>
                </div>
                <a href="/app/views/admin/referrals/dashboard.php" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold rounded-lg transition-colors">
                    ← Back to Dashboard
                </a>
            </div>
        </div>
    </header>

    <!-- Content -->
    <main class="p-8">
        <div class="max-w-3xl">
            <!-- Settings Form -->
            <div class="stat-card">
                <form id="settingsForm" class="space-y-6">
                    <!-- Points per Referral -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            🎁 Points per Completed Referral
                        </label>
                        <input 
                            type="number" 
                            name="points_per_referral" 
                            id="points_per_referral"
                            min="1"
                            step="1"
                            class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:border-primary-500"
                            placeholder="100"
                            required
                        >
                        <p class="text-xs text-gray-500 mt-2">Points awarded when a referred user makes their first purchase</p>
                    </div>

                    <!-- Point Value -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            💰 Point Value (in Rupees)
                        </label>
                        <input 
                            type="number" 
                            name="point_value_in_rupees" 
                            id="point_value_in_rupees"
                            min="0.01"
                            step="0.01"
                            class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:border-primary-500"
                            placeholder="1.00"
                            required
                        >
                        <p class="text-xs text-gray-500 mt-2">Value of 1 point when converting to coupon (e.g., 1 point = ₹1)</p>
                    </div>

                    <!-- Signup Discount Percentage -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            🎉 Signup Discount Percentage
                        </label>
                        <input 
                            type="number" 
                            name="signup_discount_percentage" 
                            id="signup_discount_percentage"
                            min="0"
                            max="100"
                            step="1"
                            class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:border-primary-500"
                            placeholder="40"
                            required
                        >
                        <p class="text-xs text-gray-500 mt-2">Discount % given to referred users on their first purchase</p>
                    </div>

                    <!-- Max Discount Amount -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            🎯 Maximum Signup Discount Amount
                        </label>
                        <input 
                            type="number" 
                            name="max_discount_amount" 
                            id="max_discount_amount"
                            min="0"
                            step="1"
                            class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:border-primary-500"
                            placeholder="1000"
                            required
                        >
                        <p class="text-xs text-gray-500 mt-2">Maximum discount amount for signup discount (in Rupees)</p>
                    </div>

                    <!-- Min Points for Redemption -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            ✨ Minimum Points for Redemption
                        </label>
                        <input 
                            type="number" 
                            name="min_points_for_redemption" 
                            id="min_points_for_redemption"
                            min="1"
                            step="1"
                            class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:border-primary-500"
                            placeholder="50"
                            required
                        >
                        <p class="text-xs text-gray-500 mt-2">Minimum points required to redeem for coupon</p>
                    </div>

                    <!-- Enable/Disable System -->
                    <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                        <div>
                            <p class="font-semibold text-gray-900">Enable Referral System</p>
                            <p class="text-xs text-gray-500 mt-1">Turn on/off the entire referral system</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="referral_system_enabled" id="referral_system_enabled" class="sr-only peer">
                            <div class="w-11 h-6 bg-gray-300 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-primary-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary-600"></div>
                        </label>
                    </div>

                    <!-- Save Button -->
                    <div class="flex gap-4">
                        <button 
                            type="submit" 
                            class="flex-1 bg-primary-600 hover:bg-primary-700 text-white px-6 py-3 rounded-lg font-semibold transition-colors"
                        >
                            💾 Save Settings
                        </button>
                        <button 
                            type="button"
                            onclick="loadSettings()"
                            class="px-6 py-3 bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold rounded-lg transition-colors"
                        >
                            🔄 Reset
                        </button>
                    </div>
                </form>
            </div>

            <!-- Preview Section -->
            <div class="stat-card mt-6 bg-gradient-to-br from-blue-50 to-indigo-50 border-blue-200">
                <h3 class="text-lg font-bold text-gray-900 mb-4">📊 Current Configuration Preview</h3>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-xs text-gray-600">Referral Bonus</p>
                        <p class="text-xl font-bold text-gray-900"><span id="preview_points">100</span> points</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-600">Points Worth</p>
                        <p class="text-xl font-bold text-gray-900">₹<span id="preview_value">1</span> / point</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-600">Signup Discount</p>
                        <p class="text-xl font-bold text-gray-900"><span id="preview_discount">40</span>%</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-600">Min Redemption</p>
                        <p class="text-xl font-bold text-gray-900"><span id="preview_min">50</span> points</p>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
    async function loadSettings() {
        try {
            const response = await fetch('/api/admin-referrals.php?action=settings');
            const result = await response.json();

            if (result.success) {
                const settings = result.settings;
                
                // Fill form
                document.getElementById('points_per_referral').value = settings.points_per_referral || 100;
                document.getElementById('point_value_in_rupees').value = settings.point_value_in_rupees || 1;
                document.getElementById('signup_discount_percentage').value = settings.signup_discount_percentage || 40;
                document.getElementById('max_discount_amount').value = settings.max_discount_amount || 1000;
                document.getElementById('min_points_for_redemption').value = settings.min_points_for_redemption || 50;
                document.getElementById('referral_system_enabled').checked = settings.referral_system_enabled == '1';
                
                // Update preview
                updatePreview();
            }
        } catch (error) {
            console.error('Error:', error);
        }
    }

    function updatePreview() {
        document.getElementById('preview_points').textContent = document.getElementById('points_per_referral').value;
        document.getElementById('preview_value').textContent = document.getElementById('point_value_in_rupees').value;
        document.getElementById('preview_discount').textContent = document.getElementById('signup_discount_percentage').value;
        document.getElementById('preview_min').textContent = document.getElementById('min_points_for_redemption').value;
    }

    // Update preview on input change
    document.querySelectorAll('input[type="number"]').forEach(input => {
        input.addEventListener('input', updatePreview);
    });

    document.getElementById('settingsForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const formData = new FormData(e.target);
        const data = {
            points_per_referral: formData.get('points_per_referral'),
            point_value_in_rupees: formData.get('point_value_in_rupees'),
            signup_discount_percentage: formData.get('signup_discount_percentage'),
            max_discount_amount: formData.get('max_discount_amount'),
            min_points_for_redemption: formData.get('min_points_for_redemption'),
            referral_system_enabled: document.getElementById('referral_system_enabled').checked ? '1' : '0'
        };

        try {
            const response = await fetch('/api/admin-referrals.php?action=update-settings', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(data)
            });

            const result = await response.json();

            if (result.success) {
                alert('✅ Settings saved successfully!');
                updatePreview();
            } else {
                alert('❌ Error: ' + result.error);
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Network error. Please try again.');
        }
    });

    // Load settings on page load
    window.addEventListener('DOMContentLoaded', loadSettings);
</script>

</body>
</html>
